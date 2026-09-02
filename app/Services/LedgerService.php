<?php

namespace App\Services;

use App\Models\CompetitionTransaction;
use App\Models\CompetitionWallet;
use App\Models\LedgerEntry;
use App\Models\PendingBalance;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

class LedgerService
{
    public function recordDeposit($deposit, Wallet $wallet, float $amount): LedgerEntry
    {
        $balanceBefore = $wallet->balance;
        $wallet->balance += $amount;
        $wallet->save();

        return $this->createEntry(
            entryType: 'deposit',
            referenceable: $deposit,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: 0,
            credit: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $wallet->balance
        );
    }

    public function recordWithdrawal($withdraw, Wallet $wallet, float $amount): LedgerEntry
    {
        $balanceBefore = $wallet->balance;
        $wallet->balance -= $amount;
        $wallet->save();

        return $this->createEntry(
            entryType: 'withdrawal',
            referenceable: $withdraw,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: $amount,
            credit: 0,
            balanceBefore: $balanceBefore,
            balanceAfter: $wallet->balance
        );
    }

    public function recordGameBet($gameTransaction, Wallet $customerWallet, $gameWallet, float $amount): array
    {
        $customerBalanceBefore = $customerWallet->balance;
        $gameBalanceBefore = $gameWallet->balance;

        $customerWallet->balance -= $amount;
        $customerWallet->save();

        $gameWallet->balance += $amount;
        $gameWallet->save();

        $customerEntry = $this->createEntry(
            entryType: 'game_bet',
            referenceable: $gameTransaction,
            wallet: $customerWallet,
            customerId: $customerWallet->customer_id,
            debit: $amount,
            credit: 0,
            balanceBefore: $customerBalanceBefore,
            balanceAfter: $customerWallet->balance,
            metadata: ['game_wallet_id' => $gameWallet->id]
        );

        $gameEntry = $this->createEntry(
            entryType: 'game_bet',
            referenceable: $gameTransaction,
            wallet: $gameWallet,
            customerId: null,
            debit: 0,
            credit: $amount,
            balanceBefore: $gameBalanceBefore,
            balanceAfter: $gameWallet->balance,
            metadata: ['customer_wallet_id' => $customerWallet->id]
        );

        return [$customerEntry, $gameEntry];
    }

    public function recordGameBetWithHouseCut($gameTransaction, Wallet $customerWallet, $gameWallet, float $amount, float $houseCut): array
    {
        $customerBalanceBefore = $customerWallet->balance;
        $gameBalanceBefore = $gameWallet->balance;
        $netCredit = $amount - $houseCut;

        $customerWallet->balance -= $amount;
        $customerWallet->save();

        $gameWallet->balance += $netCredit;
        $gameWallet->save();

        $customerEntry = $this->createEntry(
            entryType: 'game_bet',
            referenceable: $gameTransaction,
            wallet: $customerWallet,
            customerId: $customerWallet->customer_id,
            debit: $amount,
            credit: 0,
            balanceBefore: $customerBalanceBefore,
            balanceAfter: $customerWallet->balance,
            metadata: ['game_wallet_id' => $gameWallet->id, 'house_cut' => $houseCut]
        );

        $gameEntry = $this->createEntry(
            entryType: 'game_bet',
            referenceable: $gameTransaction,
            wallet: $gameWallet,
            customerId: null,
            debit: 0,
            credit: $netCredit,
            balanceBefore: $gameBalanceBefore,
            balanceAfter: $gameWallet->balance,
            metadata: ['customer_wallet_id' => $customerWallet->id, 'house_cut' => $houseCut]
        );

        return [$customerEntry, $gameEntry];
    }

    public function recordGamePayout($gameTransaction, Wallet $winnerWallet, float $amount): LedgerEntry
    {
        $balanceBefore = $winnerWallet->balance;
        $winnerWallet->balance += $amount;
        $winnerWallet->save();

        return $this->createEntry(
            entryType: 'game_payout',
            referenceable: $gameTransaction,
            wallet: $winnerWallet,
            customerId: $winnerWallet->customer_id,
            debit: 0,
            credit: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $winnerWallet->balance
        );
    }

    public function recordCompetitionBet(
        CompetitionTransaction $ct,
        Wallet $wallet,
        CompetitionWallet $cw,
        float $amount,
        float $houseCut
    ): array {
        $walletBalanceBefore = $wallet->balance;
        $cwBalanceBefore = $cw->balance;

        $wallet->balance -= $amount;
        $wallet->save();

        $cw->balance += ($amount - $houseCut);
        $cw->save();

        $walletEntry = $this->createEntry(
            entryType: 'competition_bet',
            referenceable: $ct,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: $amount,
            credit: 0,
            balanceBefore: $walletBalanceBefore,
            balanceAfter: $wallet->balance,
            metadata: ['competition_wallet_id' => $cw->id, 'house_cut' => $houseCut]
        );

        $cwEntry = $this->createEntry(
            entryType: 'competition_bet',
            referenceable: $ct,
            wallet: $cw,
            customerId: null,
            debit: 0,
            credit: $amount - $houseCut,
            balanceBefore: $cwBalanceBefore,
            balanceAfter: $cw->balance,
            metadata: ['customer_wallet_id' => $wallet->id, 'house_cut' => $houseCut]
        );

        return [$walletEntry, $cwEntry];
    }

