<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excise_duty_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->unique()->constrained('incoming_payments');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->foreignId('ledger_entry_id')->constrained('ledger_entries');
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('rate', 6, 4);
            $table->decimal('excise_amount', 14, 2);
            $table->decimal('net_amount', 14, 2);
            $table->string('status', 20)->default('charged');
            $table->foreignId('remittance_id')->nullable()->constrained('excise_duty_remittances')->nullOnDelete();
            $table->timestamp('charged_at');
            $table->timestamps();

            $table->index('charged_at', 'idx_excise_charged_at');
            $table->index(['status', 'charged_at'], 'idx_excise_status_charged_at');
            $table->index('customer_id', 'idx_excise_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excise_duty_charges');
    }
};
