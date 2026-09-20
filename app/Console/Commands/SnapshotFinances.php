<?php

namespace App\Console\Commands;

use App\Services\FinanceSnapshotService;
use Illuminate\Console\Command;

class SnapshotFinances extends Command
{
    protected $signature = 'finance:snapshot';

    protected $description = "Record today's wallet, escrow and M-Pesa balances for finance reporting";

    public function handle(FinanceSnapshotService $snapshotService): int
    {
        $snapshot = $snapshotService->takeSnapshot();

        $this->info("Snapshot saved for {$snapshot->snapshot_date->toDateString()}.");
        $this->table(['Item', 'Amount'], [
            ['Customer wallets', $snapshot->customer_wallets_total],
            ['House wallet', $snapshot->house_wallet_balance],
            ['Game escrow', $snapshot->game_escrow_total],
            ['Competition escrow', $snapshot->competition_escrow_total],
            ['Stuck escrow', $snapshot->stuck_escrow_total],
            ['Coin liability', $snapshot->coin_liability],
            ['Pending holds', $snapshot->pending_holds_total],
            ['Unmatched deposits', $snapshot->unmatched_deposits_total],
        ]);

        return self::SUCCESS;
    }
}
