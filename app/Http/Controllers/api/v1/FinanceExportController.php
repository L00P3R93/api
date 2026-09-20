<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceListRequest;
use App\Services\FinanceDateRange;
use App\Services\FinanceExpenseService;
use App\Services\FinanceGameReportService;
use App\Services\FinanceLedgerReportService;
use App\Services\FinanceListing;
use App\Services\FinancePaymentReportService;
use App\Services\FinanceReportService;
use App\Services\FinanceTaxService;
use Generator;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceExportController extends Controller
{
    private const REPORTS = [
        'ledger', 'deposits', 'withdrawals', 'purchases', 'adjustments', 'games', 'competitions',
        'customers-top', 'cash-flow', 'income-statement', 'trial-balance', 'expenses', 'taxes',
    ];

    /** Days the top customers report covers when no range is given. */
    private const TOP_CUSTOMERS_DAYS = 30;

    public function __construct(
        private FinancePaymentReportService $payments,
        private FinanceLedgerReportService $ledger,
        private FinanceGameReportService $games,
        private FinanceReportService $reports,
        private FinanceExpenseService $expenses,
        private FinanceTaxService $taxes,
    ) {}

    /**
     * Stream a report as CSV. Takes the same range and filters as the report itself and exports
     * every row, not one page.
     */
    public function __invoke(FinanceListRequest $request, string $report): JsonResponse|StreamedResponse
    {
        if (! in_array($report, self::REPORTS, true)) {
            return response()->json(['success' => false, 'message' => 'Unknown report. Choose one of: '.implode(', ', self::REPORTS)], 404);
        }

        $range = $request->dateRange($report === 'customers-top' ? self::TOP_CUSTOMERS_DAYS : 1);
        $filters = $request->filters();

        [$columns, $rows] = match ($report) {
            'ledger' => $this->fromListing($this->ledger->ledger($range, $filters)),
            'deposits' => $this->fromListing($this->payments->deposits($range, $filters)),
            'withdrawals' => $this->fromListing($this->payments->withdrawals($range, $filters)),
            'purchases' => $this->fromListing($this->payments->purchases($range, $filters)),
            'adjustments' => $this->fromListing($this->ledger->adjustments($range, $filters)),
            'games' => $this->fromListing($this->games->games($range, $filters)),
            'competitions' => $this->fromListing($this->games->competitions($range, $filters)),
            'customers-top' => $this->fromListing($this->ledger->topCustomers($range, $filters)),
            'cash-flow' => $this->cashFlowRows($range),
            'income-statement' => $this->incomeStatementRows($range),
            'trial-balance' => $this->trialBalanceRows($range),
            'expenses' => $this->fromListing($this->expenses->listing($range, $filters)),
            'taxes' => $this->taxRows($range),
        };

        $filename = "finance-{$report}-{$range->from->toDateString()}-{$range->to->toDateString()}.csv";

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 byte order mark so Excel reads names correctly
            fputcsv($out, $columns, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $column) => $this->cell($row[$column] ?? null), $columns), ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: list<string>, 1: iterable<array<string, mixed>>}
     */
    private function fromListing(FinanceListing $listing): array
    {
        return [$listing->columns, $listing->rows()];
    }

    /**
     * @return array{0: list<string>, 1: Generator<int, array<string, mixed>>}
     */
    private function cashFlowRows(FinanceDateRange $range): array
    {
        $columns = ['period', 'wallet_deposit', 'load', 'gift', 'emoji', 'unmatched', 'other', 'cash_in_total', 'paid', 'pending', 'failed', 'net_cash'];

        $rows = (function () use ($range) {
            foreach ($this->reports->cashFlow($range)['series'] as $row) {
                yield ['period' => $row['period']]
                    + $row['cash_in']
                    + ['cash_in_total' => $row['cash_in']['total']]
                    + $row['cash_out']
                    + ['net_cash' => $row['net_cash']];
            }
        })();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<string>, 1: Generator<int, array<string, mixed>>}
     */
    private function incomeStatementRows(FinanceDateRange $range): array
    {
        $columns = ['period', 'games', 'tournaments', 'jackpots', 'competitions_unattributed', 'gift_emoji_sales', 'other', 'total', 'expenses', 'net_income'];

        $rows = (function () use ($range) {
            yield from $this->reports->incomeStatement($range)['series'];
        })();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<string>, 1: Generator<int, array<string, mixed>>}
     */
    private function taxRows(FinanceDateRange $range): array
    {
        $columns = ['tax', 'label', 'base', 'kind', 'rate', 'base_amount', 'estimated_amount'];

        $rows = (function () use ($range) {
            foreach ($this->taxes->estimate($range)['taxes'] as $key => $line) {
                yield ['tax' => $key] + $line;
            }
        })();

        return [$columns, $rows];
    }

    /**
     * @return array{0: list<string>, 1: Generator<int, array<string, mixed>>}
     */
    private function trialBalanceRows(FinanceDateRange $range): array
    {
        $columns = ['account', 'entry_type', 'category', 'unit', 'debit', 'credit', 'entries'];

        $rows = (function () use ($range) {
            yield from $this->reports->trialBalance($range)['lines'];
        })();

        return [$columns, $rows];
    }

    /**
     * One CSV cell. Arrays become JSON, booleans yes/no, and text a spreadsheet would run as a
     * formula is prefixed with an apostrophe so it stays text.
     */
    private function cell(mixed $value): string|int|float
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value) => (string) json_encode($value),
            is_int($value), is_float($value) => $value,
            preg_match('/^[=+\-@\t\r]/', (string) $value) === 1 => "'".$value,
            default => (string) $value,
        };
    }
}
