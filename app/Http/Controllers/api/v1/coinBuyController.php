<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Coin;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class coinBuyController extends Controller {
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier){
        $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
        if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);

        $wallet = $customer->wallet;
        if (!$wallet) return response()->json([ "message" => "Wallet not found" ], 404);

        $validated = $request->validate([
            'amount' => 'required|numeric',
        ]);
        $amount = $validated['amount'];

        // Check sender balance
        if ($wallet->balance < $amount) {
            return response()->json(["message" => "Insufficient balance in Wallet"], 400);
        }

        //Exchange rate (Kes 1 = 25 Coin)
        $exchangeRate = 0.04;
        $coinsToBuy = floor($amount / $exchangeRate);

        try{
            $coinWallet = null;
            //Perform DB operations within a transaction
            DB::transaction(function () use ($wallet, $customer, $coinsToBuy, $amount, $exchangeRate, &$coinWallet){
                // Deduct amount from customer's main wallet
                $wallet->balance -= $amount;
                $wallet->save();

                // Check is coin wallet exists
                $coinWallet = Coin::firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['coins' => 0],
                    ['status' => 1]
                );

                // Update coin wallet with new coins
                $coinWallet->coins += $coinsToBuy;
                $coinWallet->save();
            });

            return response()->json([
                "message" => "Coins purchased successfully",
                "wallet_balance" => $wallet->balance,
                "coins" => $coinWallet->coins
            ], 200);
        }catch (\Exception $e) {
            Log::error("Coin Purchase Error: " . $e->getMessage());
            return response()->json(["message" => "An error occurred during the transaction"], 500);
        }
    }
}
