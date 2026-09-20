<?php

namespace App\Services\Concerns;

use App\Services\FinanceDateRange;
use Carbon\CarbonImmutable;

trait BuildsFinanceQueries
{
    /**
     * Customer ids to leave out of a report, empty when test customers are included.
     *
     * @return list<int>
     */
    private function excludedCustomerIds(FinanceDateRange $range): array
    {
        return $range->excludeTest ? array_values(config('finance.test_customer_ids')) : [];
    }

    /**
     * @param  list<int>  $values
     */
    private function placeholders(array $values): string
    {
        return implode(',', array_fill(0, max(count($values), 1), '?'));
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function moment(?string $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, config('app.timezone'))->toIso8601String();
    }
}
