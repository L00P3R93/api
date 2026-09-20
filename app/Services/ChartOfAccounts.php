<?php

namespace App\Services;

use App\Models\LedgerEntry;

/**
 * Maps ledger entries onto the accounts and categories in config/finance.php, so every
 * report classifies money the same way.
 */
class ChartOfAccounts
{
    public const UNCATEGORISED = 'uncategorised';

    private const REVERSAL_SUFFIX = '_reversal';

    public function isReversal(string $entryType): bool
    {
        return str_ends_with($entryType, self::REVERSAL_SUFFIX);
    }

    /**
     * The reporting category of an entry type. A reversal takes the category of the entry it reverses.
     */
    public function categoryFor(string $entryType): string
    {
        $baseType = $this->isReversal($entryType)
            ? substr($entryType, 0, -strlen(self::REVERSAL_SUFFIX))
            : $entryType;

        return config("finance.entry_types.{$baseType}", self::UNCATEGORISED);
    }

    /**
     * The account a wallet belongs to. The house wallet is kept apart from customer wallets.
     */
    public function accountFor(string $walletType, int $walletId): string
    {
        return match ($walletType) {
            LedgerEntry::WALLET_TYPE_GAME => 'game_escrow',
            LedgerEntry::WALLET_TYPE_COMPETITION => 'competition_escrow',
            LedgerEntry::WALLET_TYPE_COIN => 'coin_wallets',
            default => $walletId === (int) config('wallets.house_wallet_id', 1) ? 'house_wallet' : 'customer_wallets',
        };
    }

    /**
     * @return array{label: string, type: string}|null
     */
    public function account(string $account): ?array
    {
        return config("finance.accounts.{$account}");
    }
}
