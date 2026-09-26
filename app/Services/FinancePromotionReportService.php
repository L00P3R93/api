<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\PromotionCredit;
use App\Services\Concerns\BuildsFinanceQueries;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Promotions in money terms. The house wallet pays each credit's gross amount; the customer keeps the net
 * and the excise duty is owed to KRA like duty on a deposit. The gross is the promotions expense.
 */
class FinancePromotionReportService
{
    use BuildsFinanceQueries;

    /**
     * Gross cost of promotion credits per day, by when they were granted.
     *
     * @return iterable<object{day: string, credits: int, amount: string}>
     */
    public function costRows(FinanceDateRange $range): iterable
    {
        return $this->creditBase($range, [])
            ->selectRaw('DATE(p.created_at) as day, COUNT(*) as credits, SUM(p.gross_amount) as amount')
            ->groupByRaw('DATE(p.created_at)')
            ->get();
    }

    /**
     * Promotion credits granted inside the range, newest first.
     *
     * Filters: type (the promotion, e.g. signup_bonus), customer_id, kind (the promo code).
     *
     * @param  array<string, string>  $filters
     */
    public function credits(FinanceDateRange $range, array $filters): FinanceListing
    {
        $query = $this->creditBase($range, $filters)
            ->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
            ->leftJoin('ledger_entries as l', 'l.id', '=', 'p.ledger_entry_id')
            ->leftJoin('promo_codes as pc', 'pc.id', '=', 'p.promo_code_id')
            ->select('p.*', 'c.name as customer_name', 'l.entry_id', 'pc.code as promo_code')
            ->orderByDesc('p.created_at')
            ->orderByDesc('p.id');

        $summary = function () use ($range, $filters) {
            $rows = $this->creditBase($range, $filters)
                ->selectRaw('p.promotion, COUNT(*) as credits, SUM(p.gross_amount) as gross, SUM(p.excise_amount) as excise, SUM(p.net_amount) as net')
                ->groupBy('p.promotion')
                ->get();

            return [
                'credits' => (int) $rows->sum('credits'),
                'gross_amount' => $this->money($rows->sum('gross')),
                'excise_amount' => $this->money($rows->sum('excise')),
                'net_amount' => $this->money($rows->sum('net')),
                'by_promotion' => $rows->mapWithKeys(fn ($row) => [$row->promotion => [
                    'credits' => (int) $row->credits,
                    'gross_amount' => $this->money($row->gross),
                    'excise_amount' => $this->money($row->excise),
                    'net_amount' => $this->money($row->net),
                ]])->all(),
                'budget_cap' => config('promotions.signup_bonus.budget_cap'),
                'signup_bonus_spent' => $this->money(DB::table('promotion_credits')->where('promotion', PromotionCredit::PROMOTION_SIGNUP_BONUS)->sum('gross_amount')),
            ];
        };

        return new FinanceListing(
            $query,
            fn (object $row) => [
                'id' => (int) $row->id,
                'granted_at' => $this->moment($row->created_at),
                'customer_id' => (int) $row->customer_id,
                'customer_name' => $row->customer_name,
                'promotion' => $row->promotion,
                'promo_code' => $row->promo_code,
                'gross_amount' => $this->money($row->gross_amount),
                'rate' => (float) $row->rate,
                'excise_amount' => $this->money($row->excise_amount),
                'net_amount' => $this->money($row->net_amount),
                'status' => $row->status,
                'ledger_entry_id' => $row->entry_id,
            ],
            $summary,
            ['id', 'granted_at', 'customer_id', 'customer_name', 'promotion', 'promo_code', 'gross_amount', 'rate', 'excise_amount', 'net_amount', 'status', 'ledger_entry_id'],
        );
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function creditBase(FinanceDateRange $range, array $filters): Builder
    {
        $excluded = $this->excludedCustomerIds($range);

        return DB::table('promotion_credits as p')
            ->whereBetween('p.created_at', [$range->from, $range->to])
            ->when($excluded !== [], fn (Builder $query) => $query->whereNotIn('p.customer_id', $excluded))
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('p.promotion', $filters['type']))
            ->when(isset($filters['customer_id']), fn (Builder $query) => $query->where('p.customer_id', (int) $filters['customer_id']))
            ->when(isset($filters['kind']), fn (Builder $query) => $query->whereIn('p.promo_code_id', DB::table('promo_codes')->where('code', PromoCode::normalise($filters['kind']))->select('id')));
    }
}
