<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\DepositResolution;
use App\Models\HouseFunding;
use App\Models\Wallet;
use App\Services\Concerns\BuildsFinanceQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owner money put into the house wallet (which pays signup bonuses). The owner pays the paybill with a bill
 * ref that matches no customer (e.g. HOUSE); the unmatched deposit is then resolved here. It is credited in
 * full as `house_funding` (capital): no excise duty, no referral hook, no customer wallet transaction, and
 * it is kept out of customer deposit figures.
 *
 * Voiding reverses the credit and puts the deposit back to unmatched, so a customer's payment funded by
 * mistake can still be assigned or refunded.
 */
class HouseFundingService
{
    use BuildsFinanceQueries;

    public const CODE_INSUFFICIENT_HOUSE_BALANCE = 'insufficient_house_balance';

    public function __construct(private LedgerService $ledgerService) {}

    public function houseWalletId(): int
    {
        return (int) config('wallets.house_wallet_id', 1);
    }

    /**
     * The customer who owns the house wallet, or null when there is no house wallet.
     */
    public function houseCustomerId(): ?int
    {
        $customerId = Wallet::whereKey($this->houseWalletId())->value('customer_id');

        return $customerId === null ? null : (int) $customerId;
    }

    /**
     * Credit an unmatched deposit to the house wallet as house funding.
     *
     * @return array{success: bool, message: string, status_code: int, deposit?: Deposit, house_funding?: HouseFunding, code?: string}
     */
    public function fund(int $depositId, string $note, ?string $actor): array
    {
        return DB::transaction(function () use ($depositId, $note, $actor) {
            $deposit = Deposit::lockForUpdate()->find($depositId);

            if (! $deposit) {
                return $this->refused('Deposit not found', 404);
            }

            if ((int) $deposit->status !== Deposit::STATUS_UNMATCHED) {
                return $this->refused('Only unmatched deposits can be recorded as house funding', 409, $deposit, UnmatchedDepositService::CODE_ALREADY_RESOLVED);
            }

            $houseWallet = Wallet::lockForUpdate()->find($this->houseWalletId());

            if (! $houseWallet) {
                return $this->refused('House wallet not found', 500);
            }

            $amount = (float) $deposit->trans_amount;
            $ledgerEntry = $this->ledgerService->recordHouseFunding($deposit, $houseWallet, $amount);

            $deposit->update(['status' => Deposit::STATUS_COMPLETED]);

            $funding = HouseFunding::create([
                'deposit_id' => $deposit->id,
                'amount' => $amount,
                'ledger_entry_id' => $ledgerEntry->id,
                'note' => $note,
                'recorded_by' => $actor,
            ]);

            DepositResolution::create([
                'deposit_id' => $deposit->id,
                'action' => DepositResolution::ACTION_HOUSE_FUNDED,
                'customer_id' => $houseWallet->customer_id,
                'ledger_entry_id' => $ledgerEntry->id,
                'note' => $note,
                'resolved_by' => $actor,
            ]);

            Log::channel('mpesa')->info('Unmatched deposit recorded as house funding', ['deposit_id' => $deposit->id, 'house_funding_id' => $funding->id, 'amount' => $amount, 'by' => $actor]);

            return [
                'success' => true,
                'message' => 'Deposit credited to the house wallet',
                'status_code' => 200,
                'deposit' => $deposit->fresh('resolution'),
                'house_funding' => $funding,
            ];
        });
    }

    /**
     * Void a house funding: reverse the credit and put the deposit back to unmatched. Refused when the house
     * wallet no longer holds the amount (it has been spent on bonuses).
     *
     * @return array{success: bool, message: string, status_code: int, house_funding?: HouseFunding, code?: string}
     */
    public function void(int $id, string $reason, ?string $actor): array
    {
        return DB::transaction(function () use ($id, $reason, $actor) {
            $funding = HouseFunding::lockForUpdate()->find($id);

            if (! $funding) {
                return $this->refused('House funding not found', 404);
            }

            if ($funding->isVoided()) {
                return $this->refused('House funding is already voided', 409, code: UnmatchedDepositService::CODE_ALREADY_RESOLVED) + ['house_funding' => $funding];
            }

            $deposit = Deposit::lockForUpdate()->findOrFail($funding->deposit_id);
            $houseWallet = Wallet::lockForUpdate()->findOrFail($this->houseWalletId());
            $amount = (float) $funding->amount;

            if (round((float) $houseWallet->balance - $amount, 2) < 0) {
                return $this->refused('The house wallet no longer holds this amount, so it cannot be reversed', 409, code: self::CODE_INSUFFICIENT_HOUSE_BALANCE) + ['house_funding' => $funding];
            }

            $reversal = $this->ledgerService->reverseHouseFunding($funding->ledgerEntry, $houseWallet, $reason);

            $funding->update([
                'voided_at' => now(),
                'voided_by' => $actor,
                'void_reason' => $reason,
                'void_ledger_entry_id' => $reversal->id,
            ]);

            // One resolution per deposit: the funding row keeps the history, and the deposit is open again.
            DepositResolution::where('deposit_id', $deposit->id)->where('action', DepositResolution::ACTION_HOUSE_FUNDED)->delete();
            $deposit->update(['status' => Deposit::STATUS_UNMATCHED]);

            Log::channel('mpesa')->info('House funding voided', ['house_funding_id' => $funding->id, 'deposit_id' => $deposit->id, 'by' => $actor]);

            return ['success' => true, 'message' => 'House funding voided; the deposit is unmatched again', 'status_code' => 200, 'house_funding' => $funding->fresh()];
        });
    }

