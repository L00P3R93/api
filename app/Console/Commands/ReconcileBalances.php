<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Console\Command;

class ReconcileBalances extends Command
{
    protected $signature = 'wallet:reconcile {--wallet-id=} {--fix}';

    protected $description = 'Reconcile wallet balances against ledger entries';

    public function handle(): int
    {
        $walletId = $this->option('wallet-id');
        $fix = $this->option('fix');

        $query = Wallet::query();
        if ($walletId) {
            $query->where('id', $walletId);
        }

        $wallets = $query->get();
        $discrepancies = 0;

        foreach ($wallets as $wallet) {
            $ledgerBalance = LedgerEntry::where('wallet_id', $wallet->id)
                ->where('status', 'settled')
                ->sum('credit') - LedgerEntry::where('wallet_id', $wallet->id)
                ->where('status', 'settled')
                ->sum('debit');

            $difference = (float) $wallet->balance - (float) $ledgerBalance;

            if (abs($difference) > 0.01) {
                $discrepancies++;
                $this->error("Wallet #{$wallet->id} (Customer #{$wallet->customer_id}): "
                    ."Balance={$wallet->balance}, Ledger={$ledgerBalance}, Diff={$difference}");

                if ($fix) {
                    $wallet->balance = $ledgerBalance;
                    $wallet->save();
                    $this->info("  -> Fixed: balance updated to {$ledgerBalance}");
                }
            }
        }

        if ($discrepancies === 0) {
            $this->info('All '.$wallets->count().' wallets reconciled successfully.');
        } else {
            $this->warn("Found {$discrepancies} discrepancy(ies).".($fix ? ' All fixed.' : ' Run with --fix to correct.'));
        }

        return $discrepancies === 0 ? self::SUCCESS : self::FAILURE;
    }
}
