<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('referral_wallet_id')->constrained('referral_wallets');
            $table->decimal('amount', 12, 2);
            $table->string('phone_no', 20);
            $table->string('status', 20)->default('pending');
            $table->string('conversation_id', 100)->nullable()->unique();
            $table->string('originator_conversation_id', 100)->nullable();
            $table->string('mpesa_receipt', 50)->nullable();
            $table->string('result_code', 20)->nullable();
            $table->string('result_desc')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries');
            $table->foreignId('reversal_entry_id')->nullable()->constrained('ledger_entries');
            $table->string('requested_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at'], 'idx_referral_withdrawal_customer_created');
            $table->index(['status', 'created_at'], 'idx_referral_withdrawal_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_withdrawals');
    }
};
