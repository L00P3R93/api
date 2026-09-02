<?php

namespace App\Services;

use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use Illuminate\Support\Facades\DB;

class CompetitionPayoutService
{
    public function __construct(private LedgerService $ledgerService) {}

    public function processPayout(int $senderId, int $receiverId): array
    {
        $sender = CompetitionWallet::find($senderId);
        $receiver = CompetitionWallet::find($receiverId);

        if (! $sender || ! $receiver) {
            throw new \InvalidArgumentException('Invalid Competition Wallet(s)');
        }
        if ($sender->balance <= 0) {
            throw new \InvalidArgumentException('Insufficient balance in Sender Competition Wallet');
        }
        if ($sender->game_type != $receiver->game_type) {
            throw new \InvalidArgumentException('Competition Wallets must have same game type');
        }
        if ($sender->cmp_uid != $receiver->cmp_uid) {
            throw new \InvalidArgumentException('Competition Wallets must have same competition');
        }

        if ($sender->game_type == 1) {
            return $this->handleTournamentPayout($sender, $receiver);
        } elseif ($sender->game_type == 2) {
            return $this->handleJackpotPayout($sender, $receiver);
        }

        throw new \InvalidArgumentException('Unsupported game type');
    }

    public function handleTournamentPayout(CompetitionWallet $sender, CompetitionWallet $receiver): array
    {
        DB::transaction(function () use ($sender, $receiver) {
            $totalBalance = $sender->balance;

            $senderTransaction = CompetitionTransaction::create([
                'competition_wallet_id' => $sender->id,
                'amount' => $totalBalance,
                'payment_type' => 'loss',
                'level' => $sender->level,
                'status' => 2,
            ]);

            $sender->balance -= $totalBalance;
            $sender->level -= 1;
            $sender->save();

            $senderTransaction->update([
                'competition_wallet_balance_before' => $senderTransaction->competition_wallet_balance_before ?? $sender->balance + $totalBalance,
                'competition_wallet_balance_after' => $sender->balance,
            ]);

            $receiverTransaction = CompetitionTransaction::create([
                'competition_wallet_id' => $receiver->id,
                'amount' => $totalBalance,
                'payment_type' => 'win',
                'level' => $receiver->level,
                'status' => 2,
            ]);

            $receiver->balance += $totalBalance;
            $receiver->level += 1;
            $receiver->save();

            $receiverTransaction->update([
                'competition_wallet_balance_before' => $receiverTransaction->competition_wallet_balance_before ?? $receiver->balance - $totalBalance,
                'competition_wallet_balance_after' => $receiver->balance,
            ]);
        });

        return ['status' => 'success'];
    }

    public function handleJackpotPayout(CompetitionWallet $sender, CompetitionWallet $receiver): array
    {
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
        $totalRounds = $receiver->jp_rounds;

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

        $receiverPayoutPercentage = match ($nextLevel) {
            $quarterLevel => 0,
            $semiFinalLevel => 0,
            $finalLevel => 0.50,
            default => 0
        };

        $senderPayoutPercentage = match ($nextLevel) {
            $quarterLevel => 0.025,
            $semiFinalLevel => 0.05,
            $finalLevel => 0.10,
            default => 0
        };

        $receiverGets = round($totalCollected * $receiverPayoutPercentage, 2);
        $senderGets = round($totalCollected * $senderPayoutPercentage, 2);

        DB::transaction(function () use ($sender, $receiver, $receiverGets, $senderGets, $nextLevel) {
            if ($receiverGets > 0) {
                $receiverTransaction = CompetitionTransaction::create([
                    'competition_wallet_id' => $receiver->id,
                    'amount' => $receiverGets,
                    'payment_type' => 'win',
                    'level' => $nextLevel,
                    'status' => 2,
                ]);

                $receiverBalanceBefore = $receiver->balance;
                $receiver->balance += $receiverGets;
                $receiver->save();

                $receiverTransaction->update([
                    'competition_wallet_balance_before' => $receiverBalanceBefore,
                    'competition_wallet_balance_after' => $receiver->balance,
                ]);
            }

            if ($senderGets > 0) {
                $senderTransaction = CompetitionTransaction::create([
                    'competition_wallet_id' => $sender->id,
                    'amount' => $senderGets,
                    'payment_type' => 'loss',
                    'level' => $sender->level,
                    'status' => 2,
                ]);

                $senderBalanceBefore = $sender->balance;
                $sender->balance += $senderGets;
                $sender->save();

                $senderTransaction->update([
                    'competition_wallet_balance_before' => $senderBalanceBefore,
                    'competition_wallet_balance_after' => $sender->balance,
                ]);
            }

            $receiver->level = $nextLevel;
            $receiver->save();

            $sender->status = 3;
            $sender->save();
        });

        return ['status' => 'Success'];
    }
}
