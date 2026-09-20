<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('expense_date');
            $table->string('category', 40);
            $table->decimal('amount', 12, 2);
            $table->string('description', 255)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('entered_by', 100)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('voided_by', 100)->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['expense_date', 'voided_at'], 'idx_expense_date_voided');
            $table->index(['category', 'expense_date'], 'idx_expense_category_date');
            $table->index('reference', 'idx_expense_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_expenses');
    }
};