    public function recordCompetitionPayout(CompetitionTransaction $ct, Wallet $wallet, float $amount): LedgerEntry
    {
        $balanceBefore = $wallet->balance;
        $wallet->balance += $amount;
        $wallet->save();

        return $this->createEntry(
            entryType: 'competition_payout',
            referenceable: $ct,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: 0,
            credit: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $wallet->balance
        );
    }

    public function recordWalletTransfer(WalletTransaction $wt, Wallet $sender, Wallet $receiver, float $amount): array
    {
        $senderBalanceBefore = $sender->balance;
        $receiverBalanceBefore = $receiver->balance;

        $sender->balance -= $amount;
        $sender->save();

        $receiver->balance += $amount;
        $receiver->save();

        $senderEntry = $this->createEntry(
            entryType: 'wallet_transfer',
            referenceable: $wt,
            wallet: $sender,
            customerId: $sender->customer_id,
            debit: $amount,
            credit: 0,
            balanceBefore: $senderBalanceBefore,
            balanceAfter: $sender->balance,
            metadata: ['direction' => 'outgoing', 'receiver_wallet_id' => $receiver->id]
        );

        $receiverEntry = $this->createEntry(
            entryType: 'wallet_transfer',
            referenceable: $wt,
            wallet: $receiver,
            customerId: $receiver->customer_id,
            debit: 0,
            credit: $amount,
            balanceBefore: $receiverBalanceBefore,
            balanceAfter: $receiver->balance,
            metadata: ['direction' => 'incoming', 'sender_wallet_id' => $sender->id]
        );

        return [$senderEntry, $receiverEntry];
    }

    public function recordCoinPurchase($coinBuy, Wallet $wallet, $coinWallet, float $amount, int $coins): array
    {
        $walletBalanceBefore = $wallet->balance;
        $coinBalanceBefore = $coinWallet->coins;

        $wallet->balance -= $amount;
        $wallet->save();

        $coinWallet->coins += $coins;
        $coinWallet->save();

        $walletEntry = $this->createEntry(
            entryType: 'coin_purchase',
            referenceable: $coinBuy,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: $amount,
            credit: 0,
            balanceBefore: $walletBalanceBefore,
            balanceAfter: $wallet->balance,
            metadata: ['coins_purchased' => $coins]
        );

        $coinEntry = $this->createEntry(
            entryType: 'coin_purchase',
            referenceable: $coinBuy,
            wallet: $coinWallet,
            customerId: null,
            debit: 0,
            credit: $coins,
            balanceBefore: $coinBalanceBefore,
            balanceAfter: $coinWallet->coins,
            metadata: ['amount_paid' => $amount]
        );

        return [$walletEntry, $coinEntry];
    }

    public function recordCoinExchange($coinExchange, Wallet $wallet, $coinWallet, float $amount, int $coins): array
    {
        $walletBalanceBefore = $wallet->balance;
        $coinBalanceBefore = $coinWallet->coins;

        $coinWallet->coins -= $coins;
        $coinWallet->save();

        $wallet->balance += $amount;
        $wallet->save();

        $walletEntry = $this->createEntry(
            entryType: 'coin_exchange',
            referenceable: $coinExchange,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: 0,
            credit: $amount,
            balanceBefore: $walletBalanceBefore,
            balanceAfter: $wallet->balance,
            metadata: ['coins_exchanged' => $coins]
        );

        $coinEntry = $this->createEntry(
            entryType: 'coin_exchange',
            referenceable: $coinExchange,
            wallet: $coinWallet,
            customerId: null,
            debit: $coins,
            credit: 0,
            balanceBefore: $coinBalanceBefore,
            balanceAfter: $coinWallet->coins,
            metadata: ['amount_credited' => $amount]
        );

        return [$walletEntry, $coinEntry];
    }

