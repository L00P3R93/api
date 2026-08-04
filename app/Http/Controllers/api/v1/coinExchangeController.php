<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Coin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class coinExchangeController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier){
        try{
            $coinWallet = Coin::find($encryptedIdentifier);
            if (!$coinWallet) { return response()->json(["message" => "Coin Wallet not found"], 404);}
            $validated = $request->validate([
                'coins' => 'nullable|numeric',
            ]);
            $coinsToExchange = $validated['coins'] ?? $coinWallet->coins;
            if($coinWallet->coins < $coinsToExchange) return response()->json(["message" => "Insufficient coins in Coin Wallet"], 400);

            $wallet = $coinWallet->customer->wallet;
            if(!$wallet) return response()->json(["message" => "Wallet not found"], 404);

            // Exchange Rate (Kes 1 = 25 Coin)
            $exchangeRate = 0.04;
            $amountToExchange = $coinsToExchange * $exchangeRate;

            //Deduct from customer's coin wallet
            $coinWallet->coins -= $coinsToExchange;
            $coinWallet->save();

            // Update amount from customer's main wallet
            $wallet->balance += $amountToExchange;
            $wallet->save();

            return response()->json([ "message" => "Coins exchanged successfully" ], 200);
        }catch(\Exception $e){
            Log::error("Coin Exchange Error", [
                'error' => $e->getMessage(),
                'encryptedIdentifier' => $encryptedIdentifier,
                'request' => $request->all(),
            ]);
            return response()->json(["message" => "Coin Exchange Failed"], 500);
        }
    }
}
