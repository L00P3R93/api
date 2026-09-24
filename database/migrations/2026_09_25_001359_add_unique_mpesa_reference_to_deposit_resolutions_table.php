<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One M-Pesa reversal can back only one refund. NULLs (assignments) are not constrained.
     */
    public function up(): void
    {
        Schema::table('deposit_resolutions', function (Blueprint $table) {
            $table->unique('mpesa_reference', 'uniq_deposit_resolutions_mpesa_reference');
        });
    }

    public function down(): void
    {
        Schema::table('deposit_resolutions', function (Blueprint $table) {
            $table->dropUnique('uniq_deposit_resolutions_mpesa_reference');
        });
    }
};
