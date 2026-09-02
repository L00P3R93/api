<?php

use App\Models\Coin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('coin firstOrCreate creates record with status 1', function () {
    $customer = Customer::factory()->create();

    $coin = Coin::firstOrCreate(
        ['customer_id' => $customer->id],
        ['coins' => 0, 'status' => 1]
    );

    expect($coin)->not->toBeNull();
    expect($coin->status)->toBe(1);
    expect($coin->customer_id)->toBe($customer->id);
});

it('coin firstOrCreate returns existing record on duplicate', function () {
    $customer = Customer::factory()->create();

    Coin::create(['customer_id' => $customer->id, 'coins' => 50, 'status' => 1]);

    $coin = Coin::firstOrCreate(
        ['customer_id' => $customer->id],
        ['coins' => 0, 'status' => 1]
    );

    expect((int) $coin->coins)->toBe(50);
    expect(Coin::where('customer_id', $customer->id)->count())->toBe(1);
});
