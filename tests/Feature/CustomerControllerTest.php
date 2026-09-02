<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-customer-key');
});

it('lists customers', function () {
    Customer::factory()->create(['id_no' => 'CUS001']);

    $response = $this->getJson('/api/v1/customers', apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('creates a customer', function () {
    $response = $this->postJson('/api/v1/customers', [
        'account_no' => 'ACC_NEW_001',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'id_no' => '12345',
        'phone_no' => '254712345678',
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(201);
    $response->assertJson(['status' => 'Success']);
});

it('validates required fields for customer creation', function () {
    $response = $this->postJson('/api/v1/customers', [], apiHeaders($this->apiKey->key));

    $response->assertStatus(422);
});

it('shows a specific customer', function () {
    $customer = Customer::factory()->create(['id_no' => 'CUS_SHOW_001']);
    $encryptedId = encryptId($customer->id);

    $response = $this->getJson("/api/v1/customers/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('returns 404 for nonexistent customer', function () {
    $encryptedId = encryptId(9999);

    $response = $this->getJson("/api/v1/customers/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});

it('updates a customer', function () {
    $customer = Customer::factory()->create(['id_no' => 'CUS_UPD_001']);
    $encryptedId = encryptId($customer->id);

    $response = $this->putJson("/api/v1/customers/{$encryptedId}", [
        'name' => 'Updated Name',
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('deletes a customer', function () {
    $customer = Customer::factory()->create(['id_no' => 'CUS_DEL_001']);
    $encryptedId = encryptId($customer->id);

    $response = $this->deleteJson("/api/v1/customers/{$encryptedId}", [], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJson(['message' => 'Customer deleted successfully']);
});

it('searches customers by query', function () {
    Customer::factory()->create(['id_no' => 'SEARCH001', 'name' => 'Alice Searchable']);

    $response = $this->getJson('/api/v1/customers/search?q=Alice', apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('returns customer games played', function () {
    $customer = Customer::factory()->create(['id_no' => 'CPLAY001']);
    $encryptedId = encryptId($customer->id);

    $response = $this->getJson("/api/v1/customers/played/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJsonStructure(['single_games', 'tournament_games', 'jackpot_games']);
});

it('returns customer purchases', function () {
    $customer = Customer::factory()->create(['id_no' => 'CPUR001']);
    $encryptedId = encryptId($customer->id);

    $response = $this->getJson("/api/v1/customers/purchases/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertOk();
});
