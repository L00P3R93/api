<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_transactions', function (Blueprint $table) {
            $table->decimal('wallet_balance_before', 10, 2)->nullable()->after('status');
            $table->decimal('wallet_balance_after', 10, 2)->nullable()->after('wallet_balance_before');
            $table->decimal('game_balance_before', 10, 2)->nullable()->after('wallet_balance_after');
            $table->decimal('game_balance_after', 10, 2)->nullable()->after('game_balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('game_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_balance_before',
                'wallet_balance_after',
                'game_balance_before',
                'game_balance_after',
            ]);
        });
    }
};
