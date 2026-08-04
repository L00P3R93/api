<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawResource;
use App\Models\Withdraw;

class WithdrawController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return WithdrawResource::collection(Withdraw::with('transactions')->get());
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier){
        $withdraw = Withdraw::find($encryptedIdentifier);
        if(!$withdraw) return response()->json([ "message" => "Transaction not found" ], 404);
        return WithdrawResource::make($withdraw);
    }
}
