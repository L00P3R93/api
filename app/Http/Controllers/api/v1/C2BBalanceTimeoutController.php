<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class C2BBalanceTimeoutController extends Controller
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
            $this->balanceService->processBalanceTimeout('c2b', $request->all());
        } catch (\Exception $e) {
            Log::error('MPESA C2B Balance Timeout Error: ', ['error' => $e->getMessage()]);
        }
    }
}
