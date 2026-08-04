<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Mpesa\Init as Mpesa;

class StkDepositController extends Controller{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $identifier){
        try {
            $customer = Customer::where('id', $identifier)->orWhere('account_no', $identifier)->first();
            if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
            $user_params = [
                "Amount" => intval($request->amount),
                'AccountReference' => $customer->id_no,
                "PartyA" => $customer->phone_no,
                "PhoneNumber" => $customer->phone_no
            ];
    
            $response_json = Mpesa::stkPush($user_params);
            Log::channel('mpesa')->info('MPESA StkPush Request Response: '.$response_json);
            return response()->json(['status'     => 'success'], 201);
            
        } catch (\Exception $e) {
            // Log and return error
            Log::error('MPESA StkPush Response Error', ['error' => $e->getMessage()]);
            return response()->json(['status'     => $e->getMessage()], 500);
        }
    }
}
