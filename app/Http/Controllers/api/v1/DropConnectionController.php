<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GameTransaction;
use App\Models\GameWallet;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DropConnectionController extends Controller {

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $encryptedIdentifier){
        try {
            $gameWallet = GameWallet::where('id', $encryptedIdentifier)->orWhere('game_id', $encryptedIdentifier)->first();
            if(!$gameWallet) { return response()->json(["status" => "Game Wallet not found"], 404);}

            $players = $request->players;
            $active = $request->active;
            $dropped = $request->dropped;
            $gameStarted = $request->input('game', 0) == 1; // Default to 0 (game not started) if not provided

            $playersCount = count($request->players);
            $activeCount = count($request->active);
            $droppedCount = count($request->dropped);

            if($playersCount == 0) return response()->json(["status" => "No players"], 400);
            if($activeCount == 0 && $gameStarted) return response()->json(["status" => "No players active"], 400);
            if($droppedCount == 0) return response()->json(["status" => "No players dropped"], 400);

            // Check if all active players are in the players array (only if game has started)
            if ($gameStarted) {
                $invalidActive = array_diff($active, $players);
                if (!empty($invalidActive)) {
                    return response()->json([
                        "status" => "Some active players are not in the players list",
                        "invalid" => $invalidActive
                    ], 400);
                }
            }

            // Check if all dropped players are in the players array
            $invalidDropped = array_diff($dropped, $players);
            if (!empty($invalidDropped)) {
                return response()->json([
                    "status" => "Some dropped players are not in the players list",
                    "invalid" => $invalidDropped
                ], 400);
            }

            $payouts = [];

            if ($gameStarted) {
                // Game has started, use the existing logic with house cut
                if($droppedCount > 1){
                    $payouts = DB::transaction(function () use ($gameWallet, $active, $activeCount, $dropped, $droppedCount) {
                        $houseWalletId = 1;
                        $totalBalance = 0;
                        foreach ($dropped as $droppedPlayer) {
                            $totalBalance += $gameWallet->transactions()->where('customer_id', $droppedPlayer)->where('payment_type', 'deposit')->sum('amount');
                        }
                        return $this->processPayouts($totalBalance, $activeCount, $gameWallet, $active, $houseWalletId, true);
                    });
                } else {
                    $payouts = DB::transaction(function () use ($gameWallet, $active, $activeCount, $dropped) {
                        $houseWalletId = 1;
                        $totalBalance = $gameWallet->transactions()->where('customer_id', $dropped[0])->where('payment_type', 'deposit')->sum('amount');
                        return $this->processPayouts($totalBalance, $activeCount, $gameWallet, $active, $houseWalletId, true);
                    });
                }
            } else {
                // Game hasn't started, refund dropped players' full amount
                $payouts = DB::transaction(function () use ($gameWallet, $dropped) {
                    $houseWalletId = 1;
                    $payouts = [];

                    foreach ($dropped as $playerId) {
                        $amount = $gameWallet->transactions()
                            ->where('customer_id', $playerId)
                            ->where('payment_type', 'deposit')
                            ->sum('amount');

                        if ($amount > 0) {
                            $this->processRefund($gameWallet, $playerId, $amount);
                            $payouts[] = [
                                'player_id' => $playerId,
                                'amount' => $amount,
                                'type' => 'refund'
                            ];
                        }
                    }

                    return $payouts;
                });
            }

            // Mark game wallet as closed if no active players left or game hasn't started
            $gameWallet->status = 3;
            $gameWallet->save();

            // Respond with success
            return response()->json([
                'status' => 'success',
                'payouts' => $payouts
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Drop Connection Error: ", ['error' => $e->getMessage()]);
            return response()->json(["status" => "Error processing request: " . $e->getMessage()], 500);
        }
    }

    /**
     * Process refund for a player (game not started)
     */
    private function processRefund(GameWallet $gameWallet, $playerId, $amount): void
    {
        // Create refund transaction
        GameTransaction::create([
            'game_wallet_id' => $gameWallet->id,
            'customer_id' => $playerId,
            'amount' => $amount,
            'payment_type' => 'refund|dropped',
            'status' => 2, // Completed
        ]);

        // Add amount back to player's wallet
        $playerWallet = Wallet::where('customer_id', $playerId)->first();
        if ($playerWallet) {
            $playerWallet->balance += $amount;
            $playerWallet->save();
        }
    }

    /**
     * Calculate and create transactions for house and active players.
     *
     * @param int $totalBalance The total balance to be distributed.
     * @param int $activeCount The number of active players.
     * @param GameWallet $gameWallet The game wallet.
     * @param array $active The array of active players.
     * @param int $houseWalletId The wallet ID of the house.
     * @param bool $gameStarted Whether the game has started.
     * @return array An array of payouts with player ID and player share.
     * @throws \Exception If the active player count is zero when game has started.
     */
    public function processPayouts(int $totalBalance, int $activeCount, GameWallet $gameWallet, array $active, int $houseWalletId, bool $gameStarted = true): array {
        if($gameStarted && $activeCount == 0) {
            throw new \Exception("Active player count cannot be zero when game has started.");
        }

        $houseShare = $gameStarted ? ceil($totalBalance * 0.10) : 0;
        $playerShare = $gameStarted ? floor(($totalBalance - $houseShare) / $activeCount) : 0;

        $payouts = [];

        // Only take house share if game has started
        if ($gameStarted && $houseShare > 0) {
            GameTransaction::create([
                'game_wallet_id' => $gameWallet->id,
                'customer_id' => 1,
                'amount' => $houseShare,
                'payment_type' => 'payout|dropped',
                'status' => 2, // Completed
            ]);

            $houseWallet = Wallet::find($houseWalletId);
            if ($houseWallet) {
                $houseWallet->balance += $houseShare;
                $houseWallet->save();
            }
        }

        // Process payouts to active players if game has started
        if ($gameStarted) {
            foreach ($active as $activePlayer) {
                $activeCustomer = Customer::find($activePlayer);
                if (!$activeCustomer) continue;

                $wallet = $activeCustomer->wallet;
                if(!$wallet) throw new \Exception("Player wallet not found.");

                GameTransaction::create([
                    'game_wallet_id' => $gameWallet->id,
                    'customer_id' => $activeCustomer->id,
                    'amount' => $playerShare,
                    'payment_type' => 'payout|dropped',
                    'status' => 2, // Completed
                ]);

                $wallet->balance += $playerShare;
                $wallet->save();

                $payouts[] = [
                    'player_id' => $activeCustomer->id,
                    'amount' => $playerShare,
                    'type' => 'payout'
                ];
            }
        }

        return $payouts;
    }
}
