<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameWalletService
{
    public function __construct(private LedgerService $ledgerService) {}

    public function listGameWallets(): Collection
    {
        return GameWallet::all();
    }

    public function createGameWallet(array $data): GameWallet
    {
        return GameWallet::create($data);
    }

    public function getGameWallet($identifier): ?GameWallet
    {
        return GameWallet::where('id', $identifier)
            ->orWhere('game_id', $identifier)
            ->first();
    }

    public function getGameIncome(?string $startDate = null, ?string $endDate = null): array
    {
        return DB::table('game_wallets as G')
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
            ->whereBetween('T.created_at', [$startDate, $endDate])
            ->groupBy('players')
            ->orderBy('players')
            ->get()
            ->toArray();
    }

    public function getGameResults(): array
    {
        $rawResults = DB::table('game_wallets as GW')
            ->join('game_transactions AS GT', 'GT.game_wallet_id', '=', 'GW.id')
            ->join('customers AS C', 'GT.customer_id', '=', 'C.id')
            ->where('GT.payment_type', 'payout')
            ->select(
                'GW.id', 'GW.game_id', 'GT.customer_id', 'C.name', 'GT.amount', 'GT.created_at',
                DB::raw("(SELECT COUNT(*) FROM game_transactions WHERE payment_type = 'deposit' AND game_wallet_id = GW.id) AS players"),
                DB::raw("(SELECT SUM(amount) FROM game_transactions WHERE payment_type = 'deposit' AND game_wallet_id = GW.id) AS total_bet")
            )
            ->get();

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

        return $grouped->values()->toArray();
    }

    public function updateGameWallet($id, array $data): ?GameWallet
    {
        $gameWallet = GameWallet::where('id', $id)->first();
        if (! $gameWallet) {
            return null;
        }
        $gameWallet->update($data);

        return $gameWallet;
    }

    public function deleteGameWallet($id): bool
    {
        $gameWallet = GameWallet::where('id', $id)->first();
        if (! $gameWallet) {
            return false;
        }

        return $gameWallet->delete();
    }

    public function processGameWithdrawal(int $gameWalletId, int $customerId): array
    {
        $gameWallet = GameWallet::find($gameWalletId);
        if (! $gameWallet) {
            return ['error' => 'Game Wallet not found', 'status' => 404];
        }

        if ($gameWallet->status !== '1') {
            return ['error' => 'Game Wallet is not open for withdrawal', 'status' => 400];
        }

        $customer = Customer::where('id', $customerId)
            ->orWhere('account_no', $customerId)
            ->first();
        if (! $customer) {
            return ['error' => 'Customer not found', 'status' => 404];
        }

        $wallet = $customer->wallet;
        if (! $wallet) {
            return ['error' => 'Customer wallet not found', 'status' => 404];
        }

        $totalBalance = $gameWallet->balance;
        $houseShare = ceil($totalBalance * 0.20);
        $playerShare = $totalBalance - $houseShare;

        DB::transaction(function () use ($gameWallet, $customer, $wallet, $playerShare, $houseShare, $totalBalance) {
            $playerTransaction = GameTransaction::create([
                'game_wallet_id' => $gameWallet->id,
                'customer_id' => $customer->id,
                'amount' => $playerShare,
                'payment_type' => 'payout',
                'status' => 2,
            ]);

            $playerEntry = $this->ledgerService->recordGamePayout(
                $playerTransaction,
                $wallet,
                (float) $playerShare
            );

            $playerTransaction->update([
                'wallet_balance_before' => $playerEntry->balance_before,
                'wallet_balance_after' => $playerEntry->balance_after,
                'game_balance_before' => $totalBalance,
                'game_balance_after' => 0,
            ]);

            if ($houseShare > 0) {
                $houseWallet = Wallet::find(config('wallets.house_wallet_id', 1));

                $houseTransaction = GameTransaction::create([
                    'game_wallet_id' => $gameWallet->id,
                    'customer_id' => 1,
                    'amount' => $houseShare,
                    'payment_type' => 'payout',
                    'status' => 2,
                ]);

                $houseEntry = $this->ledgerService->recordHouseCut(
                    $houseWallet,
                    (float) $houseShare,
                    'game_withdrawal'
                );

                $houseTransaction->update([
                    'wallet_balance_before' => $houseEntry->balance_before,
                    'wallet_balance_after' => $houseEntry->balance_after,
                    'game_balance_before' => $totalBalance,
                    'game_balance_after' => 0,
                ]);
            }

            $gameWallet->balance = 0;
            $gameWallet->status = 3;
            $gameWallet->save();
        });

        return ['status' => 'success'];
    }

    public function listGameTransactions(): Collection
    {
        return GameTransaction::with(['gameWallet'])->get();
    }

    public function createGameTransaction(array $data): GameTransaction|false
    {
        $gameWallet = GameWallet::find($data['game_wallet_id']);
        if (! $gameWallet) {
            return false;
        }

        $customer = Customer::with('wallet')->find($data['customer_id']);
        if (! $customer || ! $customer->wallet || $customer->wallet->balance < $data['amount']) {
            return false;
        }

        $gameTransaction = DB::transaction(function () use ($gameWallet, $data) {
            $customer = Customer::with('wallet')->find($data['customer_id']);
            $wallet = $customer->wallet;

            $gameTransaction = GameTransaction::create($data);

            [$customerEntry, $gameEntry] = $this->ledgerService->recordGameBet(
                $gameTransaction,
                $wallet,
                $gameWallet,
                (float) $data['amount']
            );

            $gameTransaction->update([
                'wallet_balance_before' => $customerEntry->balance_before,
                'wallet_balance_after' => $customerEntry->balance_after,
                'game_balance_before' => $gameEntry->balance_before,
                'game_balance_after' => $gameEntry->balance_after,
            ]);

            return $gameTransaction;
        });

        return $gameTransaction;
    }

    public function getGameTransaction(int $id): ?GameTransaction
    {
        return GameTransaction::where('id', $id)->first();
    }

    public function updateGameTransaction(int $id, array $data): ?GameTransaction
    {
        $gameTransaction = GameTransaction::where('id', $id)->first();
        if (! $gameTransaction) {
            return null;
        }
        $gameTransaction->update($data);

        return $gameTransaction;
    }

    public function deleteGameTransaction(int $id): bool
    {
        $gameTransaction = GameTransaction::where('id', $id)->first();
        if (! $gameTransaction) {
            return false;
        }

        return $gameTransaction->delete();
    }

    public function processDropConnection(
        int $gameWalletId,
        array $players,
        array $active,
        array $dropped,
        bool $gameStarted
    ): array {
        $gameWallet = GameWallet::where('id', $gameWalletId)
            ->orWhere('game_id', $gameWalletId)
            ->first();

        if (! $gameWallet) {
            return ['error' => 'Game Wallet not found', 'status' => 404];
        }

        $playersCount = count($players);
        $activeCount = count($active);
        $droppedCount = count($dropped);

        if ($playersCount == 0) {
            return ['error' => 'No players', 'status' => 400];
        }
        if ($activeCount == 0 && $gameStarted) {
            return ['error' => 'No players active', 'status' => 400];
        }
        if ($droppedCount == 0) {
            return ['error' => 'No players dropped', 'status' => 400];
        }

        if ($gameStarted) {
            $invalidActive = array_diff($active, $players);
            if (! empty($invalidActive)) {
                return [
                    'error' => 'Some active players are not in the players list',
                    'invalid' => $invalidActive,
                    'status' => 400,
                ];
            }
        }

        $invalidDropped = array_diff($dropped, $players);
        if (! empty($invalidDropped)) {
            return [
                'error' => 'Some dropped players are not in the players list',
                'invalid' => $invalidDropped,
                'status' => 400,
            ];
        }

        $payouts = [];

        if ($gameStarted) {
            if ($droppedCount > 1) {
                $payouts = DB::transaction(function () use ($gameWallet, $active, $activeCount, $dropped) {
                    $houseWalletId = 1;
                    $totalBalance = 0;

                    foreach ($dropped as $droppedPlayer) {
                        $totalBalance += $gameWallet->gameTransactions()
                            ->where('customer_id', $droppedPlayer)
                            ->where('payment_type', 'deposit')
                            ->sum('amount');
                    }

                    return $this->processPayouts($totalBalance, $activeCount, $gameWallet, $active, $houseWalletId, true);
                });
            } else {
                $payouts = DB::transaction(function () use ($gameWallet, $active, $activeCount, $dropped) {
                    $houseWalletId = 1;
                    $totalBalance = $gameWallet->gameTransactions()
                        ->where('customer_id', $dropped[0])
                        ->where('payment_type', 'deposit')
                        ->sum('amount');

                    return $this->processPayouts($totalBalance, $activeCount, $gameWallet, $active, $houseWalletId, true);
                });
            }
        } else {
            $payouts = DB::transaction(function () use ($gameWallet, $dropped) {
                $payouts = [];

                foreach ($dropped as $playerId) {
                    $amount = $gameWallet->gameTransactions()
                        ->where('customer_id', $playerId)
                        ->where('payment_type', 'deposit')
                        ->sum('amount');

                    if ($amount > 0) {
                        $this->processRefund($gameWallet, $playerId, $amount);
                        $payouts[] = [
                            'player_id' => $playerId,
                            'amount' => $amount,
                            'type' => 'refund',
                        ];
                    }
                }

                return $payouts;
            });
        }

        $gameWallet->status = 3;
        $gameWallet->save();

        return [
            'status' => 'success',
            'payouts' => $payouts,
        ];
    }

    public function processRefund(GameWallet $gameWallet, int $playerId, float $amount): void
    {
        $playerWallet = Wallet::where('customer_id', $playerId)->first();
        if (! $playerWallet) {
            return;
        }

        $gameTransaction = GameTransaction::create([
            'game_wallet_id' => $gameWallet->id,
            'customer_id' => $playerId,
            'amount' => $amount,
            'payment_type' => 'refund|dropped',
            'status' => 2,
        ]);

        $ledgerEntry = $this->ledgerService->recordRefund(
            $gameTransaction,
            $playerWallet,
            $amount,
            'game_drop_refund'
        );

        $gameTransaction->update([
            'wallet_balance_before' => $ledgerEntry->balance_before,
            'wallet_balance_after' => $ledgerEntry->balance_after,
        ]);
    }

    public function processPayouts(
        float $totalBalance,
        int $activeCount,
        GameWallet $gameWallet,
        array $active,
        int $houseWalletId,
        bool $gameStarted = true
    ): array {
        if ($gameStarted && $activeCount == 0) {
            throw new \Exception('Active player count cannot be zero when game has started.');
        }

        $houseShare = $gameStarted ? ceil($totalBalance * 0.10) : 0;
        $playerShare = $gameStarted ? floor(($totalBalance - $houseShare) / $activeCount) : 0;

        $payouts = [];

        if ($gameStarted && $houseShare > 0) {
            $houseWallet = Wallet::find($houseWalletId);

            $houseTransaction = GameTransaction::create([
                'game_wallet_id' => $gameWallet->id,
                'customer_id' => 1,
                'amount' => $houseShare,
                'payment_type' => 'payout|dropped',
                'status' => 2,
            ]);

            $houseEntry = $this->ledgerService->recordHouseCut(
                $houseWallet,
                (float) $houseShare,
                'game_drop_payout'
            );

            $houseTransaction->update([
                'wallet_balance_before' => $houseEntry->balance_before,
                'wallet_balance_after' => $houseEntry->balance_after,
            ]);
        }

        if ($gameStarted) {
            foreach ($active as $activePlayer) {
                $activeCustomer = Customer::find($activePlayer);
                if (! $activeCustomer) {
                    continue;
                }

                $wallet = $activeCustomer->wallet;
                if (! $wallet) {
                    throw new \Exception('Player wallet not found.');
                }

                $payoutTransaction = GameTransaction::create([
                    'game_wallet_id' => $gameWallet->id,
                    'customer_id' => $activeCustomer->id,
                    'amount' => $playerShare,
                    'payment_type' => 'payout|dropped',
                    'status' => 2,
                ]);

                $ledgerEntry = $this->ledgerService->recordGamePayout(
                    $payoutTransaction,
                    $wallet,
                    (float) $playerShare
                );

                $payoutTransaction->update([
                    'wallet_balance_before' => $ledgerEntry->balance_before,
                    'wallet_balance_after' => $ledgerEntry->balance_after,
                ]);

                $payouts[] = [
                    'player_id' => $activeCustomer->id,
                    'amount' => $playerShare,
                    'type' => 'payout',
                ];
            }
        }

        return $payouts;
    }
}
