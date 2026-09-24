<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\ReferralWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReferralB2CResultController extends Controller
{
    public function __construct(private ReferralWithdrawalService $withdrawals) {}

    /**
     * Safaricom's B2C result for a referral payout (referral shortcode only).
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->withdrawals->handleResult($request->all());
        } catch (\Exception $e) {
            Log::channel('mpesa')->error('MPESA Referral B2C Result Error: ', ['error' => $e->getMessage()]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
