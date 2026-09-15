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
        Schema::table('competition_wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('competition_wallets', 'competition_id')) {
                $table->string('competition_id')->nullable()->index()->after('id');
            }
            if (! Schema::hasColumn('competition_wallets', 'cmp_uid')) {
                $table->string('cmp_uid')->nullable()->index()->after('competition_id');
            }
            if (! Schema::hasColumn('competition_wallets', 'jp_rounds')) {
                $table->integer('jp_rounds')->default(0)->after('level');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'referral_code')) {
                $table->string('referral_code')->nullable()->after('account_no');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'referral_code')) {
                $table->string('referral_code')->nullable()->index()->after('value');
            }
            if (! Schema::hasColumn('purchases', 'test')) {
                $table->integer('test')->default(0)->after('referral_code');
            }
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_transactions', 'transaction_type')) {
                $table->string('transaction_type')->nullable()->after('transaction_id');
            }
        });

        // Application code (WithdrawalService) creates transactions with a null
        // payment_ref before the M-Pesa response arrives, so this must be nullable.
        // The unique constraint already exists from create_transactions_table and
        // must not be redeclared here, or change() tries to add a duplicate index.
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('payment_ref')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competition_wallets', function (Blueprint $table) {
            if (Schema::hasColumn('competition_wallets', 'jp_rounds')) {
                $table->dropColumn('jp_rounds');
            }
            if (Schema::hasColumn('competition_wallets', 'cmp_uid')) {
                $table->dropColumn('cmp_uid');
            }
            if (Schema::hasColumn('competition_wallets', 'competition_id')) {
                $table->dropColumn('competition_id');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'test')) {
                $table->dropColumn('test');
            }
            if (Schema::hasColumn('purchases', 'referral_code')) {
                $table->dropColumn('referral_code');
            }
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'transaction_type')) {
                $table->dropColumn('transaction_type');
            }
        });

        // Intentionally not reverted to NOT NULL: WithdrawalService relies on
        // inserting a null payment_ref, and existing rows may already be null.
    }
};
