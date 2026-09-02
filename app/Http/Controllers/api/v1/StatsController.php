<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(private StatsService $statsService) {}

    public function customerStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->customerStats(),
        ]);
    }

    public function customerReferralStats(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->customerReferralStats($request->referral_code),
        ]);
    }

    public function incomeStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->incomeStats(),
        ]);
    }

    public function dailyIncomeStats30Days(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->dailyIncomeStats30Days(),
        ]);
    }

    public function purchaseStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->purchaseStats(),
        ]);
    }

    public function purchaseReferralsStats(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->purchaseReferralsStats($request->referral_code),
        ]);
    }

    public function playedStats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->playedStats(),
        ]);
    }

    public function playedByPlayerStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->statsService->playedByPlayerStats(
                $validated['customer_id'],
                $validated['start_date'],
                $validated['end_date'],
            ),
        ]);
    }

    public function retentionRates(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->statsService->retentionRates(),
        ]);
    }
}
