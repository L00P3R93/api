<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function __construct(private LedgerService $ledgerService) {}

    public function listWallets(): Collection
    {
        return Wallet::with('customer')->get();
    }

    public function getWallet(int $id): ?Wallet
    {
        return Wallet::find($id);
    }

    public function updateWallet(int $id, array $data): ?Wallet
    {
        $wallet = Wallet::find($id);
        if (! $wallet) {
            return null;
        }

        $wallet->update($data);

        return $wallet;
    }

    public function reduceBalance(int $walletId, float $amount): array
    {
        return DB::transaction(function () use ($walletId, $amount) {
            $wallet = Wallet::lockForUpdate()->find($walletId);
            if (! $wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }
            if ($wallet->balance < $amount) {
                return ['success' => false, 'message' => 'Insufficient balance'];
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance -= $amount;
            $wallet->save();

            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
            ];
        });
    }

    public function addBalance(int $walletId, float $amount): array
    {
        return DB::transaction(function () use ($walletId, $amount) {
            $wallet = Wallet::lockForUpdate()->find($walletId);
            if (! $wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance += $amount;
            $wallet->save();

            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
            ];
        });
    }

    public function setBalance(int $walletId, float $amount): array
    {
        return DB::transaction(function () use ($walletId, $amount) {
            $wallet = Wallet::lockForUpdate()->find($walletId);
            if (! $wallet) {
                return ['success' => false, 'message' => 'Wallet not found'];
            }

            $balanceBefore = $wallet->balance;
            $wallet->balance = $amount;
            $wallet->save();

            return [
                'success' => true,
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
            ];
        });
    }

    public function transfer(int $senderWalletId, float $amount, ?int $receiverWalletId = null): array
    {
        if (! $receiverWalletId) {
            return ['success' => false, 'message' => 'Receiver wallet is required', 'status_code' => 400];
        }

        $wallet = Wallet::lockForUpdate()->find($senderWalletId);
        if (! $wallet) {
            return ['success' => false, 'message' => 'Wallet not found', 'status_code' => 404];
        }

        if ($wallet->balance < $amount) {
            return ['success' => false, 'message' => 'Insufficient balance', 'status_code' => 400];
        }

        $receiverWallet = Wallet::lockForUpdate()->find($receiverWalletId);
        if (! $receiverWallet) {
            return ['success' => false, 'message' => 'Receiver wallet not found', 'status_code' => 404];
        }

        DB::transaction(function () use ($wallet, $receiverWallet, $amount) {
            $walletTransaction = WalletTransaction::create([
                'transaction_type' => 'w2w',
                'sender_id' => $wallet->id,
                'receiver_id' => $receiverWallet->id,
                'initiator_id' => 1,
                'amount' => $amount,
            ]);

            [$senderEntry, $receiverEntry] = $this->ledgerService->recordWalletTransfer(
                $walletTransaction,
                $wallet,
                $receiverWallet,
                (float) $amount
            );

            $walletTransaction->update([
                'status' => 2,
                'sender_balance_before' => $senderEntry->balance_before,
                'sender_balance_after' => $senderEntry->balance_after,
                'receiver_balance_before' => $receiverEntry->balance_before,
                'receiver_balance_after' => $receiverEntry->balance_after,
            ]);
        });

        return ['success' => true, 'status_code' => 200];
    }
}
