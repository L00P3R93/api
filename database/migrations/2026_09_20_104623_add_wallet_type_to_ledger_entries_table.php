<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer, game, competition and coin wallets all write their id into
     * ledger_entries.wallet_id, so the id alone is ambiguous. wallet_type says
     * which table it points at. Existing rows stay null until the
     * `ledger:backfill-wallet-type` command has been run.
     */
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->string('wallet_type', 30)->nullable()->after('referenceable_id');
            $table->index(['wallet_type', 'wallet_id'], 'idx_wallet_type_wallet_id');
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropIndex('idx_wallet_type_wallet_id');
            $table->dropColumn('wallet_type');
        });
    }
};
