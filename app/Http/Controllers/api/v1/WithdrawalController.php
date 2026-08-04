<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Withdraw;
use App\Mpesa\Init as Mpesa;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WithdrawalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $identifier): JsonResponse {
        try {
            // Validate request input
            $validatedData = $request->validate([ 'amount' => 'required|numeric|min:1', ]);
            $amount = $validatedData['amount'];

            // Find customer by ID or account number
            $customer = Customer::where('id', $identifier)
                ->orWhere('account_no', $identifier)
                ->first();

            if (!$customer) { return response()->json(['message' => 'Customer not found'], 404); }

            // Retrieve the associated wallet
            $wallet = $customer->wallet;
            if (!$wallet) { return response()->json(['message' => 'Wallet not found'], 404); }

            // Check if the wallet has sufficient balance
            if ($wallet->balance < $amount) { return response()->json(['message' => 'Insufficient wallet balance for withdrawal'], 400); }

            // Create a transaction record for the withdrawal
            $transaction = $wallet->transactions()->create([
                'payment_id'   => null,
                'payment_ref'  => null,
                'payment_type' => Withdraw::class,
                'amount'       => $amount,
                'status'       => 1, // Pending
            ]);

            // Create a withdrawal request (status 1 = Pending)
            $withdraw = Withdraw::create([
                'transaction_id' => $transaction->id,
                'amount'         => $amount,
                'disburse'       => 1, // Pending Disbursement
            ]);

            // M-Pesa Disbursement Process
            $phone_no = $customer->phone_no ?? null;
            if (!$phone_no) { return response()->json(['message' => 'Customer phone number not found'], 400); }

            $userParams = [
                'Amount'  => $withdraw->amount,
                'PartyB'  => $phone_no,
                'Remarks' => 'Business Payment',
            ];

            $response_json = Mpesa::b2c($userParams);
            Log::channel('mpesa')->info('MPESA B2C Response: ' . $response_json);

            $response = json_decode($response_json, true);
            
            //var_export($response);
            
            if ($response && isset($response['ResponseCode']) && $response['ResponseCode'] == 0) {
                // Deduct amount from wallet using bcsub() for decimal precision
                $wallet->balance -= $amount;
                $wallet->save();

                // Update transaction and withdrawal details
                $transaction->update([
                    'payment_id' => $withdraw->id,
                    'payment_ref' => $response['ConversationID'],
                    'status'     => 2, // Approved
                ]);

                $withdraw->update([
                    'disburse' => 2, // Success
                    'receipt'  => $response['ConversationID'] ?? 'N/A',
                ]);
                
                return response()->json([
                    'status'   => 'success',
                ], 201);
            } else {
                // Update transaction and withdrawal details
                $transaction->update([
                    'payment_id' => $withdraw->id,
                    'status'     => 3, // Failed
                ]);

                $withdraw->update([
                    'disburse'       => 3, // Failed
                    'receipt'        => $response['ResponseCode'] ?? null,
                    'error_message'  => $response['ResponseDescription'] ?? 'Unknown Error',
                ]);
                
                return response()->json(['status' => 'Unknown Error',], 500);
            }
        }
        catch (ValidationException $e) {
            return response()->json([
                'status'  => $e->errors(),
            ], 422);
        }
        catch (\Exception $e) {
            // Log the error for debugging
            Log::channel('mpesa')->error('Withdraw Request Error: ' . $e->getMessage(), [
                'identifier'   => $identifier,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'status'   => $e->getMessage(),
            ], 500);
        }
    }
}
