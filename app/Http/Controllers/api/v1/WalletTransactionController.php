<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalletTransactionRequest;
use App\Http\Requests\UpdateWalletTransactionRequest;
use App\Http\Resources\WalletTransactionResource;
use App\Models\WalletTransaction;

class WalletTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return WalletTransactionResource::collection(WalletTransaction::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWalletTransactionRequest $request) {
        WalletTransaction::create($request->validated());
        return response()->json(['status' => 'success'], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $walletTransaction = WalletTransaction::find($encryptedIdentifier);
        if (!$walletTransaction) { return response()->json(["message" => "Wallet Transaction not found"], 404);}
        return WalletTransactionResource::make($walletTransaction);
    }
    
    /**
     * Return the total amount of money deposited today
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function showDailyAmount() {
        $todayTotal = WalletTransaction::where('receiver_id', '1')->whereDate('created_at', date('Y-m-d'))->sum('amount');
        return response()->json(['data' => ['amount' => $todayTotal]]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWalletTransactionRequest $request, $encryptedIdentifier) {
        $walletTransaction = WalletTransaction::find($encryptedIdentifier);
        if (!$walletTransaction) { return response()->json(["message" => "Wallet Transaction not found"], 404);}
        $walletTransaction->update($request->validated());
        return response()->json(['status' => 'Update Success'], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        $walletTransaction = WalletTransaction::find($encryptedIdentifier);
        if (!$walletTransaction) { return response()->json(["message" => "Wallet Transaction not found"], 404);}
        $walletTransaction->delete();
        return response()->json(['status' => 'Delete Success'], 201);
    }
}
