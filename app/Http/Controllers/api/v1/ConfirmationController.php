<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepositRequest;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfirmationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request) {
        try {
            // Log incoming request
            Log::channel('mpesa')->info('MPESA Confirmation Received', $request->all());

            // Map request data to Deposit
            $depositData = [
                'trans_id' => $request->input('TransID'),
                'trans_type' => $request->input('TransactionType'),
                'trans_time' => date('Y-m-d H:i:s', strtotime($request->input('TransTime'))),
                'trans_amount' => $request->input('TransAmount'),
                'short_code' => $request->input('BusinessShortCode'),
                'bill_ref_no' => $request->input('BillRefNumber'),
                'msisdn' => $request->input('MSISDN'),
                'name' => trim(
                    $request->input('FirstName') . ' ' .
                    $request->input('MiddleName') . ' ' .
                    $request->input('LastName')
                ),
            ];

            // Create Deposit
            $deposit = Deposit::create($depositData);

            // Check if bill_ref_no contains #
            $billRefParts = explode('#', $deposit->bill_ref_no);
            $rawAccountNo = $billRefParts[0]; // Get the account number part
            $coinValue = $billRefParts[1] ?? null; // Get the coin value OR amount
            $type = $billRefParts[2] ?? null; // Get the type
            $referral_code = $billRefParts[3] ?? null; // Get the referral code

            // Normalizing account number input
            // $rawAccountNo = $deposit->bill_ref_no;
            $normalizedAccountNo = null;

            if (preg_match('/^2547\d{8}$/', $rawAccountNo)) {
                $normalizedAccountNo = substr($rawAccountNo, 3); // Remove '254'
            } elseif (preg_match('/^07\d{8}$/', $rawAccountNo)) {
                $normalizedAccountNo = substr($rawAccountNo, 1); // Remove leading '0'
            } else {
                $normalizedAccountNo = $rawAccountNo;
            }

            // Retrieve customer
            //$customer = Customer::where('id_no', $deposit->bill_ref_no)->first();
            $customer = Customer::where('id_no', $rawAccountNo)->orWhere('account_no', $normalizedAccountNo)->first();

            if (!$customer) {
                $deposit->update(['status' => 0]); // Suspense
                Log::channel('mpesa')->error('MPESA Confirmation Received [Invalid Customer]:', $request->all());
            }

            if ($type == 'load') {
                // Retrieve or create wallet
                $wallet = Wallet::firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['balance' => 0]
                );

                // Update wallet balance with coin_value
                $amountToAdd = (float)$coinValue;
                $wallet->balance += $amountToAdd;
                $wallet->save();

                // Record transaction
                $wallet->transactions()->create([
                    'payment_id' => $deposit->id,
                    'payment_ref' => $deposit->trans_id,
                    'payment_type' => Deposit::class,
                    'amount' => $amountToAdd, // Use the same amount that was added to the wallet
                    'status' => 2,
                ]);
                // Record Purchase
                $customer->purchases()->create([
                    'deposit_id' => $deposit->id,
                    'purchase_type' => 'load',
                    'amount' => $deposit->trans_amount,
                    'value' => $amountToAdd,
                    'referral_code' => $referral_code
                ]);
            }
            elseif ($type == 'gift' || $type == 'emoji') {
                // No Wallet Transaction, this is a direct purchase
                $amountToAdd = (float)$coinValue;
                // Record Purchase
                $customer->purchases()->create([
                    'deposit_id' => $deposit->id,
                    'purchase_type' => $type,
                    'amount' => $deposit->trans_amount,
                    'value' => $amountToAdd,
                    'referral_code' => $referral_code
                ]);
            } 
            else {
                // Retrieve or create wallet
                $wallet = Wallet::firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['balance' => 0]
                );

                // Update wallet balance trans_amount
                $amountToAdd = $deposit->trans_amount;
                $wallet->balance += $amountToAdd;
                $wallet->save();

                // Record transaction
                $wallet->transactions()->create([
                    'payment_id' => $deposit->id,
                    'payment_ref' => $deposit->trans_id,
                    'payment_type' => Deposit::class,
                    'amount' => $amountToAdd, // Use the same amount that was added to the wallet
                    'status' => 2,
                ]);
            }

            // Mark deposit as completed
            $deposit->update(['status' => 2]);

            return response()->json([
                "ResultCode" => "0",
                "ResultDesc" => "Accepted",
            ], 201);
        } catch (\Exception $e) {
            // Log and return error
            Log::channel('mpesa')->error('MPESA Confirmation Received [Error]: ', ['error' => $e->getMessage(), 'request' => $request->all()]);
            return response()->json([
                "ResultCode" => "C2B00016",
                "ResultDesc" => $e->getMessage()
            ], 500);
        }
    }
}
