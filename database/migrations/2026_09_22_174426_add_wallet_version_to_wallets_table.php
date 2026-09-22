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
        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_version')->default(0)->after('balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('wallet_version');
        });
    }
};
