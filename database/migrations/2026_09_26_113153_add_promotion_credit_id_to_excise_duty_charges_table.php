<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Excise duty is also charged on promotion credits, so a charge belongs to a deposit or to a promotion
     * credit. Exactly one of the two is set; the application enforces it.
     */
    public function up(): void
    {
        Schema::table('excise_duty_charges', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_id')->nullable()->change();
            $table->foreignId('promotion_credit_id')->nullable()->after('deposit_id')->unique()->constrained('promotion_credits');
        });
    }

    public function down(): void
    {
        Schema::table('excise_duty_charges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_credit_id');
            $table->unsignedBigInteger('deposit_id')->nullable(false)->change();
        });
    }
};
