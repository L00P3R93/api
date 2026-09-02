<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('sender_balance_before', 10, 2)->nullable()->after('status');
            $table->decimal('sender_balance_after', 10, 2)->nullable()->after('sender_balance_before');
            $table->decimal('receiver_balance_before', 10, 2)->nullable()->after('sender_balance_after');
            $table->decimal('receiver_balance_after', 10, 2)->nullable()->after('receiver_balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'sender_balance_before',
                'sender_balance_after',
                'receiver_balance_before',
                'receiver_balance_after',
            ]);
        });
    }
};