    public function recordCoinTransfer($coinTransfer, $senderCoinWallet, $receiverCoinWallet, int $coins): array
    {
        $senderBalanceBefore = $senderCoinWallet->coins;
        $receiverBalanceBefore = $receiverCoinWallet->coins;

        $senderCoinWallet->coins -= $coins;
        $senderCoinWallet->save();

        $receiverCoinWallet->coins += $coins;
        $receiverCoinWallet->save();

        $senderEntry = $this->createEntry(
            entryType: 'coin_transfer',
            referenceable: $coinTransfer,
            wallet: $senderCoinWallet,
            customerId: null,
            debit: $coins,
            credit: 0,
            balanceBefore: $senderBalanceBefore,
            balanceAfter: $senderCoinWallet->coins,
            metadata: ['direction' => 'outgoing', 'receiver_wallet_id' => $receiverCoinWallet->id]
        );

        $receiverEntry = $this->createEntry(
            entryType: 'coin_transfer',
            referenceable: $coinTransfer,
            wallet: $receiverCoinWallet,
            customerId: null,
            debit: 0,
            credit: $coins,
            balanceBefore: $receiverBalanceBefore,
            balanceAfter: $receiverCoinWallet->coins,
            metadata: ['direction' => 'incoming', 'sender_wallet_id' => $senderCoinWallet->id]
        );

        return [$senderEntry, $receiverEntry];
    }

    public function recordHouseCut(Wallet $houseWallet, float $amount, string $source): LedgerEntry
    {
        $balanceBefore = $houseWallet->balance;
        $houseWallet->balance += $amount;
        $houseWallet->save();

        return $this->createEntry(
            entryType: 'house_cut',
            referenceable: null,
            wallet: $houseWallet,
            customerId: 1,
            debit: 0,
            credit: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $houseWallet->balance,
            metadata: ['source' => $source]
        );
    }

    public function recordRefund($reference, Wallet $wallet, float $amount, string $reason): LedgerEntry
    {
        $balanceBefore = $wallet->balance;
        $wallet->balance += $amount;
        $wallet->save();

        return $this->createEntry(
            entryType: 'refund',
            referenceable: $reference,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: 0,
            credit: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $wallet->balance,
            metadata: ['reason' => $reason]
        );
    }

    public function holdFunds(Wallet $wallet, float $amount, string $type, $reference = null): PendingBalance
    {
        return PendingBalance::create([
            'pending_id' => Uuid::uuid4()->toString(),
            'wallet_id' => $wallet->id,
            'customer_id' => $wallet->customer_id,
            'amount' => $amount,
            'type' => $type,
            'referenceable_type' => $reference ? get_class($reference) : null,
            'referenceable_id' => $reference?->id,
            'status' => 'holding',
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function settleHold(PendingBalance $hold): LedgerEntry
    {
        $hold->status = 'settled';
        $hold->save();

        $wallet = $hold->wallet;
        $balanceBefore = $wallet->balance;
        $wallet->balance += $hold->amount;
        $wallet->save();

        return $this->createEntry(
            entryType: $hold->type.'_settled',
            referenceable: $hold->referenceable,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: 0,
            credit: $hold->amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $wallet->balance,
            metadata: ['pending_id' => $hold->pending_id]
        );
    }

    public function reverseEntry(LedgerEntry $entry): LedgerEntry
    {
        $wallet = $entry->wallet;
        $balanceBefore = $wallet->balance;

        if ($entry->credit > 0) {
            $wallet->balance -= $entry->credit;
        } else {
            $wallet->balance += $entry->debit;
        }
        $wallet->save();

        $entry->status = 'reversed';
        $entry->save();

        return $this->createEntry(
            entryType: $entry->entry_type.'_reversal',
            referenceable: $entry->referenceable,
            wallet: $wallet,
            customerId: $wallet->customer_id,
            debit: $entry->credit,
            credit: $entry->debit,
            balanceBefore: $balanceBefore,
            balanceAfter: $wallet->balance,
            metadata: ['original_entry_id' => $entry->entry_id]
        );
    }

    public function getBalance(Wallet $wallet): array
    {
        $settled = LedgerEntry::where('wallet_id', $wallet->id)
            ->where('status', 'settled')
            ->sum('credit') - LedgerEntry::where('wallet_id', $wallet->id)
            ->where('status', 'settled')
            ->sum('debit');

        $pending = PendingBalance::where('wallet_id', $wallet->id)
            ->where('status', 'holding')
            ->sum('amount');

        return [
            'settled' => (float) $settled,
            'pending' => (float) $pending,
            'available' => (float) ($settled - $pending),
        ];
    }

    public function getStatement(Wallet $wallet, $from, $to): Collection
    {
        return LedgerEntry::where('wallet_id', $wallet->id)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    private function createEntry(
        string $entryType,
        $referenceable,
        $wallet,
        $customerId,
        float $debit,
        float $credit,
        float $balanceBefore,
        float $balanceAfter,
        ?array $metadata = null
    ): LedgerEntry {
        return LedgerEntry::create([
            'entry_id' => Uuid::uuid4()->toString(),
            'entry_type' => $entryType,
            'referenceable_type' => $referenceable ? get_class($referenceable) : null,
            'referenceable_id' => $referenceable?->id,
            'wallet_id' => $wallet->id,
            'customer_id' => $customerId,
            'debit' => $debit,
            'credit' => $credit,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'status' => 'settled',
            'metadata' => $metadata,
        ]);
    }
}
