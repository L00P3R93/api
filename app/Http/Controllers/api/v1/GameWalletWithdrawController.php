<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameTransactionResource;
use App\Models\Customer;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameWalletWithdrawController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier){

        // Validate incoming request
        $request->validate([
            'customer_id' => 'required|string',
        ]);

        try {
            // Fetch the game wallet by ID
            $gameWallet = GameWallet::find($encryptedIdentifier);
            if (!$gameWallet) { return response()->json(["error" => "Game Wallet not found"], 404); }

            // Ensure the wallet is open for withdrawal (status = 1)
            if ($gameWallet->status !== '1') { return response()->json(["error" => "Game Wallet is not open for withdrawal"], 400); }

            // Fetch customer by ID or account number
            $customer = Customer::where('id', $request->customer_id)
                ->orWhere('account_no', $request->customer_id)
                ->first();
            if (!$customer) { return response()->json(["error" => "Customer not found"], 404); }

            // Ensure the customer has a wallet
            $wallet = $customer->wallet;
            if (!$wallet) { return response()->json(["error" => "Customer wallet not found"], 404); }

            // Begin atomic transaction to safely handle funds and records
            DB::transaction(function () use ($gameWallet, $customer, $wallet) {
                $totalBalance = $gameWallet->balance;

                // Define shares: 10% to house, The rest to player. Adjusted to 20%
                $houseShare = ceil($totalBalance * 0.05);
                $playerShare = $totalBalance - $houseShare;

                // Create transaction record for the player's payout
                GameTransaction::create([
                    'game_wallet_id' => $gameWallet->id,
                    'customer_id' => $customer->id,
                    'amount' => $playerShare,
                    'payment_type' => 'payout',
                    'status' => 2, // Paid Out
                ]);

                // Create transaction record for the house's payout
                GameTransaction::create([
                    'game_wallet_id' => $gameWallet->id,
                    'customer_id' => 1, // House as customer
                    'amount' => $houseShare,
                    'payment_type' => 'payout',
                    'status' => 2, // Paid Out
                ]);

                // Update player wallet with the winnings
                $wallet->balance += $playerShare;
                $wallet->save();

                // Update house wallet with their share
                $houseWallet = Wallet::find(1);
                if ($houseWallet) {
                    $houseWallet->balance += $houseShare;
                    $houseWallet->save();
                }

                // Close the game wallet to prevent future withdrawals
                $gameWallet->status = 3; // Balance Paid Out, Wallet Closed
                $gameWallet->save();
            });

            // Respond with success
            return response()->json(['status' => 'success'], 201);

        } catch (\Exception $e) {
            // Log the error for debugging and support
            Log::error('Game Wallet Withdrawal Error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
                'game_wallet_id' => $gameWallet->id ?? null,
            ]);

            // Respond with a generic error message
            return response()->json([
                "error" => "An error occurred during the withdrawal process."
            ], 500);
        }
    }
}
