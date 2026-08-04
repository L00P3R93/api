<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SMSCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PhoneVerifyController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier) {
        try {
            $request->validate([ 'code' => 'required|string|min:6|max:6']);
            $customer = Customer::find($encryptedIdentifier);
            if(!$customer) return response()->json(['status' => 'Customer not found'], 404);
            // Get SMSCode sent and pending verification (status==2)
            $codeCheck = SMSCode::where('code', $request->code)->where('status', 2)->first();
            if($codeCheck) {
                // If code exists, update status to 3 (verified & used).
                $codeCheck->status = 3;
                $codeCheck->save();
                // Update Customer Phone Verified
                $customer->phone_verified_at = now();
                $customer->save();
                return response()->json(['status' => 'Success']);
            }else{ return response()->json(['status' => 'Invalid Code'], 400); }
        } catch (\Exception $e) {
            Log::error('Phone Verification Error: ', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'Error verifying phone number'], 500);
        }
    }
}
