<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request){
        try {
            // Log incoming request
            Log::channel('mpesa')->info('MPESA Validation Received: ', $request->all());
            return response()->json([
                "ResultCode" => "0",
                "ResultDesc" => "Accepted",
            ], 200);
        } catch (\Exception $e) {
            // Log and return error
            Log::channel('mpesa')->error('MPESA Validation Error: ', ['error' => $e->getMessage(), 'request' => $request->all()]);
            return response()->json([
                "ResultCode" => "C2B00016",
                "ResultDesc" => $e->getMessage()
            ], 500);
        }
    }
}
