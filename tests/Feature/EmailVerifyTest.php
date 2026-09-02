<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-email-verify-key');
});

it('verifies customer email', function () {
    $customer = Customer::factory()->create(['id_no' => 'EMAIL001']);
    $encryptedId = encryptId($customer->id);

    $response = $this->getJson("/api/v1/customers/{$encryptedId}/verify-email", apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJson(['status' => 'Success']);
});

it('returns 404 for nonexistent customer email verify', function () {
    $encryptedId = encryptId(9999);

    $response = $this->getJson("/api/v1/customers/{$encryptedId}/verify-email", apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});
