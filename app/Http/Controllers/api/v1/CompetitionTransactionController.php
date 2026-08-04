<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Requests\StoreCompetitionTransactionRequest;
use App\Http\Requests\UpdateCompetitionTransactionRequest;
use App\Http\Resources\CompetitionTransactionResource;
use App\Models\CompetitionTransaction;
use App\Http\Controllers\Controller;
use App\Models\CompetitionWallet;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompetitionTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return CompetitionTransactionResource::collection(CompetitionTransaction::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompetitionTransactionRequest $request) {
        try {
            $competitionWallet = CompetitionWallet::find($request->competition_wallet_id);
            if(!$competitionWallet) { return response()->json(['status' => "Competition Wallet Not Found!"], 404); }
            if($competitionWallet->customer_id != $request->customer_id) { return response()->json(['status' => "Customer ID do not match"], 400); }
            $customer = Customer::with('wallet')->find($request->customer_id); // Eager load the 'wallet' relationship
            // Customer doesn't exist, no wallet, or insufficient balance
            if (!$customer || !$customer->wallet || $customer->wallet->balance < $request->amount) { return response()->json(['error' => 'Insufficient balance or Wallet not found.'], 400); }
            $wallet = $customer->wallet;
            DB::transaction(function () use ($competitionWallet, $wallet, $request) {
                CompetitionTransaction::create($request->validated());
                $totalBalance = $request->amount;
                $competitionWallet = CompetitionWallet::find($request->competition_wallet_id);
                $competitionType = $competitionWallet->game_type;
                // Define shares by competition type
                // Tournament (competitionType == 1) 15% to House, Rest to Customer
                // Jackpot (competitionType == 2) 20% to House, Rest to Customer
                $houseCompetitionShare = $playerCompetitionShare = 0;
                if ($competitionType == 1) {
                    $houseCompetitionShare = round($totalBalance * 0.20, 2);
                    $playerCompetitionShare = $totalBalance - $houseCompetitionShare;
                } elseif ($competitionType == 2) {
                    $houseCompetitionShare = round($totalBalance * 0.20, 2);
                    $playerCompetitionShare = $totalBalance - $houseCompetitionShare;
                }

                // Deduct Money from Customer Wallet
                $wallet->balance -= $request->amount;
                $wallet->save();

                // Increase Customer Competition Wallet Level
                // Load Competition Wallet with 85% deposit
                $competitionWallet->balance += $playerCompetitionShare;
                $competitionWallet->level += 1;
                $competitionWallet->save();

                // Load House Wallet with 15% of Deposit
                // Update house wallet with winnings
                $houseWallet = Wallet::find(1);
                if($houseWallet) {
                    $houseWallet->balance += $houseCompetitionShare;
                    $houseWallet->save();
                }

                // Record House Wallet Transfer
                WalletTransaction::create([
                    'transaction_type' => 'c2w',
                    'sender_id' => $competitionWallet->id,
                    'receiver_id' => 1,
                    'initiator_id' => 1,
                    'amount' => $houseCompetitionShare,
                ]);
            });
            return response()->json([ "status" => "Success" ], 201);
        } catch (\Exception $e) {
            Log::error('Create Competition Transaction Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "status" => "Error creating Competition Transaction" ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $competitionTransaction = CompetitionTransaction::find($encryptedIdentifier);
        if(!$competitionTransaction) { return response()->json([ "status" => "Competition Transaction not found" ], 404); }
        return CompetitionTransactionResource::make($competitionTransaction);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompetitionTransactionRequest $request, $encryptedIdentifier) {
        $competitionTransaction  = CompetitionTransaction::find($encryptedIdentifier);
        if(!$competitionTransaction) { return response()->json([ "status" => "Competition Transaction not found" ], 404); }
        $competitionTransaction->update($request->validated());
        return CompetitionTransactionResource::make($competitionTransaction);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        $competitionTransaction = CompetitionTransaction::find($encryptedIdentifier);
        if(!$competitionTransaction) { return response()->json([ "status" => "Competition Transaction not found" ], 404); }
        $competitionTransaction->delete();
        return response()->json([ "status" => "Success" ], 200);
    }

    public function validateGameTransaction($customerId, $amount){
        $customer = Customer::with('wallet')->find($customerId); // Eager load the 'wallet' relationship
        if (!$customer || !$customer->wallet || $customer->wallet->balance < $amount) {
            return false; // Customer doesn't exist, no wallet, or insufficient balance
        }
        return true;
    }
}
