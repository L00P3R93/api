<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerifiedController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke($encryptedIdentifier) {
        try {
            $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
            if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
            $customer->email_verified_at = now();
            $customer->save();
            return response()->json([ "status" => "Success" ], 200);
        } catch (\Exception $e) {
            Log::error('Email Verification Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "status" => "Error verifying email" ], 500);
        }

    }
}
