<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinanceListRequest;
use App\Http\Requests\StoreExciseDutyRemittanceRequest;
use App\Http\Requests\VoidExciseDutyRemittanceRequest;
use App\Models\ExciseDutyRemittance;
use App\Services\ExciseDutyReportService;
use App\Services\FinanceDateRange;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;

/**
 * Excise duty on deposits: what was charged, the monthly returns, and payments to KRA.
 */
class ExciseDutyController extends Controller
{
    public function __construct(
        private ExciseDutyReportService $exciseDuty,
        private FinanceReportService $reports,
    ) {}

    public function summary(FinanceListRequest $request): JsonResponse
    {
        $range = $request->dateRange();

        return $this->respond($range, $this->exciseDuty->summary($range));
    }

    public function charges(FinanceListRequest $request): JsonResponse
    {
        $range = $request->dateRange();
        $listing = $this->exciseDuty->charges($range, $request->filters());

        return $this->respond($range, ['summary' => $listing->summary()] + $listing->paginate($request->page(), $request->perPage()));
    }

    public function returns(FinanceListRequest $request): JsonResponse
    {
        $range = $request->dateRange();

        return $this->respond($range, [
            'filing_day' => (int) config('finance.excise_duty.filing_day'),
            'payable' => $this->exciseDuty->payable(),
            'items' => $this->exciseDuty->returns($range),
        ]);
    }

    public function remittances(FinanceListRequest $request): JsonResponse
    {
        $range = $request->dateRange();
        $listing = $this->exciseDuty->remittances($range, $request->filters());

        return $this->respond($range, ['summary' => $listing->summary()] + $listing->paginate($request->page(), $request->perPage()));
    }

    /**
     * Record a payment to KRA for a period. Every unremitted charge in the period is attached to it.
     */
    public function store(StoreExciseDutyRemittanceRequest $request): JsonResponse
    {
        $remittance = $this->exciseDuty->recordRemittance($request->validated(), $this->actorFor($request));

        if ($remittance === null) {
            return response()->json(['success' => false, 'message' => 'There is no unremitted excise duty in that period.'], 422);
        }

        return response()->json(['success' => true, 'data' => $this->exciseDuty->presentRemittance($remittance)], 201);
    }

    /**
     * Void a remittance entered in error. It is kept and flagged, and its charges become unremitted again.
     */
    public function void(VoidExciseDutyRemittanceRequest $request, string $encryptedIdentifier): JsonResponse
    {
        $remittance = ExciseDutyRemittance::find($encryptedIdentifier);

        if (! $remittance) {
            return response()->json(['success' => false, 'message' => 'Remittance not found'], 404);
        }

        $voided = $this->exciseDuty->voidRemittance($remittance->id, $request->validated('reason'), $this->actorFor($request));

        if ($voided === null) {
            return response()->json(['success' => false, 'message' => 'Remittance is already voided'], 409);
        }

        return response()->json(['success' => true, 'data' => $this->exciseDuty->presentRemittance($voided)]);
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
