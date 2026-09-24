<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `type` was an enum of b2c and c2b. The referral shortcode's balance is stored as `referral_b2c`, so the
 * column becomes a plain string. Existing rows keep their values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mpesa_balances', function (Blueprint $table) {
            $table->string('type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('mpesa_balances', function (Blueprint $table) {
            $table->enum('type', ['b2c', 'c2b'])->change();
        });
    }
};
