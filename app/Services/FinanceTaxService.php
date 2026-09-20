<?php

namespace App\Services;

/**
 * Estimated tax for a period from the rates in config/finance.php. A planning aid, not a tax
 * return: rates are off until set, and which taxes apply depends on how the games are classed.
 */
class FinanceTaxService
{
    public function __construct(private FinanceReportService $reports) {}

    /**
     * @return array<string, mixed>
     */
    public function estimate(FinanceDateRange $range): array
    {
        $statement = $this->reports->incomeStatement($range);
        $flows = $this->reports->flows($range);

        $revenue = (float) $statement['revenue']['total'];
        $expenses = (float) $statement['expenses']['total'];
        $netIncomeBeforeTax = $revenue - $expenses;

        $bases = [
            'stakes' => (float) $flows['stakes'],
            'winnings' => (float) $flows['payouts'],
            'revenue' => $revenue,
        ];

        $taxes = config('finance.taxes');
        $lines = [];
        $costTaxesOnOtherBases = 0.0;

        // Taxes on stakes, winnings and revenue first, so income tax can be worked out after the ones that are a cost.
        foreach ($taxes as $key => $tax) {
            if ($tax['base'] === 'net_income') {
                continue;
            }

            $lines[$key] = $this->line($tax, $bases[$tax['base']]);

            if ($tax['kind'] === 'expense') {
                $costTaxesOnOtherBases += $lines[$key]['estimated_amount'];
            }
        }

        $incomeBase = max(0.0, $netIncomeBeforeTax - $costTaxesOnOtherBases);

        foreach ($taxes as $key => $tax) {
            if ($tax['base'] === 'net_income') {
                $lines[$key] = $this->line($tax, $incomeBase);
            }
        }

        // Keep the configured order.
        $lines = array_replace(array_flip(array_keys($taxes)), $lines);

        $expenseTaxes = array_sum(array_map(fn (array $line) => $line['kind'] === 'expense' ? $line['estimated_amount'] : 0.0, $lines));
        $passThrough = array_sum(array_map(fn (array $line) => $line['kind'] === 'pass_through' ? $line['estimated_amount'] : 0.0, $lines));

        return [
            'meta' => $this->reports->meta($range),
            'configured' => array_sum(array_column($lines, 'rate')) > 0,
            'net_income_before_tax' => round($netIncomeBeforeTax, 2),
            'taxes' => $lines,
            'totals' => [
                'expense_taxes' => round($expenseTaxes, 2),
                'pass_through_taxes' => round($passThrough, 2),
                'net_income_after_tax' => round($netIncomeBeforeTax - $expenseTaxes, 2),
            ],
            'notes' => [
                'These are estimates from the rates in config/finance.php (all off until set). Confirm rates, bases and which taxes apply with your accountant or tax adviser.',
                'expense taxes reduce net income after tax. pass_through taxes are collected or withheld and passed on, so they do not.',
                'Income tax is worked out on net income before tax less the expense taxes on stakes, winnings and revenue, and is never below zero.',
                'stakes and winnings come from customer wallet ledger entries, so they only cover the period since the ledger began.',
            ],
        ];
    }

    /**
     * @param  array{label: string, base: string, kind: string, rate: float|int}  $tax
     * @return array{label: string, base: string, kind: string, rate: float, base_amount: float, estimated_amount: float}
     */
    private function line(array $tax, float $baseAmount): array
    {
        $rate = (float) $tax['rate'];

        return [
            'label' => $tax['label'],
            'base' => $tax['base'],
            'kind' => $tax['kind'],
            'rate' => $rate,
            'base_amount' => round($baseAmount, 2),
            'estimated_amount' => round($baseAmount * $rate, 2),
        ];
    }
}
