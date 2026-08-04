<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mpesa\Init as Mpesa;
use Illuminate\Support\Facades\Log;

class RegisterC2BUrlsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(){
        try{
            $response = Mpesa::c2b();
            Log::channel('mpesa')->info('MPESA C2B Register Response: ' . $response);
            return $response;
        }catch(\Exception $e){
            Log::channel('mpesa')->error('MPESA C2B Register Error Response: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to register C2B URLs'], 500);
        }
    }
}
