<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\Wallet;

class TransactionController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return TransactionResource::collection(Transaction::with('wallet.customer')->get());
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $transaction = Transaction::where('id', $encryptedIdentifier)->first();
        if (!$transaction) { return response()->json(["message" => "Transaction not found"], 404);}
        return TransactionResource::make($transaction);
    }
}
