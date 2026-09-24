<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referred_id')->unique();
            $table->string('code_used', 20);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('first_deposit_id')->nullable()->constrained('incoming_payments')->nullOnDelete();
            $table->timestamp('first_deposited_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'created_at'], 'idx_referrals_referrer_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
