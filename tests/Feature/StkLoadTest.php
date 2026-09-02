<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-stk-key');
    $this->customer = Customer::factory()->create(['id_no' => 'STK001']);
});

it('initiates STK deposit', function () {
    $encryptedId = encryptId($this->customer->id);

    $response = $this->postJson("/api/v1/deposits/{$encryptedId}", [
        'amount' => 100,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(201);
});

it('initiates STK load', function () {
    $encryptedId = encryptId($this->customer->id);

    $response = $this->postJson("/api/v1/load/{$encryptedId}", [
        'amount' => 100,
        'type' => 'load',
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(201);
});
