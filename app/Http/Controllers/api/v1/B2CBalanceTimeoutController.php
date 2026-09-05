<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class B2CBalanceTimeoutController extends Controller
{
    public function __construct(
        private BalanceService $balanceService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $this->balanceService->processBalanceTimeout('b2c', $request->all());
        } catch (\Exception $e) {
            Log::error('MPESA B2C Balance Timeout Error: ', ['error' => $e->getMessage()]);
        }
    }
}
