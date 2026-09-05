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
            'Amount' => 10,
            'PartyB' => '254727796831',
            'Remarks' => 'Business Payment',
            'Occasion' => 'Test',
        ]);

        echo json_encode($response);
    }
}
