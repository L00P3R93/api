<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('incoming_payments', function (Blueprint $table) {
            $table->id();
            $table->string('trans_id')->unique()->nullable();
            $table->string('trans_type')->nullable();
            $table->string('trans_time');
            $table->decimal('trans_amount', 10,2)->default(0);
            $table->string('short_code')->nullable();
            $table->string('bill_ref_no')->nullable();
            $table->string('msisdn')->nullable();
            $table->string('name')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incoming_payments');
    }
};
