<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('balance_before', 10, 2)->nullable()->after('status');
            $table->decimal('balance_after', 10, 2)->nullable()->after('balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_before', 'balance_after']);
        });
    }
};
