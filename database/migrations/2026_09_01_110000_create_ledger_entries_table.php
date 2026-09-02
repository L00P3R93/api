<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_id', 36)->unique();
            $table->string('entry_type', 50);
            $table->string('referenceable_type')->nullable();
            $table->unsignedBigInteger('referenceable_id')->nullable();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('debit', 10, 2)->default(0.00);
            $table->decimal('credit', 10, 2)->default(0.00);
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('status', 20)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('wallet_id', 'idx_wallet_id');
            $table->index('customer_id', 'idx_customer_id');
            $table->index('entry_type', 'idx_entry_type');
            $table->index(['referenceable_type', 'referenceable_id'], 'idx_referenceable');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
