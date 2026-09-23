<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_id', 36)->unique();
            $table->unsignedBigInteger('customer_id');
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('game_wallet_id')->nullable();
            $table->unsignedBigInteger('competition_wallet_id')->nullable();
            $table->string('reason', 255);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending_dispute');
            $table->decimal('disputed_amount', 12, 2)->default(0);
            $table->decimal('held_amount', 12, 2)->default(0);
            $table->decimal('shortfall_amount', 12, 2)->default(0);
            $table->string('filed_by', 100)->nullable();
            $table->string('resolution_note', 255)->nullable();
            $table->string('closed_by', 100)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_complaint_status_created');
            $table->index('customer_id', 'idx_complaint_customer');
            $table->index('game_wallet_id', 'idx_complaint_game_wallet');
            $table->index(['competition_wallet_id', 'status'], 'idx_complaint_competition_wallet_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
