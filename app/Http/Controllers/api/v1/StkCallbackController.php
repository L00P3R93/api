<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StkCallbackController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request){
        try {
            // Log incoming request
            Log::channel('mpesa')->info('MPESA StkPush Request Received', $request->all());
        } catch (\Exception $e) {
            // Log and return error
            Log::error('MPESA StkPush Request Error', ['error' => $e->getMessage()]);

        }
    }
}
