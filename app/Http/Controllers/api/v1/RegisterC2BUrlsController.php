<?php

namespace App\Http\Controllers\api\v1;

use App\Exceptions\MpesaApiException;
use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use Illuminate\Support\Facades\Log;

class RegisterC2BUrlsController extends Controller
{
    public function __construct(
        private MpesaService $mpesaService
    ) {}

    /**
     * Handle the incoming request.
     */
    public function __invoke()
    {
        try {
            $response = $this->mpesaService->c2bRegister();
            Log::channel('mpesa')->info('MPESA C2B Register Response:', $response);

            return response()->json($response);
        } catch (MpesaApiException $e) {
            Log::channel('mpesa')->error('MPESA C2B Register Error: '.$e->getMessage());

            return response()->json(['message' => 'Failed to register C2B URLs'], 500);
        }
    }
}
