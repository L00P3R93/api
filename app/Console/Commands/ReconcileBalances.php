<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Console\Command;

class ReconcileBalances extends Command
{
    protected $signature = 'wallet:reconcile {--wallet-id=} {--fix}';

    protected $description = 'Reconcile customer wallet balances against ledger entries';

    public function handle(): int
    {
        $walletId = $this->option('wallet-id');
        $fix = $this->option('fix');

        $unclassified = LedgerEntry::whereNull('wallet_type')->count();
        if ($unclassified > 0) {
            $this->warn("{$unclassified} ledger entries have no wallet_type and are ignored. Run `php artisan ledger:backfill-wallet-type` first.");
        }

        $query = Wallet::query();
        if ($walletId) {
            $query->where('id', $walletId);
        }

        $wallets = $query->get();
        $discrepancies = 0;
        $checked = 0;

        foreach ($wallets as $wallet) {
            $entries = fn () => LedgerEntry::where('wallet_type', LedgerEntry::WALLET_TYPE_WALLET)
                ->where('wallet_id', $wallet->id)
                ->countable();

            $firstEntry = $entries()->orderBy('id')->first();
            if (! $firstEntry) {
                continue;
            }

            $checked++;

            // Wallets that existed before the ledger began have an opening balance the ledger never saw.
            $expected = (float) $firstEntry->balance_before + (float) $entries()->sum('credit') - (float) $entries()->sum('debit');
            $difference = (float) $wallet->balance - $expected;

            if (abs($difference) > 0.01) {
                $discrepancies++;
                $this->error("Wallet #{$wallet->id} (Customer #{$wallet->customer_id}): "
                    ."Balance={$wallet->balance}, Ledger={$expected}, Diff={$difference}");

                if ($fix) {
                    $wallet->balance = $expected;
                    $wallet->save();
                    $this->info("  -> Fixed: balance updated to {$expected}");
                }
            }
        }

        if ($discrepancies === 0) {
            $this->info("All {$checked} wallets with ledger entries reconciled successfully.");
        } else {
            $this->warn("Found {$discrepancies} discrepancy(ies).".($fix ? ' All fixed.' : ' Run with --fix to correct.'));
        }

        return $discrepancies === 0 ? self::SUCCESS : self::FAILURE;
    }
}
