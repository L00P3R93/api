<?php

namespace App\Services;

use App\Models\ReferralBonus;
use App\Models\ReferralWallet;
use App\Models\ReferralWithdrawal;
use App\Services\Concerns\BuildsFinanceQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The referral programme in money terms. Bonuses are earned into referral wallets without any cash moving;
 * they become an expense when a withdrawal pays them out from the referral shortcode. Unspent referral
 * wallet balances can be withdrawn at any time, so they are owed to customers (a liability).
 */
class FinanceReferralReportService
{
    use BuildsFinanceQueries;

    /**
     * Bonuses earned per day and milestone, by when they were paid into the referral wallet.
     *
     * @return iterable<object{day: string, milestone: string, bonuses: int, amount: string}>
     */
    public function bonusRows(FinanceDateRange $range): iterable
    {
        return $this->bonusBase($range, [])
            ->selectRaw('DATE(b.created_at) as day, b.milestone, COUNT(*) as bonuses, SUM(b.amount) as amount')
            ->groupByRaw('DATE(b.created_at), b.milestone')
            ->get();
    }

    /**
     * Referral payouts per day: completed ones by when they completed, failed ones by when they failed,
     * and ones still open by when they were requested.
     *
     * @return list<object{day: string, status: string, amount: float}>
     */
    public function payoutRows(FinanceDateRange $range): array
    {
        $excluded = $this->excludedCustomerIds($range);

        $byDay = fn (string $column, array $statuses) => DB::table('referral_withdrawals')
            ->whereIn('status', $statuses)
            ->whereBetween($column, [$range->from, $range->to])
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotIn('customer_id', $excluded))
            ->selectRaw("DATE({$column}) as day, SUM(amount) as amount")
            ->groupByRaw("DATE({$column})")
            ->get();

        $rows = [];
        foreach ([
            'paid' => $byDay('completed_at', [ReferralWithdrawal::STATUS_COMPLETED]),
            'failed' => $byDay('failed_at', [ReferralWithdrawal::STATUS_FAILED]),
            'pending' => $byDay('created_at', [ReferralWithdrawal::STATUS_PENDING, ReferralWithdrawal::STATUS_PROCESSING]),
        ] as $status => $days) {
            foreach ($days as $day) {
                $rows[] = (object) ['day' => $day->day, 'status' => $status, 'amount' => (float) $day->amount];
            }
        }

