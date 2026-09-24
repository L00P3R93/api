<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Http\Request;

class ReferralB2CBalanceTimeoutController extends Controller
{
    public function __construct(private BalanceService $balanceService) {}

    public function __invoke(Request $request): void
    {
        $this->balanceService->processBalanceTimeout('referral_b2c', $request->all());
    }
}
