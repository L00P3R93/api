<?php

use App\Models\Coin;
use App\Models\Customer;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-coin-buy-key');
    $this->customer = Customer::factory()->create(['id_no' => 'BUY001']);
    Wallet::factory()->create(['customer_id' => $this->customer->id, 'balance' => 1000]);
    Coin::create(['customer_id' => $this->customer->id, 'coins' => 0, 'status' => 1]);
});

it('validates amount is required for coin purchase', function () {
    $encryptedId = encryptId($this->customer->id);

    $response = $this->postJson("/api/v1/coins/buy/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    $response->assertStatus(422);
});
