<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class BackfillLedgerWalletType extends Command
{
    protected $signature = 'ledger:backfill-wallet-type {--dry-run : Report what would change without writing}';

    protected $description = 'Fill ledger_entries.wallet_type for entries written before the column existed';

    public function handle(LedgerService $ledgerService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $counts = [];
        $ambiguous = [];

        // Reversals copy the type of the entry they reverse, so classify everything else first.
        foreach ([false, true] as $reversals) {
            LedgerEntry::whereNull('wallet_type')
                ->where('entry_type', $reversals ? 'like' : 'not like', '%_reversal')
                ->chunkById(500, function ($entries) use ($ledgerService, $dryRun, &$counts, &$ambiguous) {
                    foreach ($entries as $entry) {
                        $type = $ledgerService->inferWalletType($entry);

                        if ($type === null) {
                            $ambiguous[] = [$entry->id, $entry->entry_type, $entry->wallet_id];

                            continue;
                        }

                        $counts[$type] = ($counts[$type] ?? 0) + 1;

                        if (! $dryRun) {
                            $entry->wallet_type = $type;
                            $entry->save();
                        }
                    }
                });
        }

        $this->info($dryRun ? 'Dry run, nothing was written.' : 'Backfill complete.');
        $this->table(
            ['wallet_type', 'entries'],
            collect($counts)->map(fn ($count, $type) => [$type, $count])->values()->all()
        );

        if ($ambiguous !== []) {
            $this->warn(count($ambiguous).' entries could not be classified and were left null:');
            $this->table(['id', 'entry_type', 'wallet_id'], array_slice($ambiguous, 0, 50));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
