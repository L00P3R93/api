<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('house_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deposit_id')->constrained('incoming_payments');
            $table->decimal('amount', 12, 2);
            $table->foreignId('ledger_entry_id')->constrained('ledger_entries');
            $table->string('note', 255);
            $table->string('recorded_by', 100)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('voided_by', 100)->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->foreignId('void_ledger_entry_id')->nullable()->constrained('ledger_entries');
            $table->timestamps();

            $table->index(['deposit_id', 'voided_at'], 'idx_house_fundings_deposit_voided');
            $table->index(['created_at', 'voided_at'], 'idx_house_fundings_created_voided');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('house_fundings');
    }
};
