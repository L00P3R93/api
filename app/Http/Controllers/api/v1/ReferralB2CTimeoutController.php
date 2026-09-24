<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReferralB2CTimeoutController extends Controller
{
    /**
     * Safaricom timed out a referral payout request. The withdrawal is left as it is: a timeout does not
     * mean the payout failed, so it is never refunded here.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::channel('mpesa')->info('MPESA Referral B2C Timeout: ', $request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
