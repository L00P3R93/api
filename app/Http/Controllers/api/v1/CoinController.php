<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoinRequest;
use App\Http\Requests\UpdateCoinRequest;
use App\Http\Resources\CoinResource;
use App\Models\Coin;
use Illuminate\Http\Request;

class CoinController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index(){
        return CoinResource::collection(Coin::with('customer')->get());
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier){
        $coinWallet = Coin::find($encryptedIdentifier);
        if(!$coinWallet) return response()->json([ "message" => "Coin Wallet not found" ], 404);
        return CoinResource::make($coinWallet);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCoinRequest $request, $encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $coinWallet = Coin::find($encryptedIdentifier);
        if (!$coinWallet) { return response()->json(["message" => "Coin Wallet not found"], 404);}
        $coinWallet->update($request->validated());
        return CoinResource::make($coinWallet);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $coinWallet = Coin::find($encryptedIdentifier);
        if (!$coinWallet) { return response()->json(["message" => "Coin Wallet not found"], 404);}
        $coinWallet->delete();
        return response()->json([ "message" => "Coin Wallet deleted successfully" ], 200);
    }
}
