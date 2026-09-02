<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_key_id')->nullable();
            $table->string('ip_address', 45);
            $table->string('method', 10);
            $table->string('endpoint');
            $table->integer('status_code');
            $table->string('request_hash', 64)->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('api_key_id');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index('endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_logs');
    }
};
