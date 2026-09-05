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
        Schema::create('mpesa_balances', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['b2c', 'c2b']);
            $table->string('account_name')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->json('raw_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_balances');
    }
};