        return $rows;
    }

    /**
     * Programme totals for a range plus the current position.
     *
     * @return array<string, mixed>
     */
    public function summary(FinanceDateRange $range): array
    {
        $bonuses = ['signup' => 0.0, 'first_deposit' => 0.0, 'total' => 0.0, 'count' => 0];
        foreach ($this->bonusRows($range) as $row) {
            $bonuses[$row->milestone] = ($bonuses[$row->milestone] ?? 0.0) + (float) $row->amount;
            $bonuses['total'] += (float) $row->amount;
            $bonuses['count'] += (int) $row->bonuses;
        }

        $payouts = ['paid' => 0.0, 'pending' => 0.0, 'failed' => 0.0];
        foreach ($this->payoutRows($range) as $row) {
            $payouts[$row->status] += $row->amount;
        }

        $open = ReferralWithdrawal::open();

        return [
            'bonuses_earned' => array_map(fn ($value) => is_float($value) ? $this->money($value) : $value, $bonuses),
            'payouts' => array_map(fn (float $value) => $this->money($value), $payouts),
            'position' => [
                'unspent_balances' => $this->unspentBalances(),
                'open_withdrawals' => ['count' => (clone $open)->count(), 'amount' => $this->money((clone $open)->sum('amount'))],
                'lifetime_bonuses' => $this->money(ReferralBonus::sum('amount')),
                'lifetime_paid_out' => $this->money(ReferralWithdrawal::where('status', ReferralWithdrawal::STATUS_COMPLETED)->sum('amount')),
            ],
            'notes' => [
                'bonuses_earned are credited to referral wallets; no cash moves and no expense is booked when they are earned.',
                'payouts.paid is the expense: referral withdrawals completed in the range, paid from the referral shortcode. The house pays the B2C fee, which is recorded through /finance/expenses.',
                'unspent_balances can be withdrawn at any time, so the balance sheet lists them as a liability.',
            ],
        ];
    }

    /**
     * Everything still sitting in referral wallets, leaving out test customers.
     */
    public function unspentBalances(): float
    {
        return $this->money(ReferralWallet::whereNotIn('customer_id', config('finance.test_customer_ids'))->sum('balance'));
    }

    /**
     * Bonuses paid into referral wallets inside the range.
     *
     * Filters: type (signup, first_deposit), customer_id (the referrer).
     *
     * @param  array<string, string>  $filters
     */
    public function bonuses(FinanceDateRange $range, array $filters): FinanceListing
    {
        $query = $this->bonusBase($range, $filters)
            ->leftJoin('customers as c', 'c.id', '=', 'b.customer_id')
            ->leftJoin('customers as r', 'r.id', '=', 'b.referred_id')
            ->leftJoin('ledger_entries as l', 'l.id', '=', 'b.ledger_entry_id')
            ->select('b.*', 'c.name as referrer_name', 'r.name as referred_name', 'l.entry_id')
            ->orderByDesc('b.created_at')
            ->orderByDesc('b.id');

        $summary = function () use ($range, $filters) {
            $rows = $this->bonusBase($range, $filters)
                ->selectRaw('b.milestone, COUNT(*) as bonuses, SUM(b.amount) as amount')
                ->groupBy('b.milestone')
                ->get()
                ->keyBy('milestone');

            return [
                'by_milestone' => collect(ReferralBonus::MILESTONES)->mapWithKeys(fn (string $milestone) => [$milestone => [
                    'bonuses' => (int) ($rows->get($milestone)->bonuses ?? 0),
                    'amount' => $this->money($rows->get($milestone)->amount ?? 0),
                ]])->all(),
                'total' => $this->money($rows->sum('amount')),
            ];
        };

        return new FinanceListing(
            $query,
            fn (object $row) => [
                'id' => (int) $row->id,
                'paid_at' => $this->moment($row->created_at),
                'referral_id' => (int) $row->referral_id,
                'customer_id' => (int) $row->customer_id,
                'referrer_name' => $row->referrer_name,
                'referred_id' => (int) $row->referred_id,
                'referred_name' => $row->referred_name,
                'milestone' => $row->milestone,
                'amount' => $this->money($row->amount),
                'ledger_entry_id' => $row->entry_id,
            ],
            $summary,
            ['id', 'paid_at', 'referral_id', 'customer_id', 'referrer_name', 'referred_id', 'referred_name', 'milestone', 'amount', 'ledger_entry_id'],
        );
    }

    /**
     * Referral withdrawals requested inside the range. Phone numbers are masked.
     *
     * Filters: status, customer_id.
     *
     * @param  array<string, string>  $filters
     */
    public function withdrawals(FinanceDateRange $range, array $filters): FinanceListing
    {
        $excluded = $this->excludedCustomerIds($range);

        $base = fn () => DB::table('referral_withdrawals as w')
            ->whereBetween('w.created_at', [$range->from, $range->to])
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotIn('w.customer_id', $excluded))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('w.status', $filters['status']))
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->where('w.customer_id', $filters['customer_id']));

        $query = $base()
            ->leftJoin('customers as c', 'c.id', '=', 'w.customer_id')
            ->select('w.*', 'c.name as customer_name')
            ->orderByDesc('w.created_at')
            ->orderByDesc('w.id');

        $summary = function () use ($base) {
            $rows = $base()
                ->selectRaw('w.status, COUNT(*) as withdrawals, SUM(w.amount) as amount')
                ->groupBy('w.status')
                ->get()
                ->keyBy('status');

            return [
                'by_status' => collect(ReferralWithdrawal::STATUSES)->mapWithKeys(fn (string $status) => [$status => [
                    'withdrawals' => (int) ($rows->get($status)->withdrawals ?? 0),
                    'amount' => $this->money($rows->get($status)->amount ?? 0),
                ]])->all(),
            ];
        };

        $masker = app(FinanceMasker::class);

        return new FinanceListing(
            $query,
            fn (object $row) => [
                'id' => (int) $row->id,
                'requested_at' => $this->moment($row->created_at),
                'customer_id' => (int) $row->customer_id,
                'customer_name' => $row->customer_name,
                'phone_no' => $masker->phone($row->phone_no),
                'amount' => $this->money($row->amount),
                'status' => $row->status,
                'mpesa_receipt' => $row->mpesa_receipt,
                'result_code' => $row->result_code,
                'result_desc' => $row->result_desc,
                'completed_at' => $this->moment($row->completed_at),
                'failed_at' => $this->moment($row->failed_at),
            ],
            $summary,
            ['id', 'requested_at', 'customer_id', 'customer_name', 'phone_no', 'amount', 'status', 'mpesa_receipt', 'result_code', 'result_desc', 'completed_at', 'failed_at'],
        );
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function bonusBase(FinanceDateRange $range, array $filters): Builder
    {
        $excluded = $this->excludedCustomerIds($range);

        return DB::table('referral_bonuses as b')
            ->whereBetween('b.created_at', [$range->from, $range->to])
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotIn('b.customer_id', $excluded))
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('b.milestone', $filters['type']))
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->where('b.customer_id', $filters['customer_id']));
    }
}
