<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Mpesa\Init as Mpesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StkLoadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier)
    {
        try {
            $request->validate([
                'amount' => 'required',
                'type' => 'required',
            ]);

            $amount = $request->amount;
            $type = $request->type;
            $coin_value = $request->coin_value;
            $phone_no = $request->phone_no?? null;
            $referral_code = $request->referral_code?? null;

            $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
            if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
            if($type == 'load'){ // Load Customer Wallet
                $bill_ref_no = $customer->account_no."#".$coin_value."#load";
            }elseif ($type == 'gift') { // Bought Gift
                $bill_ref_no = $customer->account_no."#".$amount."#gift";
            }elseif ($type == 'emoji') { // Bought Emoji
                $bill_ref_no = $customer->account_no."#".$amount."#emoji";
            }else{ // Default
                $bill_ref_no = $customer->account_no;
            }
            
            if($referral_code){
                $bill_ref_no .= "#".$referral_code;
            }else{
                if(!empty($customer->referral_code)){
                    $bill_ref_no .= "#".$customer->referral_code;
                }
            }


            $user_params = [
                "Amount" => (float) $amount,
                'AccountReference' => $bill_ref_no,
                "PartyA" => $phone_no ?? $customer->phone_no,
                "PhoneNumber" => $phone_no ?? $customer->phone_no
            ];

            $response_json = Mpesa::stkPush($user_params);
            Log::channel('mpesa')->info('MPESA StkPush Load Request Response: '.$response_json);
            return response()->json(['status'     => 'success'], 201);

        } catch (\Exception $e) {
            // Log and return error
            Log::channel('mpesa')->error('MPESA StkPush Load Response Error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
