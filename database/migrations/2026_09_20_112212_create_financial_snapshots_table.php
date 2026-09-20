<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->decimal('customer_wallets_total', 14, 2)->default(0);
            $table->decimal('house_wallet_balance', 14, 2)->default(0);
            $table->decimal('game_escrow_total', 14, 2)->default(0);
            $table->decimal('competition_escrow_total', 14, 2)->default(0);
            $table->decimal('stuck_escrow_total', 14, 2)->default(0);
            $table->decimal('coin_liability', 14, 2)->default(0);
            $table->decimal('pending_holds_total', 14, 2)->default(0);
            $table->decimal('unmatched_deposits_total', 14, 2)->default(0);
            $table->json('mpesa_balances')->nullable();
            $table->timestamp('taken_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_snapshots');
    }
};
