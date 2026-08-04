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
        Schema::create('outgoing_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('trans_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->integer('status')->default(1);
            $table->integer('disburse')->default(0);
            $table->string('receipt')->unique()->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outgoing_payments');
    }
};
