<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Usage:
// - php artisan finance:reset --dry-run — lists row counts and balance totals, makes no changes
// - php artisan finance:reset — asks you to type RESET before wiping
// - --force — skips the typed confirmation (for scripted runs)
// Customers and wallet rows are kept (balances set to 0). mpesa_balances is never touched.
class ResetFinancialData extends Command
{
    /**
     * Tables to empty, ordered so child tables come before the tables they reference.
     *
     * @var list<string>
     */
    public const TABLES_TO_WIPE = [
        'ledger_entries',
        'pending_balances',
        'outgoing_payments',
        'incoming_payments',
        'purchases',
        'wallet_transactions',
        'transactions',
        'game_transactions',
        'game_wallets',
        'competition_transactions',
        'competition_wallets',
        'stocks',
        'b2c',
        'financial_snapshots',
        'finance_expenses',
        'idempotency_keys',
        'request_logs',
        'risk_events',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:reset
        {--dry-run : Show what would be wiped without making any changes}
        {--force : Skip the typed confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Zero all wallets and coins and wipe transactional/ledger data, keeping customers and wallet rows';

    public function handle(): int
    {
        $this->renderSummary();

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: no changes made.');

            return self::SUCCESS;
        }

        if (! $this->confirmReset()) {
            $this->components->error('Aborted. No changes made.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            foreach (self::TABLES_TO_WIPE as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            DB::table('wallets')->update(['balance' => 0]);
            DB::table('coins')->update(['coins' => 0]);
        });

        $this->resetAutoIncrements();
        Cache::flush();

        if (! $this->verify()) {
            return self::FAILURE;
        }

        $this->components->info('Financial data reset. All wallets and coins are at 0.');

        return self::SUCCESS;
    }

    protected function renderSummary(): void
    {
        $rows = collect(self::TABLES_TO_WIPE)
            ->filter(fn (string $table) => Schema::hasTable($table))
            ->map(fn (string $table) => [$table, 'wipe', DB::table($table)->count()]);

        $rows->push(
            ['wallets', 'balance -> 0', number_format((float) DB::table('wallets')->sum('balance'), 2)],
            ['coins', 'coins -> 0', number_format((float) DB::table('coins')->sum('coins'), 2)],
        );

        $this->table(['Table', 'Action', 'Rows / current total'], $rows->all());
        $this->components->info('customers, users, api_keys and mpesa_balances are not touched.');
    }

    protected function confirmReset(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Non-interactive run: pass --force to confirm.');

            return false;
        }

        if (app()->isProduction()) {
            $this->components->warn('PRODUCTION: this permanently deletes data. Take a database backup first (e.g. mysqldump) before continuing.');
        }

        return $this->ask('Type RESET to permanently wipe the data listed above') === 'RESET';
    }

    protected function resetAutoIncrements(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABLES_TO_WIPE as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
        }
    }

    protected function verify(): bool
    {
        $failures = collect(self::TABLES_TO_WIPE)
            ->filter(fn (string $table) => Schema::hasTable($table) && DB::table($table)->exists())
            ->map(fn (string $table) => "{$table} is not empty");

        if ((float) Wallet::sum('balance') !== 0.0) {
            $failures->push('wallets still have a non-zero balance');
        }

        if ((float) DB::table('coins')->sum('coins') !== 0.0) {
            $failures->push('coins still have a non-zero balance');
        }

        $failures->each(fn (string $message) => $this->components->error($message));

        return $failures->isEmpty();
    }
}
