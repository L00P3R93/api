<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_withdrawals', function (Blueprint $table) {
            $table->string('settled_by')->nullable()->after('requested_by');
            $table->string('settlement_note')->nullable()->after('settled_by');
        });
    }

    public function down(): void
    {
        Schema::table('referral_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['settled_by', 'settlement_note']);
        });
    }
};
