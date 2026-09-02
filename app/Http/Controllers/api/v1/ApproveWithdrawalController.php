<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;

class ApproveWithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalService $withdrawalService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke($identifier)
    {
        $result = $this->withdrawalService->approveWithdrawal($identifier);

        $statusCode = $result['status_code'] ?? 500;

        return response()->json([
            'message' => $result['message'],
            'withdraw' => $result['withdraw'] ?? null,
            'transaction' => $result['transaction'] ?? null,
        ], $statusCode);
    }
}
