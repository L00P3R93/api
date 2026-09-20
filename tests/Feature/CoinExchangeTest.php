<?php

use App\Models\Coin;
use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-coin-exchange-key');
    $this->customer = Customer::factory()->create(['id_no' => 'CEX001']);
    $this->wallet = Wallet::factory()->create(['customer_id' => $this->customer->id, 'balance' => 0]);
    $this->coin = Coin::create(['customer_id' => $this->customer->id, 'coins' => 100, 'status' => 1]);
});

it('exchanges all coins when the coins amount is omitted', function () {
    $encryptedId = encryptId($this->coin->id);

    $response = $this->putJson("/api/v1/coins/exchange/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    $response->assertOk();
    expect((float) $this->coin->fresh()->coins)->toBe(0.0);
    expect((float) $this->wallet->fresh()->balance)->toBe(4.0);
});
