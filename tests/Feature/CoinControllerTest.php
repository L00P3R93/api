<?php

use App\Models\Coin;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-coins-key');
    $this->customer = Customer::factory()->create(['id_no' => 'COIN001']);
});

it('lists all coins', function () {
    Coin::create(['customer_id' => $this->customer->id, 'coins' => 100, 'status' => 1]);

    $response = $this->getJson('/api/v1/coins', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('shows a specific coin wallet', function () {
    $coin = Coin::create(['customer_id' => $this->customer->id, 'coins' => 50, 'status' => 1]);
    $encryptedId = encryptId($coin->id);

    $response = $this->getJson("/api/v1/coins/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('returns 404 for nonexistent coin wallet', function () {
    $encryptedId = encryptId(9999);

    $response = $this->getJson("/api/v1/coins/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});

it('updates a coin wallet', function () {
    $coin = Coin::create(['customer_id' => $this->customer->id, 'coins' => 50, 'status' => 1]);
    $encryptedId = encryptId($coin->id);

    $response = $this->putJson("/api/v1/coins/{$encryptedId}", [
        'coins' => 100,
        'status' => 1,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('rejects invalid identifier for coin', function () {
    $response = $this->getJson('/api/v1/coins/invalid-encrypted-id', apiHeaders($this->apiKey->key));

    $response->assertStatus(400);
});