    /**
     * House fundings recorded in the range.
     *
     * Filters: status (active by default, or voided, or all).
     *
     * @param  array<string, string>  $filters
     */
    public function listing(FinanceDateRange $range, array $filters): FinanceListing
    {
        $status = $filters['status'] ?? 'active';

        $query = $this->base($range, $status)
            ->select('h.*', 'i.trans_id', 'i.trans_time', 'i.bill_ref_no')
            ->orderByDesc('h.id');

        $summary = function () use ($range) {
            $shape = fn (string $state) => $this->base($range, $state)
                ->selectRaw('COUNT(*) as entries, COALESCE(SUM(h.amount), 0) as amount')
                ->first();

            $active = $shape('active');
            $voided = $shape('voided');

            return [
                'active' => ['entries' => (int) $active->entries, 'amount' => $this->money($active->amount)],
                'voided' => ['entries' => (int) $voided->entries, 'amount' => $this->money($voided->amount)],
                'house_wallet_balance' => $this->money(Wallet::whereKey($this->houseWalletId())->value('balance') ?? 0),
            ];
        };

        return new FinanceListing(
            $query,
            fn (object $row) => $this->row($row),
            $summary,
            ['id', 'deposit_id', 'trans_id', 'trans_time', 'bill_ref_no', 'amount', 'note', 'status', 'recorded_by', 'created_at', 'voided_at', 'voided_by', 'void_reason'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function present(HouseFunding $funding): array
    {
        $deposit = $funding->deposit;

        return $this->row((object) [
            'id' => $funding->id,
            'deposit_id' => $funding->deposit_id,
            'trans_id' => $deposit?->trans_id,
            'trans_time' => $deposit?->trans_time,
            'bill_ref_no' => $deposit?->bill_ref_no,
            'amount' => $funding->amount,
            'note' => $funding->note,
            'voided_at' => $funding->voided_at?->toDateTimeString(),
            'recorded_by' => $funding->recorded_by,
            'created_at' => $funding->created_at?->toDateTimeString(),
            'voided_by' => $funding->voided_by,
            'void_reason' => $funding->void_reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'deposit_id' => (int) $row->deposit_id,
            'trans_id' => $row->trans_id,
            'trans_time' => $row->trans_time,
            'bill_ref_no' => $row->bill_ref_no,
            'amount' => $this->money($row->amount),
            'note' => $row->note,
            'status' => $row->voided_at === null ? 'active' : 'voided',
            'recorded_by' => $row->recorded_by,
            'created_at' => $this->moment($row->created_at),
            'voided_at' => $this->moment($row->voided_at),
            'voided_by' => $row->voided_by,
            'void_reason' => $row->void_reason,
        ];
    }

    private function base(FinanceDateRange $range, string $status): Builder
    {
        return DB::table('house_fundings as h')
            ->leftJoin('incoming_payments as i', 'i.id', '=', 'h.deposit_id')
            ->whereBetween('h.created_at', [$range->from, $range->to])
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('h.voided_at'))
            ->when($status === 'voided', fn (Builder $query) => $query->whereNotNull('h.voided_at'));
    }

    /**
     * @return array{success: false, message: string, status_code: int, deposit?: Deposit, code?: string}
     */
    private function refused(string $message, int $statusCode, ?Deposit $deposit = null, ?string $code = null): array
    {
        return array_filter([
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
            'deposit' => $deposit,
            'code' => $code,
        ], fn ($value) => $value !== null);
    }
}
