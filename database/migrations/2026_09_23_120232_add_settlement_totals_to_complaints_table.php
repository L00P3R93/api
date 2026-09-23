<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('shortfall_amount');
            $table->decimal('house_cuts_reversed', 12, 2)->default(0)->after('refunded_amount');
            $table->decimal('released_amount', 12, 2)->default(0)->after('house_cuts_reversed');
            $table->index(['status', 'closed_at'], 'idx_complaint_status_closed');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('idx_complaint_status_closed');
            $table->dropColumn(['refunded_amount', 'house_cuts_reversed', 'released_amount']);
        });
    }
};
