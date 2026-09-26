<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->string('promotion', 30);
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('rate', 6, 4)->default(0);
            $table->decimal('excise_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('status', 20)->default('granted');
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries');
            $table->foreignId('house_ledger_entry_id')->nullable()->constrained('ledger_entries');
            $table->timestamps();

            $table->unique(['customer_id', 'promotion'], 'uniq_promotion_credit_customer');
            $table->index(['promotion', 'created_at'], 'idx_promotion_credits_promotion_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_credits');
    }
};
