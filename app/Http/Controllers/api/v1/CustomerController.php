<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\LeaderboardResource;
use App\Http\Resources\PurchasesResource;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\Deposit;
use App\Models\Withdraw;
use App\Models\GameTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return CustomerResource::collection(
            Customer::query()
                ->where('status', 1)
                ->where('id', '!=', 1)
                ->whereDate('created_at', '>=', '2026-03-01 00:00:00')
                ->get()
        );
    }

    /**
     * Search for customers by name, account number, phone number, or email.
     * @param Request $request
     * @return JsonResponse|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function search(Request $request) {
        $query = $request->query('q');

        if (!$query) {
            return response()->json(['message' => 'Search query is required'], 422);
        }

        $customers = Customer::query()
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('account_no', 'like', "%{$query}%")
                  ->orWhere('phone_no', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->get();

        return CustomerResource::collection($customers);
    }

    public function customersReferrals(Request $request) {
        $referralCodes = Str::contains($request->referral_code, ',')
            ? explode(',', $request->referral_code)
            : [$request->referral_code];
        return CustomerResource::collection(Customer::query()->where('status', 1)->where('id', '!=', 1)->whereIn('referral_code', $referralCodes)->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request) {
        try {
            $customer = Customer::create($request->validated());
            Wallet::create(['customer_id' => $customer->id, 'balance' => 250]);
            return response()->json(["status" => "Success", "customer_id" => $customer->id ], 201);
        } catch (\Exception $e) {
            Log::error('Create Customer Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "status" => "Error creating Customer" ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedIdentifier){
        $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
        if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
        return CustomerResource::make($customer);
    }

    public function customer_transactions(Request $request, $encryptedIdentifier): JsonResponse{
        try {
            $request->validate([
                'payment_type' => 'required',
            ]);
            $payment_type = $request->payment_type;
            $customer = Customer::query()->where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
            if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
            $wallet = $customer->wallet;
            if(!$wallet) return response()->json([ "message" => "Customer wallet not found" ], 404);
            if($payment_type == 'deposit') $type = Deposit::class;
            elseif($payment_type == 'withdraw') $type = Withdraw::class;
            elseif ($payment_type == 'all') {
                $transactions = $wallet->transactions()->whereIn('payment_type', [Deposit::class, Withdraw::class])->limit(10)->latest()->get();
                $total = $wallet->transactions()->whereIn('payment_type', [Deposit::class, Withdraw::class])->sum('amount');
                return response()->json([ "total" => $total, "transactions" => $transactions ], 200);
            }
            else return response()->json([ "message" => "Invalid payment type" ], 400);
            $transactions = $wallet->transactions()->where('payment_type', $type)->limit(10)->latest()->get();
            $total = $wallet->transactions()->where('payment_type', $type)->sum('amount');
            return response()->json([ "total" => $total, "transactions" => $transactions ], 200);
        } catch (\Exception $e) {
            Log::error('Customer Get Transactions Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "message" => $e->getMessage() ], 500);
        }
    }

    public function customer_played($encryptedIdentifier) : JsonResponse {
        try {
            $customer = Customer::query()
                ->where('id', $encryptedIdentifier)
                ->orWhere('account_no', $encryptedIdentifier)
                ->with([
                    'gameWalletTransactions' => function($query) {
                        $query
                            ->where('payment_type', '!=', 'payout')
                            ->whereBetween('created_at', [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()])
                            ->with('gameWallet');
                    },
                    'allCompetitionTransactions' => function($query) {
                        $query
                            ->where('payment_type', '!=', 'payout')
                            ->where('payment_type', '!=', 'deposit')
                            ->whereBetween('competition_transactions.created_at', [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()])
                            ->with('wallet');
                    }
                ])
                ->first();

            if(!$customer) {
                return response()->json(["message" => "Customer not found"], 404);
            }

            // Preload payout information and player counts
            $gameWalletIds = $customer->gameWalletTransactions
                ->pluck('game_wallet_id')
                ->unique()
                ->values();

            $payouts = GameTransaction::query()
                ->whereIn('game_wallet_id', $gameWalletIds)
                ->where('payment_type', 'payout')
                ->pluck('game_wallet_id')
                ->flip();

            $playerCounts = GameTransaction::query()
                ->selectRaw('game_wallet_id, COUNT(*) as total')
                ->whereIn('game_wallet_id', $gameWalletIds)
                ->where('payment_type', 'deposit')
                ->groupBy('game_wallet_id')
                ->pluck('total', 'game_wallet_id');


            // Get single games
            $singleGames = $customer->gameWalletTransactions->map(function($transaction) use ($payouts, $playerCounts) {
                return [
                    'game_wallet_id' => $transaction->game_wallet_id,
                    'game_id' => $transaction->gameWallet->game_id ?? null,
                    'game_type' => 'Single Game',
                    'players' => $playerCounts[$transaction->game_wallet_id] ?? 0,
                    'amount' => $transaction->amount,
                    'payment_type' => $transaction->payment_type,
                    'state' => isset($payouts[$transaction->game_wallet_id]) ? 'win' : 'loss',
                    'created_at' => $transaction->created_at->toDateTimeString(),
                ];
            });

            // Process competition games, separating tournaments and jackpots
            $tournamentGames = collect();
            $jackpotGames = collect();

            foreach ($customer->allCompetitionTransactions as $transaction) {
                $gameData = [
                    'competition_id' => $transaction->wallet->competition_id ?? null,
                    'type' => $transaction->wallet->game_type ?? null,
                    'amount' => $transaction->amount,
                    'level' => $transaction->wallet->level ?? null,
                    'payment_type' => $transaction->payment_type,
                    'created_at' => $transaction->created_at->toDateTimeString(),
                ];

                // Separate by game_type (1 = tournament, 2 = jackpot)
                if (($transaction->wallet->game_type ?? null) == 1) {
                    $tournamentGames->push($gameData);
                }
                if (($transaction->wallet->game_type ?? null) == 2) {
                    $jackpotGames->push($gameData);
                }
            }

            // Pagination
            $singleGames = paginate(
                $singleGames,
                request()->integer('single_per_page', 10),
                request()->integer('single_page', 1),
                'single_page'
            );

            $tournamentGames = paginate(
                $tournamentGames,
                request()->integer('tournament_per_page', 10),
                request()->integer('tournament_page', 1),
                'tournament_page'
            );

            $jackpotGames = paginate(
                $jackpotGames,
                request()->integer('jackpot_per_page', 10),
                request()->integer('jackpot_page', 1),
                'jackpot_page'
            );

            return response()->json([
                "single_games" => $singleGames,
                "tournament_games" => $tournamentGames,
                "jackpot_games" => $jackpotGames
            ]);

        } catch (\Exception $e) {
            Log::error('Customer Get Played Error: ', ['error' => $e->getMessage()]);
            return response()->json(["message" => $e->getMessage()], 500);
        }
    }

    public function customer_leaderboard(Request $request): JsonResponse
    {
        try {
            // Merge default values if not set
            $request->merge([
                'start_date' => $request->start_date ?? Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m-d'),
                'end_date' => $request->end_date ?? Carbon::now()->endOfMonth()->format('Y-m-d'),
            ]);
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date',
            ]);
            $start_date = $request->start_date;
            $end_date = $request->end_date;
            // Get active customers (status = 1) and exclude system account (id = 1)
            $customers = Customer::with([
                'gameWalletTransactions' => function($query) {
                    $query->where('payment_type', 'payout');
                },
                'allCompetitionTransactions' => function($query) {
                    $query->where('payment_type', 'win');
                },
            ])
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->get();

            // Process single games leaderboard
            $singleGamesLeaderboard = $customers->map(function($customer) use ($start_date, $end_date) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'wins' => $customer->gameWalletTransactions
                        ->whereBetween('created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                        ->sum('amount')
                ];
            })->filter(fn($customer) => $customer['wins'] > 0)
              ->sortByDesc('wins')
              ->values()
              ->all();

            // Process competitions leaderboard
            $competitionsLeaderboard = $customers->map(function($customer) use ($start_date, $end_date) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'wins' => $customer->allCompetitionTransactions
                        ->whereBetween('created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                        ->sum('amount')
                ];
            })->filter(fn($customer) => $customer['wins'] > 0)
              ->sortByDesc('wins')
              ->values()
              ->all();

            return response()->json([
                'single_leaderboard' => $singleGamesLeaderboard,
                'competitions_leaderboard' => $competitionsLeaderboard,
            ]);

        } catch (\Exception $e) {
            Log::error('Customer Get Leaderboard Error: ', ['error' => $e->getMessage()]);
            return response()->json(["message" => $e->getMessage()], 500);
        }
    }

    public function combined_leaderboard(Request $request): JsonResponse
    {
        try {
            // Merge default values if not set
            $request->merge([
                'start_date' => $request->start_date ?? Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m-d'),
                'end_date' => $request->end_date ?? Carbon::now()->endOfMonth()->format('Y-m-d'),
            ]);

            $request->validate([
                'start_date' => 'date',
                'end_date' => 'date',
            ]);

            $start_date = Carbon::now()->startOfWeek()->format('Y-m-d');
            $end_date = Carbon::now()->endOfWeek()->format('Y-m-d');

            // Get active customers (status = 1) and exclude system account (id = 1)
            $customers = Customer::with([
                'gameWalletTransactions' => function($query) {
                    $query->where('payment_type', 'payout');
                },
                'allCompetitionTransactions' => function($query) {
                    $query->where('payment_type', 'win');
                },
            ])
                ->where('status', 1)
                ->where('id', '!=', 1)
                ->get();

            // Process combined leaderboard
            $combinedLeaderboard = [];

            foreach ($customers as $customer) {
                $singleGameWins = $customer->gameWalletTransactions
                    ->whereBetween('created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                    ->sum('amount');

                $competitionWins = $customer->allCompetitionTransactions
                    ->whereBetween('created_at', [$start_date . " 00:00:00", $end_date . " 23:59:59"])
                    ->sum('amount');

                $totalWins = $singleGameWins + $competitionWins;

                if ($totalWins > 0) {
                    $combinedLeaderboard[] = [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'single_game_wins' => $singleGameWins,
                        'competition_wins' => $competitionWins,
                        'total_wins' => $totalWins
                    ];
                }
            }

            // Sort by total_wins in descending order
            usort($combinedLeaderboard, function($a, $b) {
                return $b['total_wins'] <=> $a['total_wins'];
            });

            return response()->json([
                'leaderboard' => $combinedLeaderboard,
            ]);

        } catch (\Exception $e) {
            Log::error('Customer Get Combined Leaderboard Error: ', ['error' => $e->getMessage()]);
            return response()->json(["message" => $e->getMessage()], 500);
        }
    }

    public function customer_purchases($encryptedIdentifier) {
        try {
            $customer = Customer::query()->where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
            if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
            $customerPurchases = $customer->purchases()->get()->map(function($purchase) {
                return [
                    'id' => $purchase->id,
                    'customer' => $purchase->customer->name,
                    'amount' => $purchase->amount,
                    'type' => $purchase->purchase_type,
                    'value' => $purchase->value,
                    'created_at' => $purchase->created_at->toDateTimeString(),
                ];
            });
            return response()->json(['data' => $customerPurchases], 200);
        } catch (\Exception $e) {
            Log::error('Customer Get Purchases Error: ', ['error' => $e->getMessage()]);
            return response()->json([ "message" => $e->getMessage() ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerRequest $request, $encryptedIdentifier) {
        $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
        if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
        $customer->update($request->validated());
        return CustomerResource::make($customer);
    }

    public function update_wallet(Request $request, $encryptedIdentifier): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric',
        ]);
        $customer = Customer::query()->where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
        if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
        $customer->wallet->balance += $request->amount;
        $customer->wallet->save();
        return response()->json(['status' => 'success'], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($encryptedIdentifier) {
        $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
        if(!$customer) return response()->json([ "message" => "Customer not found" ], 404);
        $customer->delete();
        return response()->json([ "message" => "Customer deleted successfully" ], 200);
    }
}
