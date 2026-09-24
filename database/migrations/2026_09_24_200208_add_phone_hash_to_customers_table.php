<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * phone_hash = sha256 of the customer's phone number in M-Pesa form (2547XXXXXXXX / 2541XXXXXXXX), the way
 * Safaricom hashes the payer's number in C2B confirmations. Lets an unmatched deposit be traced to a
 * customer. Existing customers are backfilled here; the Customer model keeps it current on save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('phone_hash', 64)->nullable()->after('phone_no');
            $table->index('phone_hash', 'idx_customers_phone_hash');
        });

        DB::table('customers')
            ->whereNotNull('phone_no')
            ->orderBy('id')
            ->select(['id', 'phone_no'])
            ->chunkById(500, function ($customers) {
                foreach ($customers as $customer) {
                    $digits = preg_replace('/\D/', '', (string) $customer->phone_no);

                    if (preg_match('/^(?:254|0)?([17]\d{8})$/', $digits, $match)) {
                        DB::table('customers')
                            ->where('id', $customer->id)
                            ->update(['phone_hash' => hash('sha256', '254'.$match[1])]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_phone_hash');
            $table->dropColumn('phone_hash');
        });
    }
};
