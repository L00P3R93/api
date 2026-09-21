<?php

use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customer = Customer::factory()->create();
    $this->wallet = Wallet::factory()->create(['customer_id' => $this->customer->id, 'balance' => 500]);

    DB::table('game_wallets')->insert(['game_id' => 1, 'balance' => 40]);
    DB::table('competition_wallets')->insert(['customer_id' => $this->customer->id, 'balance' => 25]);
    DB::table('stocks')->insert(['user_id' => User::factory()->create(['role_id' => Role::factory()->create()->id])->id, 'amount' => 10]);
    DB::table('coins')->insert(['customer_id' => $this->customer->id, 'coins' => 30]);
    DB::table('mpesa_balances')->insert(['type' => 'b2c', 'amount' => 1000]);
    DB::table('ledger_entries')->insert([
        'entry_id' => (string) fake()->uuid(),
        'entry_type' => 'deposit',
        'wallet_id' => $this->wallet->id,
        'credit' => 500,
        'balance_before' => 0,
        'balance_after' => 500,
    ]);
    Cache::put('finance-report-test', 'stale');
});

it('zeroes wallets and coins, wipes transactional tables and keeps customers', function () {
    $this->artisan('finance:reset --force')->assertSuccessful();

    foreach (['ledger_entries', 'game_wallets', 'competition_wallets', 'stocks'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }

    expect(Customer::count())->toBe(1)
        ->and(Wallet::count())->toBe(1)
        ->and((float) $this->wallet->fresh()->balance)->toBe(0.0)
        ->and(DB::table('coins')->count())->toBe(1)
        ->and((float) DB::table('coins')->sum('coins'))->toBe(0.0)
        ->and(Cache::get('finance-report-test'))->toBeNull();
});

it('leaves mpesa balances untouched', function () {
    $this->artisan('finance:reset --force')->assertSuccessful();

    expect(DB::table('mpesa_balances')->count())->toBe(1);
});

it('makes no changes on a dry run', function () {
    $this->artisan('finance:reset --dry-run')->assertSuccessful();

    expect((float) $this->wallet->fresh()->balance)->toBe(500.0)
        ->and(DB::table('ledger_entries')->count())->toBe(1)
        ->and(DB::table('game_wallets')->count())->toBe(1);
});

it('aborts when the typed confirmation is wrong', function () {
    $this->artisan('finance:reset')
        ->expectsQuestion('Type RESET to permanently wipe the data listed above', 'no')
        ->assertFailed();

    expect((float) $this->wallet->fresh()->balance)->toBe(500.0)
        ->and(DB::table('ledger_entries')->count())->toBe(1);
});

it('proceeds when RESET is typed', function () {
    $this->artisan('finance:reset')
        ->expectsQuestion('Type RESET to permanently wipe the data listed above', 'RESET')
        ->assertSuccessful();

    expect((float) $this->wallet->fresh()->balance)->toBe(0.0);
});
