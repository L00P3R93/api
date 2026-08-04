<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletTransferController extends Controller{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier){
        try{
            $senderWalletId = $encryptedIdentifier;
            $wallet = Wallet::find($senderWalletId);
            if (!$wallet) { return response()->json(["status" => "error", "encryptedIdentifier" => intval($encryptedIdentifier)], 404);}

            $validated = $request->validate([
                'amount' => 'required|numeric',
                'wallet_id' => 'nullable|integer',
            ]);

            $amount = $validated['amount'];

            // Check sender balance
            if ($wallet->balance < $amount) {
                return response()->json(["status" => "error"], 400);
            }

            // Determine the receiver wallet
            $receiverWalletId = $validated['wallet_id'] ?? 1;
            $receiverWallet = Wallet::find($receiverWalletId);
            if (!$receiverWallet) {
                return response()->json(["status" => "error"], 404);
            }

            DB::transaction(function () use ($senderWalletId, $wallet, $receiverWalletId, $receiverWallet, $amount) {
                // Record Transaction
                $walletTransaction = WalletTransaction::create([
                    'transaction_type' => 'w2w',
                    'sender_id' => $senderWalletId,
                    'receiver_id' => $receiverWalletId,
                    'initiator_id' => 1,
                    'amount' => $amount,
                ]);

                // Update the sender's balance
                $wallet->balance -= $amount;
                $wallet->save();

                // Update the receiver's balance
                $receiverWallet->balance += $amount;
                $receiverWallet->save();

                // Update Transaction Status to Completed
                $walletTransaction->status = 2;
                $walletTransaction->save();
            });

            return response()->json(["status" => "success"], 200);
        }catch(\Exception $e){
            Log::error("Wallet Transfer Error", [
                'error' => $e->getMessage(),
                'encryptedIdentifier' => $encryptedIdentifier,
                'request' => $request->all(),
            ]);
            return response()->json(["status" => "error"], 500);
        }
    }
}
