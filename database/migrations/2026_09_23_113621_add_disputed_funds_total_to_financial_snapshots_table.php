<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_snapshots', function (Blueprint $table) {
            $table->decimal('disputed_funds_total', 14, 2)->default(0)->after('excise_duty_payable');
        });
    }

    public function down(): void
    {
        Schema::table('financial_snapshots', function (Blueprint $table) {
            $table->dropColumn('disputed_funds_total');
        });
    }
};
