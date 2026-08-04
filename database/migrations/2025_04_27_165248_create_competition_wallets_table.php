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
        Schema::create('competition_wallets', function (Blueprint $table) {
            $table->id();
            $table->integer('game_type')->default(1);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->integer('level')->default(0);
            $table->decimal('balance', 10)->default(0);
            $table->integer('status')->default(1);
            $table->mediumText('comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_wallets');
    }
};
