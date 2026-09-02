<?php

namespace App\Services;

use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionWalletService
{
    public function __construct(private LedgerService $ledgerService) {}

    public function listCompetitionWallets(): Collection
    {
        return CompetitionWallet::all();
    }

    public function createCompetitionWallet(array $data): CompetitionWallet
    {
        return CompetitionWallet::create($data);
    }

    public function getCompetitionWallet($identifier): ?CompetitionWallet
    {
        return CompetitionWallet::where('id', $identifier)
            ->orWhere('competition_id', $identifier)
            ->first();
    }

    public function getCompetitionsByCmpUid($cmpUid): Collection
    {
        return CompetitionWallet::where('cmp_uid', $cmpUid)->get();
    }

    public function getCompetitionIncome(string $gameType, ?string $startDate = null, ?string $endDate = null): array
    {
        return DB::table('competition_wallets as C')
            ->join('wallet_transactions as W', 'W.sender_id', '=', 'C.id')
            ->select('C.jp_rounds', DB::raw('SUM(W.amount) as total_income'))
            ->selectRaw('COUNT(DISTINCT C.id) as games_played')
            ->where('C.game_type', $gameType)
            ->where('C.jp_rounds', '>', 0)
            ->whereBetween('C.created_at', [$startDate, $endDate])
            ->groupBy('C.jp_rounds')
            ->orderBy('C.jp_rounds')
            ->get()
            ->toArray();
    }

    public function getCompetitionResults(string $gameType): array
    {
        return DB::table('competition_wallets as CW')
            ->join('competition_transactions as CT', 'CT.competition_wallet_id', '=', 'CW.id')
            ->join('wallet_transactions as WT', 'WT.sender_id', '=', 'CW.id')
            ->join('customers as C', 'CW.customer_id', '=', 'C.id')
            ->where('CW.game_type', $gameType)
            ->where('CW.jp_rounds', '>', 0)
            ->whereIn('CT.payment_type', ['win', 'loss'])
            ->select(
                'CW.id',
                'CW.competition_id',
                'CW.cmp_uid',
                'CW.customer_id',
                'C.name',
                'CW.level',
                'CW.jp_rounds',
                'CW.status',
                'CT.payment_type',
                'CT.amount',
                'WT.amount as income',
                'CT.created_at'
            )
            ->get()
            ->toArray();
    }

    public function getCompetitionAwards(string $gameType): array
    {
        $sub = DB::table('competition_transactions as CT')
            ->join('competition_wallets as CW', 'CT.competition_wallet_id', '=', 'CW.id')
            ->whereColumn('CW.level', '>=', 'CW.jp_rounds')
            ->where('CW.game_type', $gameType)
            ->where('CT.payment_type', 'win')
            ->select(DB::raw('MAX(CT.id) as latest_transaction_id'))
            ->groupBy('CW.competition_id');

        return DB::table('competition_transactions as CT')
            ->join('competition_wallets as CW', 'CT.competition_wallet_id', '=', 'CW.id')
            ->join('customers as C', 'CW.customer_id', '=', 'C.id')
            ->whereColumn('CW.level', '>=', 'CW.jp_rounds')
            ->where('CW.game_type', $gameType)
            ->where('CT.payment_type', 'win')
            ->whereIn('CT.id', $sub)
            ->select(
                'CT.id as transaction_id',
                'CW.competition_id',
                'CW.game_type',
                'C.name',
                'CW.cmp_uid',
                'CW.level',
                'CW.jp_rounds',
                'CT.payment_type',
                'CT.amount',
                'CT.created_at'
            )
            ->orderByDesc('CW.id')
            ->get()
            ->toArray();
    }

    public function updateCompetitionWallet($id, array $data): ?CompetitionWallet
    {
        $competitionWallet = CompetitionWallet::find($id);
        if (! $competitionWallet) {
            return null;
        }

        $competitionWallet->update($data);

        return $competitionWallet;
    }

    public function deleteCompetitionWallet($id): bool
    {
        $competitionWallet = CompetitionWallet::find($id);
        if (! $competitionWallet) {
            return false;
        }

        $competitionWallet->delete();

        return true;
    }

    public function processWithdrawal(int $competitionWalletId, int $customerId): array
    {
        $competitionWallet = CompetitionWallet::find($competitionWalletId);
        if (! $competitionWallet) {
            throw new \InvalidArgumentException('Competition Wallet not found');
        }

        if ($competitionWallet->status !== 1) {
            throw new \InvalidArgumentException('Competition Wallet is not open for withdrawal');
        }

        $customer = Customer::find($customerId);
        if (! $customer) {
            throw new \InvalidArgumentException('Customer not found');
        }

        $wallet = $customer->wallet;
        if (! $wallet) {
            throw new \InvalidArgumentException('Customer wallet not found');
        }

        DB::transaction(function () use ($competitionWallet, $wallet) {
            $totalBalance = $competitionWallet->balance;

            $competitionTransaction = CompetitionTransaction::create([
                'competition_wallet_id' => $competitionWallet->id,
                'amount' => $totalBalance,
                'payment_type' => 'payout',
                'level' => $competitionWallet->level,
                'status' => 2,
            ]);

            $ledgerEntry = $this->ledgerService->recordCompetitionPayout(
                $competitionTransaction,
                $wallet,
                (float) $totalBalance
            );

            $competitionTransaction->update([
                'wallet_balance_before' => $ledgerEntry->balance_before,
                'wallet_balance_after' => $ledgerEntry->balance_after,
                'competition_wallet_balance_before' => $competitionWallet->balance,
                'competition_wallet_balance_after' => 0,
            ]);

            $competitionWallet->status = 3;
            $competitionWallet->balance = 0;
            $competitionWallet->save();
        });

        return ['status' => 'success'];
    }

    public function listCompetitionTransactions(): Collection
    {
        return CompetitionTransaction::all();
    }

    public function createCompetitionTransaction(array $data): CompetitionTransaction
    {
        $competitionWallet = CompetitionWallet::find($data['competition_wallet_id']);
        if (! $competitionWallet) {
            throw new \InvalidArgumentException('Competition Wallet Not Found!');
        }
        if ($competitionWallet->customer_id != $data['customer_id']) {
            throw new \InvalidArgumentException('Customer ID do not match');
        }

        $customer = Customer::with('wallet')->find($data['customer_id']);
        if (! $customer || ! $customer->wallet || $customer->wallet->balance < $data['amount']) {
            throw new \InvalidArgumentException('Insufficient balance or Wallet not found.');
        }

        $wallet = $customer->wallet;

        $competitionTransaction = null;

        DB::transaction(function () use ($competitionWallet, $wallet, $data, &$competitionTransaction) {
            $totalBalance = $data['amount'];
            $competitionType = $competitionWallet->game_type;

            $houseCompetitionShare = 0;
            if ($competitionType == 1) {
                $houseCompetitionShare = round($totalBalance * 0.20, 2);
            } elseif ($competitionType == 2) {
                $houseCompetitionShare = round($totalBalance * 0.20, 2);
            }

            $competitionTransaction = CompetitionTransaction::create($data);

            [$walletEntry, $cwEntry] = $this->ledgerService->recordCompetitionBet(
                $competitionTransaction,
                $wallet,
                $competitionWallet,
                (float) $data['amount'],
                (float) $houseCompetitionShare
            );

            $competitionTransaction->update([
                'wallet_balance_before' => $walletEntry->balance_before,
                'wallet_balance_after' => $walletEntry->balance_after,
                'competition_wallet_balance_before' => $cwEntry->balance_before,
                'competition_wallet_balance_after' => $cwEntry->balance_after,
            ]);

            $competitionWallet->level += 1;
            $competitionWallet->save();

            if ($houseCompetitionShare > 0) {
                $houseWallet = Wallet::find(config('wallets.house_wallet_id', 1));
                $this->ledgerService->recordHouseCut(
                    $houseWallet,
                    (float) $houseCompetitionShare,
                    'competition_bet'
                );
            }

            WalletTransaction::create([
                'transaction_type' => 'c2w',
                'sender_id' => $competitionWallet->id,
                'receiver_id' => 1,
                'initiator_id' => 1,
                'amount' => $houseCompetitionShare,
            ]);
        });

        return $competitionTransaction;
    }

    public function getCompetitionTransaction(int $id): ?CompetitionTransaction
    {
        return CompetitionTransaction::find($id);
    }

    public function updateCompetitionTransaction(int $id, array $data): ?CompetitionTransaction
    {
        $competitionTransaction = CompetitionTransaction::find($id);
        if (! $competitionTransaction) {
            return null;
        }

        $competitionTransaction->update($data);

        return $competitionTransaction;
    }

    public function deleteCompetitionTransaction(int $id): bool
    {
        $competitionTransaction = CompetitionTransaction::find($id);
        if (! $competitionTransaction) {
            return false;
        }

        $competitionTransaction->delete();

        return true;
    }
}
