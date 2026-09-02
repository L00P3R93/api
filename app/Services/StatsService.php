<?php

namespace App\Services;

use App\Models\CompetitionWallet;
use App\Models\Customer;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StatsService
{
    public function customerStats(): array
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        return [
            'today' => Customer::where('id', '>=', '120')->whereDate('created_at', $today)->count(),
            'this_week' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfWeek)->count(),
            'this_month' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfMonth)->count(),
            'this_year' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfYear)->count(),
        ];
    }

    public function customerReferralStats(string $referralCode): array
    {
        $referralCodes = Str::contains($referralCode, ',')
            ? explode(',', $referralCode)
            : [$referralCode];

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        return [
            'today' => Customer::where('id', '>=', '120')->whereDate('created_at', $today)->whereIn('referral_code', $referralCodes)->count(),
            'this_week' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfWeek)->whereIn('referral_code', $referralCodes)->count(),
            'this_month' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfMonth)->whereIn('referral_code', $referralCodes)->count(),
            'this_year' => Customer::where('id', '>=', '120')->where('created_at', '>=', $startOfYear)->whereIn('referral_code', $referralCodes)->count(),
        ];
    }

    public function incomeStats(): array
    {
        $today = Carbon::today();

        $getSingleGamesIncomeByPlayerCount = function ($playerCount) use ($today) {
            $gameWalletIds = DB::table('game_transactions')
                ->select('game_wallet_id')
                ->where('payment_type', 'deposit')
                ->whereDate('created_at', $today)
                ->groupBy('game_wallet_id')
                ->havingRaw('COUNT(DISTINCT customer_id) = ?', [$playerCount])
                ->pluck('game_wallet_id');

            return DB::table('game_transactions')
                ->where('payment_type', 'payout')
                ->where('customer_id', 1)
                ->whereDate('created_at', $today)
                ->whereIn('game_wallet_id', $gameWalletIds)
                ->sum('amount');
        };

        $singleGames2PlayersIncome = $getSingleGamesIncomeByPlayerCount(2);
        $singleGames3PlayersIncome = $getSingleGamesIncomeByPlayerCount(3);
        $singleGames4PlayersIncome = $getSingleGamesIncomeByPlayerCount(4);
        $totalSingleGamesIncome = $singleGames2PlayersIncome + $singleGames3PlayersIncome + $singleGames4PlayersIncome;

        $getCompetitionIncomeByRounds = function ($gameType, $rounds) use ($today) {
            return DB::table('competition_wallets as C')
                ->join('wallet_transactions as W', 'W.sender_id', '=', 'C.id')
                ->where('C.game_type', $gameType)
                ->whereIn('C.jp_rounds', $rounds)
                ->whereDate('C.created_at', $today)
                ->sum('W.amount');
        };

        $tournaments3RoundsIncome = $getCompetitionIncomeByRounds(1, [3]);
        $tournaments4RoundsIncome = $getCompetitionIncomeByRounds(1, [4]);
        $tournaments5RoundsIncome = $getCompetitionIncomeByRounds(1, [5]);
        $totalTournamentsIncome = $tournaments3RoundsIncome + $tournaments4RoundsIncome + $tournaments5RoundsIncome;

        $jackpots13RoundsIncome = $getCompetitionIncomeByRounds(2, [13]);
        $jackpots17RoundsIncome = $getCompetitionIncomeByRounds(2, [17]);
        $jackpots21RoundsIncome = $getCompetitionIncomeByRounds(2, [21]);
        $totalJackpotsIncome = $jackpots13RoundsIncome + $jackpots17RoundsIncome + $jackpots21RoundsIncome;

        $totalIncome = $totalSingleGamesIncome + $totalTournamentsIncome + $totalJackpotsIncome;

        return [
            'total_income' => $totalIncome,
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
    }

    public function dailyIncomeStats30Days(): array
    {
        $endDate = Carbon::today();
        $startDate = Carbon::today()->subDays(29);
        $dailyStats = [];

        for ($i = 0; $i < 30; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dateString = $currentDate->format('Y-m-d');

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

            $singleGames2PlayersIncome = $getSingleGamesIncomeByPlayerCount(2);
            $singleGames3PlayersIncome = $getSingleGamesIncomeByPlayerCount(3);
            $singleGames4PlayersIncome = $getSingleGamesIncomeByPlayerCount(4);
            $totalSingleGamesIncome = $singleGames2PlayersIncome + $singleGames3PlayersIncome + $singleGames4PlayersIncome;

            $getCompetitionIncomeByRounds = function ($gameType, $rounds) use ($currentDate) {
                return DB::table('competition_wallets as C')
                    ->join('wallet_transactions as W', 'W.sender_id', '=', 'C.id')
                    ->where('C.game_type', $gameType)
                    ->whereIn('C.jp_rounds', $rounds)
                    ->whereDate('C.created_at', $currentDate)
                    ->sum('W.amount');
            };

            $tournaments3RoundsIncome = $getCompetitionIncomeByRounds(1, [3]);
            $tournaments4RoundsIncome = $getCompetitionIncomeByRounds(1, [4]);
            $tournaments5RoundsIncome = $getCompetitionIncomeByRounds(1, [5]);
            $totalTournamentsIncome = $tournaments3RoundsIncome + $tournaments4RoundsIncome + $tournaments5RoundsIncome;

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

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'daily_stats' => $dailyStats,
        ];
    }

    public function purchaseStats(): array
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        return [
            'today' => Purchase::whereDate('created_at', $today)->sum('amount'),
            'week' => Purchase::where('created_at', '>=', $startOfWeek)->sum('amount'),
            'month' => Purchase::where('created_at', '>=', $startOfMonth)->sum('amount'),
            'year' => Purchase::where('created_at', '>=', $startOfYear)->sum('amount'),
            'total' => Purchase::sum('amount'),
        ];
    }

    public function purchaseReferralsStats(string $referralCode): array
    {
        $referralCodes = Str::contains($referralCode, ',')
            ? explode(',', $referralCode)
            : [$referralCode];

        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        return [
            'today' => Purchase::where('test', '0')->whereDate('created_at', $today)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'week' => Purchase::where('test', '0')->where('created_at', '>=', $startOfWeek)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'month' => Purchase::where('test', '0')->where('created_at', '>=', $startOfMonth)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'year' => Purchase::where('test', '0')->where('created_at', '>=', $startOfYear)->whereIn('referral_code', $referralCodes)->sum('amount'),
            'total' => Purchase::where('test', '0')->whereIn('referral_code', $referralCodes)->sum('amount'),
        ];
    }

    public function playedStats(): array
    {
        $today = Carbon::today();

        $getSingleGamesPlayedByPlayerCount = function ($playerCount) use ($today) {
            return DB::table('game_transactions')
                ->select('game_wallet_id')
                ->where('payment_type', 'deposit')
                ->whereDate('created_at', $today)
                ->groupBy('game_wallet_id')
                ->havingRaw('COUNT(DISTINCT customer_id) = ?', [$playerCount])
                ->count();
        };

        $games2Players = $getSingleGamesPlayedByPlayerCount(2);
        $games3Players = $getSingleGamesPlayedByPlayerCount(3);
        $games4Players = $getSingleGamesPlayedByPlayerCount(4);
        $totalGamesPlayed = $games2Players + $games3Players + $games4Players;

        $getCompetitionPlayedByRounds = function ($gameType, $rounds) use ($today) {
            return CompetitionWallet::where('game_type', $gameType)
                ->whereIn('jp_rounds', $rounds)
                ->whereHas('transactions', function ($query) use ($today) {
                    $query->where('payment_type', '!=', 'payout')
                        ->whereDate('created_at', $today);
                })
                ->count();
        };

        $tournaments3Rounds = $getCompetitionPlayedByRounds(1, [3]);
        $tournaments4Rounds = $getCompetitionPlayedByRounds(1, [4]);
        $tournaments5Rounds = $getCompetitionPlayedByRounds(1, [5]);
        $totalTournamentsPlayed = $tournaments3Rounds + $tournaments4Rounds + $tournaments5Rounds;

        $jackpots13Rounds = $getCompetitionPlayedByRounds(2, [13]);
        $jackpots17Rounds = $getCompetitionPlayedByRounds(2, [17]);
        $jackpots21Rounds = $getCompetitionPlayedByRounds(2, [21]);
        $totalJackpotsPlayed = $jackpots13Rounds + $jackpots17Rounds + $jackpots21Rounds;

        $totalPlayedToday = $totalGamesPlayed + $totalTournamentsPlayed + $totalJackpotsPlayed;

        return [
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
    }

    public function playedByPlayerStats(int $customerId, string $startDate, string $endDate): array
    {
        $getSingleGamesPlayedByPlayerCount = function ($playerCount) use ($customerId, $startDate, $endDate) {
            $customerGameWalletIds = DB::table('game_transactions')
                ->select('game_wallet_id')
                ->where('payment_type', 'deposit')
                ->where('customer_id', $customerId)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->pluck('game_wallet_id')
                ->unique();

            return DB::table('game_transactions')
                ->select('game_wallet_id')
                ->where('payment_type', 'deposit')
                ->whereIn('game_wallet_id', $customerGameWalletIds)
                ->whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->groupBy('game_wallet_id')
                ->havingRaw('COUNT(DISTINCT customer_id) = ?', [$playerCount])
                ->count();
        };

        $games2Players = $getSingleGamesPlayedByPlayerCount(2);
        $games3Players = $getSingleGamesPlayedByPlayerCount(3);
        $games4Players = $getSingleGamesPlayedByPlayerCount(4);
        $totalGamesPlayed = $games2Players + $games3Players + $games4Players;

        $getCompetitionPlayedByRounds = function ($gameType, $rounds) use ($customerId, $startDate, $endDate) {
            return CompetitionWallet::where('game_type', $gameType)
                ->whereIn('jp_rounds', $rounds)
                ->whereHas('transactions', function ($query) use ($customerId, $startDate, $endDate) {
                    $query->where('payment_type', '!=', 'payout')
                        ->where('customer_id', $customerId)
                        ->whereDate('created_at', '>=', $startDate)
                        ->whereDate('created_at', '<=', $endDate);
                })
                ->count();
        };

        $tournaments3Rounds = $getCompetitionPlayedByRounds(1, [3]);
        $tournaments4Rounds = $getCompetitionPlayedByRounds(1, [4]);
        $tournaments5Rounds = $getCompetitionPlayedByRounds(1, [5]);
        $totalTournamentsPlayed = $tournaments3Rounds + $tournaments4Rounds + $tournaments5Rounds;

        $jackpots13Rounds = $getCompetitionPlayedByRounds(2, [13]);
        $jackpots17Rounds = $getCompetitionPlayedByRounds(2, [17]);
        $jackpots21Rounds = $getCompetitionPlayedByRounds(2, [21]);
        $totalJackpotsPlayed = $jackpots13Rounds + $jackpots17Rounds + $jackpots21Rounds;

        $totalPlayedToday = $totalGamesPlayed + $totalTournamentsPlayed + $totalJackpotsPlayed;

        return [
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
    }

    public function retentionRates(): array
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfYear = $now->copy()->startOfYear();

        $allPlayers = Customer::whereHas('gameWalletTransactions', function ($q) use ($today) {
            $q->where('game_transactions.created_at', '<', $today);
        })
            ->orWhereHas('allCompetitionTransactions', function ($q) use ($today) {
                $q->where('competition_transactions.created_at', '<', $today);
            })
            ->count();

        if ($allPlayers === 0) {
            return [
                'today' => 0,
                'week' => 0,
                'month' => 0,
                'year' => 0,
                'total_players' => 0,
            ];
        }

        $countReturningPlayers = function ($startDate, $endDate = null) use ($today) {
            return Customer::where(function ($q) use ($startDate, $endDate, $today) {
                $q->where(function ($q) use ($today) {
                    $q->whereHas('gameWalletTransactions', function ($q) use ($today) {
                        $q->where('game_transactions.created_at', '<', $today);
                    })
                        ->orWhereHas('allCompetitionTransactions', function ($q) use ($today) {
                            $q->where('competition_transactions.created_at', '<', $today);
                        });
                })
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereHas('gameWalletTransactions', function ($q) use ($startDate, $endDate) {
                            $q->where('game_transactions.created_at', '>=', $startDate);
                            if ($endDate) {
                                $q->where('game_transactions.created_at', '<=', $endDate);
                            }
                        })
                            ->orWhereHas('allCompetitionTransactions', function ($q) use ($startDate, $endDate) {
                                $q->where('competition_transactions.created_at', '>=', $startDate);
                                if ($endDate) {
                                    $q->where('competition_transactions.created_at', '<=', $endDate);
                                }
                            });
                    });
            })->count();
        };

        $todayReturning = $countReturningPlayers($today, $now);
        $weekReturning = $countReturningPlayers($startOfWeek, $now);
        $monthReturning = $countReturningPlayers($startOfMonth, $now);
        $yearReturning = $countReturningPlayers($startOfYear, $now);

        return [
            'today' => $todayReturning,
            'week' => $weekReturning,
            'month' => $monthReturning,
            'year' => $yearReturning,
            'total_players' => $allPlayers,
        ];
    }
}
