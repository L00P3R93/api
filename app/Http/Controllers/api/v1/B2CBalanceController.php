<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\B2CService;
use Illuminate\Http\Request;

class B2CBalanceController extends Controller
{
    public function __construct(
        private B2CService $b2cService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $this->b2cService->processB2CBalance($request->all());
    }
}
