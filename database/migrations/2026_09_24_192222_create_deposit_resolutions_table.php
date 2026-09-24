<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->unique()->constrained('incoming_payments');
            $table->string('action', 20);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries');
            $table->string('account_no_used', 50)->nullable();
            $table->string('mpesa_reference', 50)->nullable();
            $table->string('note');
            $table->string('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at'], 'idx_deposit_resolutions_action_created');
            $table->index('customer_id', 'idx_deposit_resolutions_customer');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_resolutions');
    }
};
