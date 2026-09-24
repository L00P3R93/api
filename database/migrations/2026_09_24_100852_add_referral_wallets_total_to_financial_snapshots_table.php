<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_snapshots', function (Blueprint $table) {
            $table->decimal('referral_wallets_total', 14, 2)->default(0)->after('disputed_funds_total');
        });
    }

    public function down(): void
    {
        Schema::table('financial_snapshots', function (Blueprint $table) {
            $table->dropColumn('referral_wallets_total');
        });
    }
};
