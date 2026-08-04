<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Requests\StoreCompetitionWalletRequest;
use App\Http\Requests\UpdateCompetitionWalletRequest;
use App\Http\Resources\CompetitionWalletResource;
use App\Models\CompetitionWallet;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CompetitionWalletController extends Controller {
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return CompetitionWalletResource::collection(CompetitionWallet::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompetitionWalletRequest $request) {
        try {
            $competitionWallet = CompetitionWallet::create($request->validated());
            return response()->json([ "status" => "Success", "competition_wallet_id" => $competitionWallet->id ], 201);
        } catch (\Exception $e) {
            Log::error('Create Competition Wallet Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "status" => "Error creating Competition Wallet" ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier) {
        $competitionWallet = CompetitionWallet::where('id', $encryptedIdentifier)->orWhere('competition_id', $encryptedIdentifier)->first();
        if(!$competitionWallet) return response()->json([ "status" => "Competition Wallet not found" ], 404);
        return CompetitionWalletResource::make($competitionWallet);
    }

    /**
     * Display the specified resource.
     */
    public function show_competitions($encryptedIdentifier) {
        $competitionWallets = CompetitionWallet::where('cmp_uid', $encryptedIdentifier);
        return CompetitionWalletResource::collection($competitionWallets);
    }

    public function competition_income(Request $request, $encryptedIdentifier) {
        $incomes = DB::table('competition_wallets as C')
            ->join('wallet_transactions as W', 'W.sender_id', '=', 'C.id')
            ->select('C.jp_rounds', DB::raw('SUM(W.amount) as total_income'))
            ->selectRaw('COUNT(DISTINCT C.id) as games_played')
            ->where('C.game_type', $encryptedIdentifier)
            ->where('C.jp_rounds', '>', 0)
            ->whereBetween('C.created_at', [$request->input('start_date'), $request->input('end_date')])
            ->groupBy('C.jp_rounds')
            ->orderBy('C.jp_rounds')
            ->get();

        return response()->json([ "status" => "Success", "data" => $incomes ]);
    }
    
    public function competition_results($encryptedIdentifier) {
        $results = DB::table('competition_wallets as CW')
            ->join('competition_transactions as CT', 'CT.competition_wallet_id', '=', 'CW.id')
            ->join('wallet_transactions as WT', 'WT.sender_id', '=', 'CW.id')
            ->join('customers as C', 'CW.customer_id', '=', 'C.id')
            ->where('CW.game_type', $encryptedIdentifier)
            ->where('CW.jp_rounds', '>', 0)
            ->whereIn('CT.payment_type', ['win', 'loss'])
            ->select(
                'CW.id',
                'CW.competition_id',
                'CW.cmp_uid',
                'CW.customer_id',
                'C.name',
                'CW.level',
                'CW.jp_rounds',
                'CW.status',
                'CT.payment_type',
                'CT.amount',
                'WT.amount as income',
                'CT.created_at'
            )
            ->get();

        return response()->json([ "status" => "Success", "data" => $results ]);
    }
    
    public function competition_awards($encryptedIdentifier): \Illuminate\Http\JsonResponse
    {
        $sub = DB::table('competition_transactions as CT')
            ->join('competition_wallets as CW', 'CT.competition_wallet_id', '=', 'CW.id')
            ->whereColumn('CW.level', '>=', 'CW.jp_rounds')
            ->where('CW.game_type', $encryptedIdentifier)
            ->where('CT.payment_type', 'win')
            ->select(DB::raw('MAX(CT.id) as latest_transaction_id'))
            ->groupBy('CW.competition_id');

        $results = DB::table('competition_transactions as CT')
            ->join('competition_wallets as CW', 'CT.competition_wallet_id', '=', 'CW.id')
            ->join('customers as C', 'CW.customer_id', '=', 'C.id')
            ->whereColumn('CW.level', '>=', 'CW.jp_rounds')
            ->where('CW.game_type', $encryptedIdentifier)
            ->where('CT.payment_type', 'win')
            ->whereIn('CT.id', $sub)
            ->select(
                'CT.id as transaction_id',
                'CW.competition_id',
                'CW.game_type',
                'C.name',
                'CW.cmp_uid',
                'CW.level',
                'CW.jp_rounds',
                'CT.payment_type',
                'CT.amount',
                'CT.created_at'
            )
            ->orderByDesc('CW.id')
            ->get();

        return response()->json([ "status" => "Success", "data" => $results ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompetitionWalletRequest $request, $encryptedIdentifier) {
        $competitionWallet = CompetitionWallet::find($encryptedIdentifier);
        if(!$competitionWallet) return response()->json([ "status" => "Competition Wallet not found" ], 404);
        $competitionWallet->update($request->validated());
        return CompetitionWalletResource::make($competitionWallet);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        $competitionWallet = CompetitionWallet::find($encryptedIdentifier);
        if(!$competitionWallet) return response()->json([ "status" => "Competition Wallet not found" ], 404);
        $competitionWallet->delete();
        return response()->json([ "status" => "Success" ], 200);
    }
}
