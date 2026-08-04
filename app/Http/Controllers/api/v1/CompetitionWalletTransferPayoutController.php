<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompetitionWalletTransferPayoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request) {
        try {
            $validated = $request->validate([
                'sender_competition_wallet_id' => 'required|integer',
                'receiver_competition_wallet_id' => 'required|integer',
            ]);

            $sender = CompetitionWallet::find($validated['sender_competition_wallet_id']);
            $receiver = CompetitionWallet::find($validated['receiver_competition_wallet_id']);

            if(!$sender || !$receiver) { return response()->json(['error' => "Invalid Competition Wallet(s)"], 404); }
            if ($sender->balance <= 0) { return response()->json(["error" => "Insufficient balance in Sender Competition Wallet"], 400); }
            if($sender->game_type != $receiver->game_type) { return response()->json(["error" => "Competition Wallets must have same game type"], 400); }
            if($sender->cmp_uid != $receiver->cmp_uid) { return response()->json(["error" => "Competition Wallets must have same competition"], 400); }
            if($sender->game_type == 1){
                return $this->handleTournamentPayout($sender, $receiver);
            } elseif ($sender->game_type == 2) {
                return $this->handleJackpotPayout($sender, $receiver);
            } else { return response()->json(["error" => "Unsupported game type"], 400); }
        } catch (\Exception $e) {
            // Log the error for debugging and support
            Log::error('Competition Wallet Transfer Payout Error', [
                'message' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            // Respond with a generic error message
            return response()->json([
                "error" => "An error occurred during the competition wallet payout process."
            ], 500);
        }
    }

    private function handleTournamentPayout($sender, $receiver) {
        DB::transaction(function () use ($sender, $receiver) {
            $totalBalance = $sender->balance;

            // Update Sender Competition Wallet Loss
            $sender->balance -= $totalBalance;
            $sender->level -= 1;
            $sender->save();

            // Record Competition Loss for Sender
            CompetitionTransaction::create([
                'competition_wallet_id' => $sender->id,
                'amount' => $totalBalance,
                'payment_type' => 'loss',
                'level' => $sender->level,
                'status' => 2, // Paid Out
            ]);


            // Update Receiver Competition Wallet Win
            $receiver->balance += $totalBalance;
            $receiver->level += 1;
            $receiver->save();

            // Record Competition Win for Receiver
            CompetitionTransaction::create([
                'competition_wallet_id' => $receiver->id,
                'amount' => $totalBalance,
                'payment_type' => 'win',
                'level' => $receiver->level,
                'status' => 2, // Paid Out
            ]);
        });
        return response()->json(['status' => 'success']);
    }

    private function handleJackpotPayout($sender, $receiver) {
        // Get total collected from all deposit transactions in this jackpot
        $totalCollected = CompetitionWallet::where('cmp_uid', $sender->cmp_uid)
            ->with(['transactions' => function ($query) {
                $query->where('payment_type', 'deposit');
            }])
            ->get()
            ->pluck('transactions')
            ->flatten()
            ->sum('amount');

        $currentLevel = $receiver->level;
        $nextLevel = $currentLevel + 1;
        $totalRounds = $receiver->jp_rounds; // 13, 17, or 21

        // Map levels based on the round type for the given jp_rounds
        $quarterLevel = match ($totalRounds) {
            13 => 11,
            17 => 15,
            21 => 19,
        };

        $semiFinalLevel = match ($totalRounds) {
            13 => 12,
            17 => 16,
            21 => 20,
        };

        $finalLevel = $totalRounds;

        // Determine payout percentages
        $receiverPayoutPercentage = match ($nextLevel) {
            $quarterLevel => 0,             // Win at quarter-finals — no payout yet
            $semiFinalLevel => 0,           // Win at semi-finals — no payout yet
            $finalLevel => 0.50,            // Win the finals — 50% of total collected
            default => 0                    // No payout for wins before QF/SF/Final
        };

        $senderPayoutPercentage = match ($nextLevel) {
            $quarterLevel => 0.025,         // Loser in QF
            $semiFinalLevel => 0.05,        // Loser in SF
            $finalLevel => 0.10,            // Loser in Final
            default => 0                    // No payout before QF
        };

        $receiverGets = round($totalCollected * $receiverPayoutPercentage, 2);
        $senderGets = round($totalCollected * $senderPayoutPercentage, 2);

        DB::transaction(function () use ($sender, $receiver, $receiverGets, $senderGets, $nextLevel) {
            if ($receiverGets > 0) {
                CompetitionTransaction::create([
                    'competition_wallet_id' => $receiver->id,
                    'amount' => $receiverGets,
                    'payment_type' => 'win',
                    'level' => $nextLevel,
                    'status' => 2,
                ]);
                $receiver->balance += $receiverGets;
            }

            if ($senderGets > 0) {
                CompetitionTransaction::create([
                    'competition_wallet_id' => $sender->id,
                    'amount' => $senderGets,
                    'payment_type' => 'loss',
                    'level' => $sender->level,
                    'status' => 2,
                ]);
                $sender->balance += $senderGets;
            }

            // Update the receiver's level (advance them)
            $receiver->level = $nextLevel;
            $receiver->save();

            // Loser remains on same level, just balance updated if any
            $sender->status = 3; // Competition Wallet Closed.
            $sender->save();
        });

        return response()->json(['status' => 'Success']);
    }
}
