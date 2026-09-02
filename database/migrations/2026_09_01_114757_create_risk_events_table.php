<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->string('ip_address', 45);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('event_type', 50);
            $table->string('severity', 20)->default('low');
            $table->integer('score')->default(0);
            $table->json('details')->nullable();
            $table->string('action_taken', 50)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('customer_id');
            $table->index('severity');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_events');
    }
};
