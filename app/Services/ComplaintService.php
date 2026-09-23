<?php

namespace App\Services;

use App\Exceptions\ComplaintException;
use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use App\Models\Complaint;
use App\Models\DisputedTransaction;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Complaints about game, tournament and jackpot results. Filing one moves the disputed winnings into
 * dispute escrow so the winner cannot spend them while the complaint is pending.
 */
class ComplaintService
{
    private const GAME_PAYOUT_TYPES = ['payout', 'payout|dropped'];

    private const OPEN_COMPETITION_TYPES = ['win', 'deposit'];

    /**
     * Customer and competition wallets locked during this filing, so two disputes held from the same
     * wallet reduce one in-memory balance.
     *
     * @var array<string, Wallet|CompetitionWallet>
     */
    private array $lockedWallets = [];

    public function __construct(private LedgerService $ledgerService) {}

    /**
     * File a complaint and hold every disputed transaction. When a source wallet cannot cover a
     * transaction, whatever it has is held and the rest is recorded as a shortfall.
     *
     * @param  array{customer_id: int, game_wallet_id?: ?int, competition_wallet_id?: ?int, transaction_ids?: ?list<int>, reason: string, description?: ?string}  $data
     *
     * @throws ComplaintException
     */
    public function file(array $data, ?string $actor): Complaint
    {
        $this->lockedWallets = [];

        return DB::transaction(function () use ($data, $actor) {
            $customerId = (int) $data['customer_id'];
            $transactionIds = array_map('intval', $data['transaction_ids'] ?? []);

            [$subjectType, $targets] = empty($data['game_wallet_id'])
                ? $this->competitionTargets((int) $data['competition_wallet_id'], $customerId, $transactionIds)
                : $this->gameTargets((int) $data['game_wallet_id'], $customerId, $transactionIds);

            foreach ($targets as $target) {
                $this->ensureNotAlreadyDisputed($target['transaction']);
            }

            $complaint = Complaint::create([
                'complaint_id' => (string) Str::uuid(),
                'customer_id' => $customerId,
                'subject_type' => $subjectType,
                'game_wallet_id' => $data['game_wallet_id'] ?? null,
                'competition_wallet_id' => $data['competition_wallet_id'] ?? null,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'status' => Complaint::STATUS_PENDING,
                'filed_by' => $actor,
            ]);

            $disputed = $held = 0.0;

            foreach ($targets as $target) {
                $dispute = $this->hold($complaint, $target['transaction'], $target['source'], $target['amount']);

                $disputed += (float) $dispute->amount;
                $held += (float) $dispute->held_amount;
            }

            $complaint->update([
                'disputed_amount' => round($disputed, 2),
                'held_amount' => round($held, 2),
                'shortfall_amount' => round($disputed - $held, 2),
            ]);

            return $complaint->load('disputedTransactions');
        });
    }

    /**
     * The winner payouts of a game. The complainant must have played in it and cannot dispute their own payout.
     *
     * @param  list<int>  $transactionIds
     * @return array{0: string, 1: list<array{transaction: GameTransaction, source: Wallet, amount: float}>}
     *
     * @throws ComplaintException
     */
    private function gameTargets(int $gameWalletId, int $customerId, array $transactionIds): array
    {
        $gameWallet = GameWallet::lockForUpdate()->findOrFail($gameWalletId);

        $played = GameTransaction::where('game_wallet_id', $gameWallet->id)
            ->where('customer_id', $customerId)
            ->where('payment_type', 'deposit')
            ->exists();

        if (! $played) {
            throw new ComplaintException('Only a player in this game can complain about it.');
        }

        $payouts = GameTransaction::where('game_wallet_id', $gameWallet->id)
            ->whereIn('payment_type', self::GAME_PAYOUT_TYPES)
            ->whereNotIn('customer_id', [$this->houseCustomerId(), $customerId])
            ->when($transactionIds !== [], fn ($query) => $query->whereIn('id', $transactionIds))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $this->ensureAllFound($payouts, $transactionIds, 'winner payouts in this game');

        if ($payouts->isEmpty()) {
            throw new ComplaintException('This game has no winner payout to dispute.');
        }

        $targets = $payouts->map(fn (GameTransaction $payout) => [
            'transaction' => $payout,
            'source' => $this->customerWallet($payout->customer_id),
            'amount' => (float) $payout->amount,
        ])->all();

        return [Complaint::SUBJECT_GAME, $targets];
    }

