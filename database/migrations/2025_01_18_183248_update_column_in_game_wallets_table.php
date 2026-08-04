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
        Schema::table('game_wallets', function (Blueprint $table) {
            $table->string('game_id', 50)->unique()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable();
        });
    }
};
