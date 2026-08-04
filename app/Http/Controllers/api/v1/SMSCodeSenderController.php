<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SMSCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SMSCodeSenderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request){
        try {
            $phoneNo = $request->phone_no;
            if(!isValidPhoneNo($phoneNo))  return response()->json([ "status" => "Phone number is invalid" ], 400);
            $OneTimeCode = SMSCode::generateCode();
            $SMSBody = "Your one time code is: $OneTimeCode";
            $sms = SMSCode::create([
                'phone_no' => $phoneNo,
                'code' => $OneTimeCode,
                'body' => $SMSBody,
                'status' => 1,
            ]);
            // TODO: Integrate with SMS Service
            $smsSent = true; // for test
            if($smsSent){
                $sms->status = 2;
                $sms->save();
                return response()->json([ "status" => "Success" ], 200);
            }
        } catch (\Exception $e) {
            Log::error('Phone Verification Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "status" => "Error verifying phone number" ], 500);
        }
    }
}
