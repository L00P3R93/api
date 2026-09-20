<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceListRequest;
use App\Services\CustomerService;
use App\Services\FinanceDateRange;
use App\Services\FinanceGameReportService;
use App\Services\FinanceLedgerReportService;
use App\Services\FinanceListing;
use App\Services\FinancePaymentReportService;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;

class FinanceDrilldownController extends Controller
{
    /** Days a customer statement or top-customers report covers when no range is given. */
    private const DEFAULT_DAYS = 30;

    public function __construct(
        private FinancePaymentReportService $payments,
        private FinanceLedgerReportService $ledger,
        private FinanceGameReportService $games,
        private FinanceReportService $reports,
        private CustomerService $customers,
    ) {}

    public function deposits(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->payments->deposits($request->dateRange(), $request->filters()));
    }

    public function withdrawals(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->payments->withdrawals($request->dateRange(), $request->filters()));
    }

    public function purchases(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->payments->purchases($request->dateRange(), $request->filters()));
    }

    public function games(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->games->games($request->dateRange(), $request->filters()));
    }

    public function competitions(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->games->competitions($request->dateRange(), $request->filters()));
    }

    public function ledger(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->ledger->ledger($request->dateRange(), $request->filters()));
    }

    public function adjustments(FinanceListRequest $request): JsonResponse
    {
        return $this->paged($request, $this->ledger->adjustments($request->dateRange(), $request->filters()));
    }

    public function topCustomers(FinanceListRequest $request): JsonResponse
    {
        $range = $request->dateRange(self::DEFAULT_DAYS);
        $listing = $this->ledger->topCustomers($range, $request->filters());

        return $this->respond($range, ['summary' => $listing->summary(), 'items' => $listing->all()]);
    }

    public function customerStatement(FinanceListRequest $request, string $encryptedIdentifier): JsonResponse
    {
        $customer = $this->customers->getCustomer($encryptedIdentifier);

        if (! $customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
        }

        $range = $request->dateRange(self::DEFAULT_DAYS);
        $statement = $this->ledger->customerStatement($customer, $range, $request->page(), $request->perPage());

        if ($statement === null) {
            return response()->json(['success' => false, 'message' => 'Customer has no wallet'], 404);
        }

        return $this->respond($range, $statement);
    }

    private function paged(FinanceListRequest $request, FinanceListing $listing): JsonResponse
    {
        return $this->respond($request->dateRange(), ['summary' => $listing->summary()] + $listing->paginate($request->page(), $request->perPage()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(FinanceDateRange $range, array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['generated_at' => now()->toIso8601String(), 'meta' => $this->reports->meta($range)] + $data,
        ]);
    }
}
