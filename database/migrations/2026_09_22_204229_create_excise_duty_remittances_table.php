<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excise_duty_remittances', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount_due', 14, 2);
            $table->decimal('amount_paid', 14, 2);
            $table->string('kra_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('recorded_by', 100)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('voided_by', 100)->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end'], 'idx_excise_remittance_period');
            $table->index('voided_at', 'idx_excise_remittance_voided');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excise_duty_remittances');
    }
};
