<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;

class TestController extends Controller
{
    public function __construct(
        private MpesaService $mpesaService
    )
    {}

    public function __invoke()
    {
        $response = $this->mpesaService->b2c([
            'Amount' => 0,
            'PartyB' => '254724574375',
            'Remarks' => 'Business Payment',
            'Occasion' => 'Server Expense',
        ]);

        echo json_encode($response);
    }
}
