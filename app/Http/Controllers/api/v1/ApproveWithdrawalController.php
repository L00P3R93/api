<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Withdraw;
use Illuminate\Http\Request;

class ApproveWithdrawalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke($identifier){
        $transaction = Transaction::find($identifier);
        if(!$transaction) return response()->json([ "message" => "Transaction not found" ], 404);
        // Check if transaction is a withdrawal and status is pending
        if($transaction->payment_type == Withdraw::class && $transaction->status == 1) {
            // Create a new outgoing payment entry with status 1 (Pending Disbursement)
            $withdrawal = Withdraw::create([
                'transaction_id' => $transaction->id,
                'amount' => $transaction->amount,
            ]);

            // Update transaction details and transaction status.
            $transaction->payment_id = $withdrawal->id;
            $transaction->status = '2'; // Approved
            $transaction->save();

            // Return a success response
            return response()->json([
                'message' => 'Withdrawal request approved successfully.',
                'withdraw' => $withdrawal,
                'transaction' => $transaction
            ], 200);
        }

        // If the transaction is not a valid withdrawal or is already processed
        return response()->json([
            'message' => 'Invalid or already processed withdrawal request.'
        ], 400);
    }
}
