<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\CompetitionWallet;
use App\Models\GameTransaction;
use App\Models\Customer;
use App\Models\Wallet;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StatsController extends Controller
{
    /**
     * Get customer statistics
     *
     * @return JsonResponse
     */
    public function customerStats(): JsonResponse
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        $stats = [
            'today' => Customer::where('id', '>=', '120')->whereDate('created_at', $today)->count(),
            'this_week' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfWeek)->count(),
            'this_month' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfMonth)->count(),
            'this_year' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfYear)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    public function customerReferralStats(Request $request): JsonResponse
    {
        $referralCodes = Str::contains($request->referral_code, ',')
            ? explode(',', $request->referral_code)
            : [$request->referral_code];

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        $stats = [
            'today' => Customer::where('id', '>=', '120')->whereDate('created_at', $today)->whereIn('referral_code', $referralCodes)->count(),
            'this_week' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfWeek)->whereIn('referral_code', $referralCodes)->count(),
            'this_month' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfMonth)->whereIn('referral_code', $referralCodes)->count(),
            'this_year' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfYear)->whereIn('referral_code', $referralCodes)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function incomeStats(): JsonResponse
    {
        $today = Carbon::today();

        // Helper to get single games income by player count
        $getSingleGamesIncomeByPlayerCount = function ($playerCount) use ($today) {
            // Get game_wallet_ids that have exactly $playerCount distinct customers with deposit payment_type
            $gameWalletIds = DB::table('game_transactions')
                ->select('game_wallet_id')
                ->where('payment_type', 'deposit')
                ->whereDate('created_at', $today)
                ->groupBy('game_wallet_id')
                ->havingRaw('COUNT(DISTINCT customer_id) = ?', [$playerCount])
                ->pluck('game_wallet_id');

            // Sum payout amounts for these game_wallet_ids
            return DB::table('game_transactions')
                ->where('payment_type', 'payout')
                ->where('customer_id', 1)
                ->whereDate('created_at', $today)
                ->whereIn('game_wallet_id', $gameWalletIds)
                ->sum('amount');
        };

        // Income from single games by player count
        $singleGames2PlayersIncome = $getSingleGamesIncomeByPlayerCount(2);
        $singleGames3PlayersIncome = $getSingleGamesIncomeByPlayerCount(3);
        $singleGames4PlayersIncome = $getSingleGamesIncomeByPlayerCount(4);

        // Total single game income
        $totalSingleGamesIncome = $singleGames2PlayersIncome + $singleGames3PlayersIncome + $singleGames4PlayersIncome;

        // Helper for tournament/jackpot income breakdown by jp_rounds
        $getCompetitionIncomeByRounds = function ($gameType, $rounds) use ($today) {
            return DB::table('competition_wallets as C')
                ->join('wallet_transactions as W', 'W.sender_id', '=', 'C.id')
                ->where('C.game_type', $gameType)
                ->whereIn('C.jp_rounds', $rounds)
                ->whereDate('C.created_at', $today)
                ->sum('W.amount');
        };

        // Tournaments income breakdown (game_type = 1, jp_rounds: 3, 4, 5)
        $tournaments3RoundsIncome = $getCompetitionIncomeByRounds(1, [3]);
        $tournaments4RoundsIncome = $getCompetitionIncomeByRounds(1, [4]);
        $tournaments5RoundsIncome = $getCompetitionIncomeByRounds(1, [5]);
        $totalTournamentsIncome = $tournaments3RoundsIncome + $tournaments4RoundsIncome + $tournaments5RoundsIncome;

        // Jackpots income breakdown (game_type = 2, jp_rounds: 13, 17, 21)
        $jackpots13RoundsIncome = $getCompetitionIncomeByRounds(2, [13]);
        $jackpots17RoundsIncome = $getCompetitionIncomeByRounds(2, [17]);
        $jackpots21RoundsIncome = $getCompetitionIncomeByRounds(2, [21]);
        $totalJackpotsIncome = $jackpots13RoundsIncome + $jackpots17RoundsIncome + $jackpots21RoundsIncome;
        
        $totalIncome = $totalSingleGamesIncome + $totalTournamentsIncome + $totalJackpotsIncome;

        $stats = [
            'total_income' => $totalIncome, // you can sum them if needed
            'games' => [
                'total' => $totalSingleGamesIncome,
                '2_players' => $singleGames2PlayersIncome,
                '3_players' => $singleGames3PlayersIncome,
                '4_players' => $singleGames4PlayersIncome,
            ],
            'tournaments' => [
                'total' => $totalTournamentsIncome,
                '3_rounds' => $tournaments3RoundsIncome,
                '4_rounds' => $tournaments4RoundsIncome,
                '5_rounds' => $tournaments5RoundsIncome,
            ],
            'jackpots' => [
                'total' => $totalJackpotsIncome,
                '13_rounds' => $jackpots13RoundsIncome,
                '17_rounds' => $jackpots17RoundsIncome,
                '21_rounds' => $jackpots21RoundsIncome,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
    
    
    public function dailyIncomeStats30Days(): JsonResponse
    {
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays(29);

        $dailyStats = [];

        for ($i = 0; $i < 30; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dateString = $currentDate->format('Y-m-d');

            // Helper to get single games income by player count for a specific date
            $getSingleGamesIncomeByPlayerCount = function ($playerCount) use ($currentDate) {
                $gameWalletIds = DB::table('game_transactions')
                    ->select('game_wallet_id')
                    ->where('payment_type', 'deposit')
                    ->whereDate('created_at', $currentDate)
                    ->groupBy('game_wallet_id')
                    ->havingRaw('COUNT(DISTINCT customer_id) = ?', [$playerCount])
                    ->pluck('game_wallet_id');

                return DB::table('game_transactions')
                    ->where('payment_type', 'payout')
                    ->where('customer_id', 1)
                    ->whereDate('created_at', $currentDate)
                    ->whereIn('game_wallet_id', $gameWalletIds)
                    ->sum('amount');
            };

            // Single games income by player count
            $singleGames2PlayersIncome = $getSingleGamesIncomeByPlayerCount(2);
            $singleGames3PlayersIncome = $getSingleGamesIncomeByPlayerCount(3);
            $singleGames4PlayersIncome = $getSingleGamesIncomeByPlayerCount(4);
            $totalSingleGamesIncome = $singleGames2PlayersIncome + $singleGames3PlayersIncome + $singleGames4PlayersIncome;

            // Helper for tournament/jackpot income breakdown by jp_rounds for a specific date
            $getCompetitionIncomeByRounds = function ($gameType, $rounds) use ($currentDate) {
                return DB::table('competition_wallets as C')
                    ->join('wallet_transactions as W', 'W.sender_id', '=', 'C.id')
                    ->where('C.game_type', $gameType)
                    ->whereIn('C.jp_rounds', $rounds)
                    ->whereDate('C.created_at', $currentDate)
                    ->sum('W.amount');
            };

            // Tournaments income breakdown (game_type = 1, jp_rounds: 3, 4, 5)
            $tournaments3RoundsIncome = $getCompetitionIncomeByRounds(1, [3]);
            $tournaments4RoundsIncome = $getCompetitionIncomeByRounds(1, [4]);
            $tournaments5RoundsIncome = $getCompetitionIncomeByRounds(1, [5]);
            $totalTournamentsIncome = $tournaments3RoundsIncome + $tournaments4RoundsIncome + $tournaments5RoundsIncome;

            // Jackpots income breakdown (game_type = 2, jp_rounds: 13, 17, 21)
            $jackpots13RoundsIncome = $getCompetitionIncomeByRounds(2, [13]);
            $jackpots17RoundsIncome = $getCompetitionIncomeByRounds(2, [17]);
            $jackpots21RoundsIncome = $getCompetitionIncomeByRounds(2, [21]);
            $totalJackpotsIncome = $jackpots13RoundsIncome + $jackpots17RoundsIncome + $jackpots21RoundsIncome;

            $dailyStats[$dateString] = [
                'single_games' => $totalSingleGamesIncome,
                'tournaments' => $totalTournamentsIncome,
                'jackpots' => $totalJackpotsIncome,
                'total' => $totalSingleGamesIncome + $totalTournamentsIncome + $totalJackpotsIncome,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'daily_stats' => $dailyStats,
            ],
        ]);
    }
    
    public function purchaseStats(): JsonResponse {
        $today = Carbon::today();

        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        $purchases = [
            'today' => Purchase::where('test', '0')->whereDate('created_at', $today)->sum('amount'),
            'week' => Purchase::where('test', '0')->where('created_at', '>=', $startOfWeek)->sum('amount'),
            'month' => Purchase::where('test', '0')->where('created_at', '>=', $startOfMonth)->sum('amount'),
            'year' => Purchase::where('test', '0')->where('created_at', '>=', $startOfYear)->sum('amount'),
            'total' => Purchase::where('test', '0')->sum('amount')
        ];

        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }
    
    public function purchaseReferralsStats(Request $request): JsonResponse {
        $referralCodes = Str::contains($request->referral_code, ',')
            ? explode(',', $request->referral_code)
            : [$request->referral_code];

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        $purchases = [
            'today' => Purchase::where('test', '0')->whereDate('created_at', $today)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'week' => Purchase::where('test', '0')->where('created_at', '>=', $startOfWeek)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'month' => Purchase::where('test', '0')->where('created_at', '>=', $startOfMonth)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'year' => Purchase::where('test', '0')->where('created_at', '>=', $startOfYear)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'total' => Purchase::where('test', '0')->whereIn('referral_code', $referralCodes)->sum('amount')
        ];

        return response()->json([
            'success' => true,
            'data' => $purchases
        ]);
    }
    
    public function playedStats(): JsonResponse {
        $today = Carbon::today();

        // Helper to get single games played by player count
        $getSingleGamesPlayedByPlayerCount = function ($playerCount) use ($today) {
            // Get game_wallet_ids that have exactly $playerCount distinct customers with deposit payment_type
            return DB::table('game_transactions')
                ->select('game_wallet_id')
                ->where('payment_type', 'deposit')
                ->whereDate('created_at', $today)
                ->groupBy('game_wallet_id')
                ->havingRaw('COUNT(DISTINCT customer_id) = ?', [$playerCount])
                ->count();
        };

        // Single games played breakdown by player count
        $games2Players = $getSingleGamesPlayedByPlayerCount(2);
        $games3Players = $getSingleGamesPlayedByPlayerCount(3);
        $games4Players = $getSingleGamesPlayedByPlayerCount(4);
        $totalGamesPlayed = $games2Players + $games3Players + $games4Players;

        // Helper to get tournaments/jackpots played by rounds
        $getCompetitionPlayedByRounds = function ($gameType, $rounds) use ($today) {
            return CompetitionWallet::where('game_type', $gameType)
                ->whereIn('jp_rounds', $rounds)
                ->whereHas('transactions', function($query) use ($today) {
                    $query->where('payment_type', '!=', 'payout')
                        ->whereDate('created_at', $today);
                })
                ->count();
        };

        // Tournaments played breakdown (game_type = 1, jp_rounds: 3, 4, 5)
        $tournaments3Rounds = $getCompetitionPlayedByRounds(1, [3]);
        $tournaments4Rounds = $getCompetitionPlayedByRounds(1, [4]);
        $tournaments5Rounds = $getCompetitionPlayedByRounds(1, [5]);
        $totalTournamentsPlayed = $tournaments3Rounds + $tournaments4Rounds + $tournaments5Rounds;

        // Jackpots played breakdown (game_type = 2, jp_rounds: 13, 17, 21)
        $jackpots13Rounds = $getCompetitionPlayedByRounds(2, [13]);
        $jackpots17Rounds = $getCompetitionPlayedByRounds(2, [17]);
        $jackpots21Rounds = $getCompetitionPlayedByRounds(2, [21]);
        $totalJackpotsPlayed = $jackpots13Rounds + $jackpots17Rounds + $jackpots21Rounds;

        $totalPlayedToday = $totalGamesPlayed + $totalTournamentsPlayed + $totalJackpotsPlayed;

        $played = [
            'total' => $totalPlayedToday,
            'games' => [
                'total' => $totalGamesPlayed,
                '2_players' => $games2Players,
                '3_players' => $games3Players,
                '4_players' => $games4Players,
            ],
            'tournament' => [
                'total' => $totalTournamentsPlayed,
                '3_rounds' => $tournaments3Rounds,
                '4_rounds' => $tournaments4Rounds,
                '5_rounds' => $tournaments5Rounds,
            ],
            'jackpots' => [
                'total' => $totalJackpotsPlayed,
                '13_rounds' => $jackpots13Rounds,
                '17_rounds' => $jackpots17Rounds,
                '21_rounds' => $jackpots21Rounds,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $played
        ]);
    }
    
    /**
     * Accepts customer_id, start_date and end_date and returns summary of games played by the player
     * returns {total, games, tournament, jackpots}
     * @param Request $request
     * @return JsonResponse
     */
    public function playedByPlayerStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        // Count single games played today
        $gamesPlayedToday = GameTransaction::where('payment_type', '!=', 'payout')
            ->where('customer_id', $validated['customer_id'])
            ->whereDate('created_at', Carbon::today())
            ->count();

        // Count tournaments played today (game_type = 1)
        $tournamentsPlayedToday = CompetitionWallet::where('game_type', 1)
            ->whereHas('transactions', function($query) use ($validated) {
                $query->where('payment_type', '!=', 'payout')
                    ->where('customer_id', $validated['customer_id'])
                    ->whereDate('created_at', Carbon::today());
            })
            ->count();

        // Count jackpots played today (game_type = 2)
        $jackpotsPlayedToday = CompetitionWallet::where('game_type', 2)
            ->whereHas('transactions', function($query) use ($validated) {
                $query->where('payment_type', '!=', 'payout')
                    ->where('customer_id', $validated['customer_id'])
                    ->whereDate('created_at', Carbon::today());
            })
            ->count();

        $totalPlayedToday = $gamesPlayedToday + $tournamentsPlayedToday + $jackpotsPlayedToday;

        $played = [
            'total' => $totalPlayedToday,
            'games' => $gamesPlayedToday,
            'tournament' => $tournamentsPlayedToday,
            'jackpots' => $jackpotsPlayedToday
        ];

        return response()->json([
            'success' => true,
            'data' => $played
        ]);
    }
    
    public function retentionRates(): JsonResponse
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();
    
        // Get all players who have played before today (for calculating returning players)
        $allPlayers = Customer::whereHas('gameWalletTransactions', function($q) use ($today) {
                $q->where('game_transactions.created_at', '<', $today);
            })
            ->orWhereHas('allCompetitionTransactions', function($q) use ($today) {
                $q->where('competition_transactions.created_at', '<', $today);
            })
            ->count();
    
        if ($allPlayers === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'today' => 0,
                    'week' => 0,
                    'month' => 0,
                    'year' => 0,
                    'total_players' => 0
                ]
            ]);
        }
    
        // Helper function to count returning players
        $countReturningPlayers = function ($startDate, $endDate = null) use ($today) {
            return Customer::where(function($q) use ($startDate, $endDate, $today) {
                // Players who played before today
                $q->where(function($q) use ($today) {
                    $q->whereHas('gameWalletTransactions', function($q) use ($today) {
                        $q->where('game_transactions.created_at', '<', $today);
                    })
                    ->orWhereHas('allCompetitionTransactions', function($q) use ($today) {
                        $q->where('competition_transactions.created_at', '<', $today);
                    });
                })
                // And played again in the target period
                ->where(function($q) use ($startDate, $endDate) {
                    $q->whereHas('gameWalletTransactions', function($q) use ($startDate, $endDate) {
                        $q->where('game_transactions.created_at', '>=', $startDate);
                        if ($endDate) {
                            $q->where('game_transactions.created_at', '<=', $endDate);
                        }
                    })
                    ->orWhereHas('allCompetitionTransactions', function($q) use ($startDate, $endDate) {
                        $q->where('competition_transactions.created_at', '>=', $startDate);
                        if ($endDate) {
                            $q->where('competition_transactions.created_at', '<=', $endDate);
                        }
                    });
                });
            })->count();
        };
    
        // Calculate returning players for each period
        $todayReturning = $countReturningPlayers($today, $now);
        $weekReturning = $countReturningPlayers($startOfWeek, $now);
        $monthReturning = $countReturningPlayers($startOfMonth, $now);
        $yearReturning = $countReturningPlayers($startOfYear, $now);
    
        return response()->json([
            'success' => true,
            'data' => [
                'today' => $todayReturning,
                'week' => $weekReturning,
                'month' => $monthReturning,
                'year' => $yearReturning,
                'total_players' => $allPlayers
            ]
        ]);
    }
}
