<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_snapshots', function (Blueprint $table) {
            $table->decimal('excise_duty_payable', 14, 2)->default(0)->after('unmatched_deposits_total');
        });
    }

    public function down(): void
    {
        Schema::table('financial_snapshots', function (Blueprint $table) {
            $table->dropColumn('excise_duty_payable');
        });
    }
};