    /**
     * An open competition wallet has the named win/deposit transactions (default the latest win) held from
     * the wallet itself. A closed one has its payout held from the customer's wallet.
     *
     * @param  list<int>  $transactionIds
     * @return array{0: string, 1: list<array{transaction: CompetitionTransaction, source: Wallet|CompetitionWallet, amount: float}>}
     *
     * @throws ComplaintException
     */
    private function competitionTargets(int $competitionWalletId, int $customerId, array $transactionIds): array
    {
        $competitionWallet = $this->lockedCompetitionWallet($competitionWalletId);

        $subjectType = match ((int) $competitionWallet->game_type) {
            1 => Complaint::SUBJECT_TOURNAMENT,
            2 => Complaint::SUBJECT_JACKPOT,
            default => throw new ComplaintException('Unsupported competition type.'),
        };

        if ((int) $competitionWallet->customer_id === $customerId) {
            throw new ComplaintException('A customer cannot complain about their own competition wallet.');
        }

        $played = CompetitionWallet::where('cmp_uid', $competitionWallet->cmp_uid)
            ->where('customer_id', $customerId)
            ->exists();

        if (! $played) {
            throw new ComplaintException('Only a player in this competition can complain about it.');
        }

        $isOpen = (int) $competitionWallet->status === 1;

        $transactions = CompetitionTransaction::where('competition_wallet_id', $competitionWallet->id)
            ->when(
                $isOpen,
                fn ($query) => $query->whereIn('payment_type', $transactionIds === [] ? ['win'] : self::OPEN_COMPETITION_TYPES),
                fn ($query) => $query->where('payment_type', 'payout')
            )
            ->when($transactionIds !== [], fn ($query) => $query->whereIn('id', $transactionIds))
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        $this->ensureAllFound($transactions, $transactionIds, $isOpen ? 'win or deposit transactions on this competition wallet' : 'payouts from this competition wallet');

        if ($isOpen && $transactionIds === []) {
            $transactions = $transactions->take(1);
        }

        if ($transactions->isEmpty()) {
            throw new ComplaintException($isOpen
                ? 'This competition wallet has no win to dispute.'
                : 'This competition wallet has no payout to dispute.');
        }

        $targets = $transactions->sortBy('id')->values()->map(fn (CompetitionTransaction $transaction) => [
            'transaction' => $transaction,
            'source' => $isOpen ? $competitionWallet : $this->customerWallet($competitionWallet->customer_id),
            'amount' => $this->creditedToCompetitionWallet($transaction),
        ])->all();

        return [$subjectType, $targets];
    }

    /**
     * Create the disputed transaction row and move what the source can cover into dispute escrow.
     */
    private function hold(Complaint $complaint, GameTransaction|CompetitionTransaction $transaction, Wallet|CompetitionWallet $source, float $amount): DisputedTransaction
    {
        $heldAmount = round(min($amount, max((float) $source->balance, 0.0)), 2);

        $dispute = DisputedTransaction::create([
            'complaint_id' => $complaint->id,
            'disputable_type' => $transaction::class,
            'disputable_id' => $transaction->id,
            'customer_id' => $transaction->customer_id,
            'source_wallet_type' => $source instanceof Wallet ? LedgerEntry::WALLET_TYPE_WALLET : LedgerEntry::WALLET_TYPE_COMPETITION,
            'source_wallet_id' => $source->id,
            'amount' => $amount,
            'held_amount' => $heldAmount,
            'shortfall_amount' => round($amount - $heldAmount, 2),
            'balance' => 0,
            'status' => DisputedTransaction::STATUS_HELD,
        ]);

        if ($heldAmount > 0) {
            $this->ledgerService->recordDisputeHold($dispute, $source, $heldAmount);
        }

        return $dispute;
    }

    /**
     * @throws ComplaintException
     */
    private function ensureNotAlreadyDisputed(GameTransaction|CompetitionTransaction $transaction): void
    {
        $open = DisputedTransaction::held()
            ->where('disputable_type', $transaction::class)
            ->where('disputable_id', $transaction->id)
            ->exists();

        if ($open) {
            throw ComplaintException::conflict("Transaction {$transaction->id} is already under an open complaint.");
        }
    }

    /**
     * @param  Collection<int, GameTransaction|CompetitionTransaction>  $found
     * @param  list<int>  $transactionIds
     *
     * @throws ComplaintException
     */
    private function ensureAllFound(Collection $found, array $transactionIds, string $expected): void
    {
        $missing = array_values(array_diff(array_unique($transactionIds), $found->pluck('id')->all()));

        if ($missing !== []) {
            throw new ComplaintException('transaction_ids must be '.$expected.'. Not found: '.implode(', ', $missing).'.');
        }
    }

    /**
     * What a competition transaction put into its wallet. A bet credits its stake minus the house cut.
     */
    private function creditedToCompetitionWallet(CompetitionTransaction $transaction): float
    {
        if ($transaction->payment_type === 'deposit'
            && $transaction->competition_wallet_balance_before !== null
            && $transaction->competition_wallet_balance_after !== null) {
            return round((float) $transaction->competition_wallet_balance_after - (float) $transaction->competition_wallet_balance_before, 2);
        }

        return (float) $transaction->amount;
    }

    /**
     * @throws ComplaintException
     */
    private function customerWallet(int $customerId): Wallet
    {
        $key = LedgerEntry::WALLET_TYPE_WALLET.':'.$customerId;

        if (! isset($this->lockedWallets[$key])) {
            $wallet = Wallet::where('customer_id', $customerId)->lockForUpdate()->first();

            if (! $wallet) {
                throw new ComplaintException("Customer {$customerId} has no wallet to hold the disputed money from.");
            }

            $this->lockedWallets[$key] = $wallet;
        }

        return $this->lockedWallets[$key];
    }

    private function lockedCompetitionWallet(int $competitionWalletId): CompetitionWallet
    {
        $key = LedgerEntry::WALLET_TYPE_COMPETITION.':'.$competitionWalletId;

        return $this->lockedWallets[$key] ??= CompetitionWallet::lockForUpdate()->findOrFail($competitionWalletId);
    }

    private function houseCustomerId(): int
    {
        return (int) (Wallet::whereKey(config('wallets.house_wallet_id', 1))->value('customer_id') ?? 1);
    }
}
