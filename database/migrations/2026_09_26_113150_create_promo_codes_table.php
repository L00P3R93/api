<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('promotion', 30)->default('signup_bonus');
            $table->timestamp('expires_at');
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->string('note')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->string('deactivated_by')->nullable();
            $table->timestamps();

            $table->index('expires_at', 'idx_promo_codes_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
