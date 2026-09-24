<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Http\Request;

class ReferralB2CBalanceController extends Controller
{
    public function __construct(private BalanceService $balanceService) {}

    /**
     * Safaricom's account balance result for the referral shortcode, stored as type `referral_b2c`.
     */
    public function __invoke(Request $request): void
    {
        $this->balanceService->processBalanceResult('referral_b2c', $request->all());
    }
}
