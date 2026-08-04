<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompetitionWalletWithdrawController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier) {
        // Validate Incoming Request
        $request->validate([
            'customer_id' => 'required|string',
        ]);
        try {
            // Fetch the competition wallet by ID
            $competitionWallet = CompetitionWallet::find($encryptedIdentifier);
            if (!$competitionWallet) { return response()->json(["status" => "Competition Wallet not found"], 404); }
            $competitionType = $competitionWallet->game_type;

            // Ensure the wallet is open for withdrawal (status = 1)
            if ($competitionWallet->status !== 1) { return response()->json(["status" => "Competition Wallet is not open for withdrawal"], 400); }

            // Fetch customer by ID
            $customer = Customer::find($request->customer_id);
            if (!$customer) { return response()->json(["status" => "Customer not found"], 404); }

            // Ensure the customer has a wallet
            $wallet = $customer->wallet;
            if (!$wallet) { return response()->json(["status" => "Customer wallet not found"], 404); }

            // Begin atomic transaction to safely handle funds and records
            DB::transaction(function () use ($competitionWallet, $customer, $wallet) {
                $totalBalance = $competitionWallet->balance;

                // Create a withdrawal record for the customer
                CompetitionTransaction::create([
                    'competition_wallet_id' => $competitionWallet->id,
                    'amount' => $totalBalance,
                    'payment_type' => 'payout',
                    'level' => $competitionWallet->level,
                    'status' => 2, // Paid Out
                ]);

                // Update player wallet with winnings
                $wallet->balance += $totalBalance;
                $wallet->save();

                // Update competition wallet status to closed
                $competitionWallet->status = 3; // Balance Withdrawn, Wallet Closed
                $competitionWallet->save();
            });

            return response()->json(["status" => "success"], 200);
        } catch (\Exception $e) {
            // Log the error for debugging and support
            Log::error('Competition Wallet Withdrawal Error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
                'competition_wallet_id' => $competitionWallet->id ?? null,
            ]);

            // Respond with a generic error message
            return response()->json([
                "error" => "An error occurred during the withdrawal process."
            ], 500);
        }
    }
}
