<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for the date-range finance reports. Each report filters
     * a table by a status or type column and a created_at range.
     *
     * @var array<string, array<string, list<string>>>
     */
    private array $indexes = [
        'ledger_entries' => [
            'idx_entry_type_created_at' => ['entry_type', 'created_at'],
            'idx_customer_id_created_at' => ['customer_id', 'created_at'],
        ],
        'game_transactions' => [
            'idx_payment_type_created_at' => ['payment_type', 'created_at'],
        ],
        'competition_transactions' => [
            'idx_payment_type_created_at' => ['payment_type', 'created_at'],
        ],
        'incoming_payments' => [
            'idx_status_created_at' => ['status', 'created_at'],
        ],
        'outgoing_payments' => [
            'idx_disburse_created_at' => ['disburse', 'created_at'],
        ],
        'purchases' => [
            'idx_purchase_type_created_at' => ['purchase_type', 'created_at'],
        ],
        'wallet_transactions' => [
            'idx_transaction_type_created_at' => ['transaction_type', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $name => $columns) {
                    if (! Schema::hasIndex($table, $name)) {
                        $blueprint->index($columns, $name);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach (array_keys($indexes) as $name) {
                    if (Schema::hasIndex($table, $name)) {
                        $blueprint->dropIndex($name);
                    }
                }
            });
        }
    }
};
