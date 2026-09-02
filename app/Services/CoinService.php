<?php

namespace App\Services;

use App\Models\Coin;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CoinService
{
    public function __construct(private LedgerService $ledgerService) {}

    public function buyCoins(int $customerId, float $amount): array
    {
        $customer = Customer::where('id', $customerId)->orWhere('account_no', $customerId)->first();
        if (! $customer) {
            return ['success' => false, 'message' => 'Customer not found', 'status_code' => 404];
        }

        $wallet = $customer->wallet;
        if (! $wallet) {
            return ['success' => false, 'message' => 'Wallet not found', 'status_code' => 404];
        }

        if ($wallet->balance < $amount) {
            return ['success' => false, 'message' => 'Insufficient balance in Wallet', 'status_code' => 400];
        }

        $exchangeRate = 0.04;
        $coinsToBuy = floor($amount / $exchangeRate);

        $coinWallet = null;
        DB::transaction(function () use ($wallet, $customer, $coinsToBuy, $amount, &$coinWallet) {
            $coinWallet = Coin::firstOrCreate(
                ['customer_id' => $customer->id],
                ['coins' => 0, 'status' => 1]
            );

            $this->ledgerService->recordCoinPurchase(
                null,
                $wallet,
                $coinWallet,
                (float) $amount,
                $coinsToBuy
            );
        });

        return [
            'success' => true,
            'message' => 'Coins purchased successfully',
            'wallet_balance' => $wallet->balance,
            'coins' => $coinWallet->coins,
            'status_code' => 200,
        ];
    }

    public function exchangeCoins(int $coinWalletId, ?int $coins = null): array
    {
        $coinWallet = Coin::find($coinWalletId);
        if (! $coinWallet) {
            return ['success' => false, 'message' => 'Coin Wallet not found', 'status_code' => 404];
        }

        $coinsToExchange = $coins ?? $coinWallet->coins;
        if ($coinWallet->coins < $coinsToExchange) {
            return ['success' => false, 'message' => 'Insufficient coins in Coin Wallet', 'status_code' => 400];
        }

        $wallet = $coinWallet->customer->wallet;
        if (! $wallet) {
            return ['success' => false, 'message' => 'Wallet not found', 'status_code' => 404];
        }

        $exchangeRate = 0.04;
        $amountToExchange = $coinsToExchange * $exchangeRate;

        $this->ledgerService->recordCoinExchange(
            null,
            $wallet,
            $coinWallet,
            (float) $amountToExchange,
            (int) $coinsToExchange
        );

        return [
            'success' => true,
            'message' => 'Coins exchanged successfully',
            'status_code' => 200,
        ];
    }

    public function transferCoins(int $senderCoinWalletId, int $coins, ?int $receiverCoinWalletId = null): array
    {
        $coinWallet = Coin::find($senderCoinWalletId);
        if (! $coinWallet) {
            return ['success' => false, 'message' => 'Coin Wallet not found', 'status_code' => 404];
        }

        if ($coinWallet->coins < $coins) {
            return ['success' => false, 'message' => 'Insufficient coins in Sender Wallet Coin', 'status_code' => 400];
        }

        $receiverCoinWallet = Coin::find($receiverCoinWalletId ?? 1);
        if (! $receiverCoinWallet) {
            return ['success' => false, 'message' => 'Receiver coin wallet not found', 'status_code' => 404];
        }

        $this->ledgerService->recordCoinTransfer(
            null,
            $coinWallet,
            $receiverCoinWallet,
            (int) $coins
        );

        return [
            'success' => true,
            'message' => 'Transfer successful',
            'status_code' => 200,
        ];
    }
}
