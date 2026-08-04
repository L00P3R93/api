<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGameWalletRequest;
use App\Models\GameWallet;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Http\Resources\GameWalletResource;
use App\Http\Requests\StoreGameWalletRequest;

class GameWalletController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {
        return GameWalletResource::collection(GameWallet::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGameWalletRequest $request) {
        $gameWallet = GameWallet::create($request->validated());
        //return GameWalletResource::make($gameWallet);
        return response()->json(['status' => 'success', 'game_wallet_id' => $gameWallet->id, 'game_type' => $gameWallet->game_type], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $gameWallet = GameWallet::where('id', $encryptedIdentifier)->orWhere('game_id', $encryptedIdentifier)->first();
        if (!$gameWallet) { return response()->json(["message" => "Game Wallet not found"], 404);}
        return GameWalletResource::make($gameWallet);
    }
    
    public function game_income(Request $request){
        $incomes = DB::table('game_wallets as G')
            ->selectSub(function ($query) {
                $query->from('game_transactions')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('game_wallet_id', 'G.id')
                    ->where('payment_type', 'deposit');
            }, 'players')
            ->selectRaw('SUM(T.amount) as total_income')
            ->selectRaw('COUNT(DISTINCT G.id) as games_played')
            ->join('game_transactions as T', 'T.game_wallet_id', '=', 'G.id')
            ->where('T.payment_type', 'payout')
            ->where('T.customer_id', 1)
            ->whereBetween('T.created_at', [$request->input('start_date'), $request->input('end_date')])
            ->groupBy('players')
            ->orderBy('players')
            ->get();

        return response()->json([ "status" => "Success", "data" => $incomes ]);
    }
    
    public function game_results(){
        $rawResults = DB::table('game_wallets as GW')
            ->join('game_transactions AS GT', 'GT.game_wallet_id', '=', 'GW.id')
            ->join('customers AS C', 'GT.customer_id', '=', 'C.id')
            ->where('GT.payment_type', 'payout')
            ->select(
                'GW.id', 'GW.game_id', 'GT.customer_id', 'C.name', 'GT.amount', 'GT.created_at',
                DB::raw("(SELECT COUNT(*) FROM game_transactions WHERE  payment_type = 'deposit' AND game_wallet_id = GW.id) AS players"),
                DB::raw("(SELECT SUM(amount) FROM game_transactions WHERE  payment_type = 'deposit' AND game_wallet_id = GW.id) AS total_bet")
            )->get();

        $grouped = $rawResults->groupBy('id')->map(function ($items) {
            $first = $items->first();
            $winner = $items->firstWhere('customer_id', '!=', 1);
            return [
                'id' => $first->id,
                'game_id' => $first->game_id,
                'players' => (int) $first->players,
                'total_bet' => (float) $first->total_bet,
                'customer_id' => $winner?->customer_id,
                'name' => $winner?->name,
                'amount' => $items->where('customer_id', '!=', 1)->sum('amount'),
                'income' => $items->where('customer_id', 1)->sum('amount'),
                'created_at' => $first->created_at,
            ];
        });

        return response()->json([ "status" => "Success", "data" => $grouped ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGameWalletRequest $request, $encryptedIdentifier){
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $gameWallet = GameWallet::where('id', $encryptedIdentifier)->first();
        if (!$gameWallet) { return response()->json(["message" => "Game Wallet not found"], 404);}
        $gameWallet->update($request->validated());
        //return GameWalletResource::make($gameWallet);
        return response()->json(['status' => 'success'], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        // At this point, $encryptedIdentifier is already decrypted by the middleware
        $gameWallet = GameWallet::where('id', $encryptedIdentifier)->first();
        if (!$gameWallet) { return response()->json(["message" => "Game Wallet not found"], 404);}
        $gameWallet->delete();
        return response()->json([ "status" => "Success" ], 200);
    }
}
