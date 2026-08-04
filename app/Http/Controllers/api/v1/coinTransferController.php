<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Coin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class coinTransferController extends Controller {
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier) {
        try{
            $coinWallet = Coin::find($encryptedIdentifier);
            if (!$coinWallet) { return response()->json(["message" => "Coin Wallet not found"], 404);}
            $validated = $request->validate([
                'coins' => 'required|numeric',
                'coin_wallet_id' => 'nullable|integer',
            ]);
            $coinsToTransfer = $validated['coins'];

            // Check sender coin balance
            if ($coinWallet->coins < $coinsToTransfer) {
                return response()->json(["message" => "Insufficient coins in Sender Wallet Coin"], 400);
            }

            // Determine the receiver coin wallet
            $receiverCoinWallet = Coin::find($validated['coin_wallet_id'] ?? 1);
            if (!$receiverCoinWallet) {
                return response()->json(["message" => "Receiver coin wallet not found"], 404);
            }

            // Perform the transaction
            $coinWallet->coins -= $coinsToTransfer;
            $coinWallet->save();

            $receiverCoinWallet->coins += $coinsToTransfer;
            $receiverCoinWallet->save();

            return response()->json(["message" => "Transfer successful"], 200);
        }catch (\Exception $e){
            Log::error("Coin Transfer Error", [
                'error' => $e->getMessage(),
                'encryptedIdentifier' => $encryptedIdentifier,
                'request' => $request->all(),
            ]);
            return response()->json(["message" => "Coin Transfer Failed"], 500);
        }
    }
}
