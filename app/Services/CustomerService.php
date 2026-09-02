<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deposit;
use App\Models\GameTransaction;
use App\Models\Wallet;
use App\Models\Withdraw;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CustomerService
{
    public function listActiveCustomers(): Collection
    {
        return Customer::query()
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->whereDate('created_at', '>=', '2026-03-01 00:00:00')
            ->get();
    }

    public function searchCustomers(string $query): Collection
    {
        return Customer::query()
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('account_no', 'like', "%{$query}%")
                    ->orWhere('phone_no', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->get();
    }

    public function getCustomersByReferralCodes(string $referralCodes): Collection
    {
        $codes = Str::contains($referralCodes, ',')
            ? explode(',', $referralCodes)
            : [$referralCodes];

        return Customer::query()
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->whereIn('referral_code', $codes)
            ->get();
    }

    public function createCustomer(array $data): Customer
    {
        $customer = Customer::create($data);
        Wallet::create(['customer_id' => $customer->id, 'balance' => 250]);

        return $customer;
    }

    public function getCustomer($identifier): ?Customer
    {
        return Customer::where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();
    }

    public function getCustomerTransactions($identifier, string $paymentType): array
    {
        $customer = Customer::query()
            ->where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();

        if (! $customer) {
            return ['customer' => null, 'total' => 0, 'transactions' => collect()];
        }

        $wallet = $customer->wallet;

        if (! $wallet) {
            return ['customer' => $customer, 'wallet' => null, 'total' => 0, 'transactions' => collect()];
        }

        if ($paymentType === 'all') {
            $transactions = $wallet->transactions()
                ->whereIn('payment_type', [Deposit::class, Withdraw::class])
                ->limit(10)
                ->latest()
                ->get();
            $total = $wallet->transactions()
                ->whereIn('payment_type', [Deposit::class, Withdraw::class])
                ->sum('amount');

            return ['customer' => $customer, 'wallet' => $wallet, 'total' => $total, 'transactions' => $transactions];
        }

        $type = match ($paymentType) {
            'deposit' => Deposit::class,
            'withdraw' => Withdraw::class,
            default => null,
        };

        if (! $type) {
            return ['customer' => $customer, 'wallet' => $wallet, 'invalid_type' => true, 'total' => 0, 'transactions' => collect()];
        }

        $transactions = $wallet->transactions()
            ->where('payment_type', $type)
            ->limit(10)
            ->latest()
            ->get();
        $total = $wallet->transactions()
            ->where('payment_type', $type)
            ->sum('amount');

        return ['customer' => $customer, 'wallet' => $wallet, 'total' => $total, 'transactions' => $transactions];
    }

    public function getCustomerPlayedGames($identifier): array
    {
        $customer = Customer::query()
            ->where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->with([
                'gameWalletTransactions' => function ($query) {
                    $query
                        ->where('payment_type', '!=', 'payout')
                        ->whereBetween('created_at', [Carbon::now()->subMonths(3)->startOfMonth(), Carbon::now()->endOfMonth()])
                        ->with('gameWallet');
                },
                'allCompetitionTransactions' => function ($query) {
                    $query
                        ->where('payment_type', '!=', 'payout')
                        ->where('payment_type', '!=', 'deposit')
                        ->where('competition_transactions.created_at', '>=', Carbon::now()->subMonths(3)->startOfMonth())
                        ->where('competition_transactions.created_at', '<=', Carbon::now()->endOfMonth())
                        ->with('wallet');
                },
            ])
            ->first();

        if (! $customer) {
            return ['customer' => null];
        }

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

        $singleGames = $customer->gameWalletTransactions->map(function ($transaction) use ($payouts, $playerCounts) {
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

            if (($transaction->wallet->game_type ?? null) == 1) {
                $tournamentGames->push($gameData);
            }
            if (($transaction->wallet->game_type ?? null) == 2) {
                $jackpotGames->push($gameData);
            }
        }

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

        return [
            'customer' => $customer,
            'single_games' => $singleGames,
            'tournament_games' => $tournamentGames,
            'jackpot_games' => $jackpotGames,
        ];
    }

    public function getCustomerLeaderboard(?string $startDate = null, ?string $endDate = null): array
    {
        $startDate = $startDate ?? Carbon::now()->subMonths(3)->startOfMonth()->format('Y-m-d');
        $endDate = $endDate ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        $customers = Customer::with([
            'gameWalletTransactions' => function ($query) {
                $query->where('payment_type', 'payout');
            },
            'allCompetitionTransactions' => function ($query) {
                $query->where('payment_type', 'win');
            },
        ])
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->get();

        $singleGamesLeaderboard = $customers->map(function ($customer) use ($startDate, $endDate) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'wins' => $customer->gameWalletTransactions
                    ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->sum('amount'),
            ];
        })
            ->filter(fn ($customer) => $customer['wins'] > 0)
            ->sortByDesc('wins')
            ->values()
            ->all();

        $competitionsLeaderboard = $customers->map(function ($customer) use ($startDate, $endDate) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'wins' => $customer->allCompetitionTransactions
                    ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                    ->sum('amount'),
            ];
        })
            ->filter(fn ($customer) => $customer['wins'] > 0)
            ->sortByDesc('wins')
            ->values()
            ->all();

        return [
            'single_leaderboard' => $singleGamesLeaderboard,
            'competitions_leaderboard' => $competitionsLeaderboard,
        ];
    }

    public function getCombinedLeaderboard(): array
    {
        $startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
        $endDate = Carbon::now()->endOfWeek()->format('Y-m-d');

        $customers = Customer::with([
            'gameWalletTransactions' => function ($query) {
                $query->where('payment_type', 'payout');
            },
            'allCompetitionTransactions' => function ($query) {
                $query->where('payment_type', 'win');
            },
        ])
            ->where('status', 1)
            ->where('id', '!=', 1)
            ->get();

        $combinedLeaderboard = [];

        foreach ($customers as $customer) {
            $singleGameWins = $customer->gameWalletTransactions
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->sum('amount');

            $competitionWins = $customer->allCompetitionTransactions
                ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
                ->sum('amount');

            $totalWins = $singleGameWins + $competitionWins;

            if ($totalWins > 0) {
                $combinedLeaderboard[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'single_game_wins' => $singleGameWins,
                    'competition_wins' => $competitionWins,
                    'total_wins' => $totalWins,
                ];
            }
        }

        usort($combinedLeaderboard, fn ($a, $b) => $b['total_wins'] <=> $a['total_wins']);

        return ['leaderboard' => $combinedLeaderboard];
    }

    public function getCustomerPurchases($identifier): Collection
    {
        $customer = Customer::query()
            ->where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();

        if (! $customer) {
            return collect();
        }

        return $customer->purchases()->get()->map(function ($purchase) {
            return [
                'id' => $purchase->id,
                'customer' => $purchase->customer->name,
                'amount' => $purchase->amount,
                'type' => $purchase->purchase_type,
                'value' => $purchase->value,
                'created_at' => $purchase->created_at->toDateTimeString(),
            ];
        });
    }

    public function updateCustomer($identifier, array $data): ?Customer
    {
        $customer = Customer::where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();

        if (! $customer) {
            return null;
        }

        $customer->update($data);

        return $customer;
    }

    public function updateCustomerWallet($identifier, float $amount): bool
    {
        $customer = Customer::query()
            ->where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();

        if (! $customer) {
            return false;
        }

        $customer->wallet->balance += $amount;
        $customer->wallet->save();

        return true;
    }

    public function deleteCustomer($identifier): bool
    {
        $customer = Customer::where('id', $identifier)
            ->orWhere('account_no', $identifier)
            ->first();

        if (! $customer) {
            return false;
        }

        $customer->wallet?->delete();
        $customer->delete();

        return true;
    }
}
