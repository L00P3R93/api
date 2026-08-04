<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return WalletResource::collection(Wallet::with('customer')->get());
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier): WalletResource | JsonResponse {
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $wallet = Wallet::find($encryptedIdentifier);
        if (!$wallet) { return response()->json(["message" => "Wallet not found"], 404);}
        return WalletResource::make($wallet);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWalletRequest $request, $encryptedIdentifier): JsonResponse {
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $wallet = Wallet::find($encryptedIdentifier);
        if (!$wallet) { return response()->json(["message" => "Wallet not found"], 404);}
        $wallet->update($request->validated());
        return response()->json(['status' => 'success'], 201);
    }
    
     public function reduce_balance(Request $request, $encryptedIdentifier): JsonResponse {
        $request->validate([
            'amount' => 'required|numeric',
        ]);
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $wallet = Wallet::query()->find($encryptedIdentifier);
        if (!$wallet) { return response()->json(["message" => "Wallet not found"], 404);}
        if ($wallet->balance < $request->amount) { return response()->json(["message" => "Insufficient balance"], 400);}
        $wallet->balance -= $request->amount;
        $wallet->save();
        return response()->json(['status' => 'success'], 201);
    }

    public function add_balance(Request $request, $encryptedIdentifier): JsonResponse {
        $request->validate([
            'amount' => 'required|numeric',
        ]);
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $wallet = Wallet::query()->find($encryptedIdentifier);
        if (!$wallet) { return response()->json(["message" => "Wallet not found"], 404);}
        $wallet->balance += $request->amount;
        $wallet->save();
        return response()->json(['status' => 'success'], 201);
    }
    
    
    public function update_balance(Request $request, $encryptedIdentifier): JsonResponse {
        $request->validate([
            'amount' => 'required|numeric',
        ]);
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $wallet = Wallet::query()->find($encryptedIdentifier);
        if (!$wallet) { return response()->json(["message" => "Wallet not found"], 404);}
        $wallet->balance += $request->amount;
        $wallet->save();
        return response()->json(['status' => 'success'], 201);
    }
}
