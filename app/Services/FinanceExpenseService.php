<?php

namespace App\Services;

use App\Models\FinanceExpense;
use App\Services\Concerns\BuildsFinanceQueries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Operating expenses entered by hand. An expense is never edited or deleted: a wrong one is voided
 * with a reason (kept, but left out of totals) and entered again.
 */
class FinanceExpenseService
{
    use BuildsFinanceQueries;

    /**
     * @param  array{expense_date: string, category: string, amount: numeric-string|float|int, description?: ?string, reference?: ?string}  $data
     */
    public function record(array $data, ?string $actor): FinanceExpense
    {
        return FinanceExpense::create([
            'expense_date' => $data['expense_date'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
            'entered_by' => $actor,
        ]);
    }

    /**
     * Void an expense. Returns null when it was already voided, so a repeat call changes nothing.
     */
    public function void(int $id, string $reason, ?string $actor): ?FinanceExpense
    {
        return DB::transaction(function () use ($id, $reason, $actor) {
            $expense = FinanceExpense::lockForUpdate()->findOrFail($id);

            if ($expense->isVoided()) {
                return null;
            }

            $expense->update([
                'voided_at' => now(),
                'voided_by' => $actor,
                'void_reason' => $reason,
            ]);

            return $expense;
        });
    }

    /**
     * Active expenses in the range by category and by report bucket.
     *
     * @return array{total: float, by_category: array<string, float>, by_bucket: array<string, float>}
     */
    public function totals(FinanceDateRange $range): array
    {
        $rows = DB::table('finance_expenses')
            ->whereNull('voided_at')
            ->whereBetween('expense_date', [$range->from->toDateString(), $range->to->toDateString()])
            ->selectRaw('expense_date, category, SUM(amount) as amount')
            ->groupBy('expense_date', 'category')
            ->get();

        $total = 0.0;
        $byCategory = [];
        $byBucket = [];

        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            $bucket = $range->bucketFor(CarbonImmutable::parse($row->expense_date, config('app.timezone')));

            $total += $amount;
            $byCategory[$row->category] = ($byCategory[$row->category] ?? 0.0) + $amount;
            $byBucket[$bucket] = ($byBucket[$bucket] ?? 0.0) + $amount;
        }

        ksort($byCategory);

        return [
            'total' => round($total, 2),
            'by_category' => array_map(fn (float $amount) => round($amount, 2), $byCategory),
            'by_bucket' => array_map(fn (float $amount) => round($amount, 2), $byBucket),
        ];
    }

    /**
     * Recorded expenses dated inside the range.
     *
     * Filters: category, status (active by default, or voided, or all).
     *
     * @param  array<string, string>  $filters
     */
    public function listing(FinanceDateRange $range, array $filters): FinanceListing
    {
        $status = $filters['status'] ?? 'active';
        $base = fn () => $this->base($range, $filters, $status);

        $query = $base()
            ->select('e.*')
            ->orderByDesc('e.expense_date')
            ->orderByDesc('e.id');

        $summary = function () use ($range, $filters) {
            $shape = fn (string $state) => $this->base($range, $filters, $state)
                ->selectRaw('COUNT(*) as entries, COALESCE(SUM(e.amount), 0) as amount')
                ->first();

            $active = $shape('active');
            $voided = $shape('voided');
            $totals = $this->totals($range);

            return [
                'categories' => config('finance.expense_categories'),
                'active' => ['entries' => (int) $active->entries, 'amount' => $this->money($active->amount)],
                'voided' => ['entries' => (int) $voided->entries, 'amount' => $this->money($voided->amount)],
                'by_category' => empty($filters['category']) ? $totals['by_category'] : array_intersect_key($totals['by_category'], [$filters['category'] => true]),
            ];
        };

        return new FinanceListing(
            $query,
            fn (object $row) => [
                'id' => (int) $row->id,
                'expense_date' => $row->expense_date,
                'category' => $row->category,
                'amount' => $this->money($row->amount),
                'description' => $row->description,
                'reference' => $row->reference,
                'status' => $row->voided_at === null ? 'active' : 'voided',
                'entered_by' => $row->entered_by,
                'created_at' => $this->moment($row->created_at),
                'voided_at' => $this->moment($row->voided_at),
                'voided_by' => $row->voided_by,
                'void_reason' => $row->void_reason,
            ],
            $summary,
            ['id', 'expense_date', 'category', 'amount', 'description', 'reference', 'status', 'entered_by', 'created_at', 'voided_at', 'voided_by', 'void_reason'],
        );
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function base(FinanceDateRange $range, array $filters, string $status): Builder
    {
        return DB::table('finance_expenses as e')
            ->whereBetween('e.expense_date', [$range->from->toDateString(), $range->to->toDateString()])
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('e.voided_at'))
            ->when($status === 'voided', fn (Builder $query) => $query->whereNotNull('e.voided_at'))
            ->when(isset($filters['category']), fn (Builder $query) => $query->where('e.category', $filters['category']));
    }
}
