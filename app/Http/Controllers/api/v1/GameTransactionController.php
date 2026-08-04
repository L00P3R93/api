<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GameTransaction;
use App\Models\GameWallet;

use App\Http\Resources\GameTransactionResource;
use App\Http\Requests\StoreGameTransactionRequest;
use Illuminate\Support\Facades\DB;

class GameTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(){
        return GameTransactionResource::collection(GameTransaction::with(['gameWallet'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGameTransactionRequest $request) {
        try {
            $gameWallet = GameWallet::find($request->game_wallet_id);
            if(!$gameWallet) { return response()->json(['status' => "Game Wallet Not Found"], 404); }
            $customer = Customer::with('wallet')->find($request->customer_id); // Eager load the 'wallet' relationship
            // Customer doesn't exist, no wallet, or insufficient balance
            if (!$customer || !$customer->wallet || $customer->wallet->balance < $request->amount) { return response()->json(['error' => 'Insufficient balance or Wallet not found.'], 400); }
            $wallet  = $customer->wallet;
            DB::transaction(function () use ($gameWallet, $wallet, $request) {
                // Record Transaction
                GameTransaction::create($request->validated());

                // Update GameWallet
                $gameWallet->balance += $request->amount;
                $gameWallet->save();

                // Deduct From Customer Wallet
                $wallet->balance -= $request->amount;
                $wallet->save();
            });
            return response()->json(['status' => 'success'], 201);
        } catch (\Exception $e) {}

    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $gameTransaction = GameTransaction::where('id', $encryptedIdentifier)->first();
        if (!$gameTransaction) { return response()->json(["message" => "Game Transaction not found"], 404);}
        return GameTransactionResource::make($gameTransaction);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreGameTransactionRequest $request, $encryptedIdentifier) {
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $gameTransaction = GameTransaction::where('id', $encryptedIdentifier)->first();
        if (!$gameTransaction) { return response()->json(["error" => "Game Transaction not found"], 404);}
        $gameTransaction->update($request->validated());
        //return GameTransactionResource::make($gameTransaction);
        return response()->json(['status' => 'success'], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier)
    {
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $gameTransaction = GameTransaction::where('id', $encryptedIdentifier)->first();
        if (!$gameTransaction) {
            return response()->json(["message" => "Game Transaction not found"], 404);
        }
        $gameTransaction->delete();
        return response()->json(["message" => "Customer deleted successfully"], 200);
    }
}
