<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_balances', function (Blueprint $table) {
            $table->id();
            $table->string('pending_id', 36)->unique();
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('type', 50);
            $table->string('referenceable_type')->nullable();
            $table->unsignedBigInteger('referenceable_id')->nullable();
            $table->string('status', 20)->default('holding');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('wallet_id', 'idx_wallet_id');
            $table->index('status', 'idx_status');
            $table->index('expires_at', 'idx_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_balances');
    }
};
