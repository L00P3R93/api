<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained('referrals');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('referred_id');
            $table->foreignId('referral_wallet_id')->constrained('referral_wallets');
            $table->string('milestone', 20);
            $table->decimal('amount', 12, 2);
            $table->foreignId('ledger_entry_id')->constrained('ledger_entries');
            $table->timestamps();

            $table->unique(['referral_id', 'milestone'], 'uniq_referral_bonus_milestone');
            $table->index(['customer_id', 'created_at'], 'idx_referral_bonus_customer_created');
            $table->index('created_at', 'idx_referral_bonus_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_bonuses');
    }
};
