<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\B2CService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class B2CResultController extends Controller
{
    public function __construct(
        private B2CService $b2cService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $this->b2cService->processB2CResult($request->all());
        } catch (\Exception $e) {
            Log::error('MPESA B2C Error: ', ['error' => $e->getMessage()]);
        }
    }
}
