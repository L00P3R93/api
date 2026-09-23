<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputed_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints');
            $table->string('disputable_type');
            $table->unsignedBigInteger('disputable_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('source_wallet_type', 30);
            $table->unsignedBigInteger('source_wallet_id');
            $table->decimal('amount', 12, 2);
            $table->decimal('held_amount', 12, 2)->default(0);
            $table->decimal('shortfall_amount', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status', 20)->default('held');
            $table->timestamps();

            $table->index(['disputable_type', 'disputable_id', 'status'], 'idx_disputed_disputable_status');
            $table->index(['source_wallet_type', 'source_wallet_id', 'status'], 'idx_disputed_source_status');
            $table->index('status', 'idx_disputed_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputed_transactions');
    }
};
