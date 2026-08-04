<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competition_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_wallet_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('amount', 10)->default(0);
            $table->integer('status')->default(2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_transactions');
    }
};
