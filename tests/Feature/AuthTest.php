<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-auth-key');
});

it('rejects request without API key', function () {
    $response = $this->getJson('/api/v1/customers');

    $response->assertStatus(401);
    $response->assertJson(['message' => 'Invalid or missing API key']);
});

it('rejects request with invalid API key', function () {
    $response = $this->getJson('/api/v1/customers', ['X-API-KEY' => 'invalid-key']);

    $response->assertStatus(401);
});

it('rejects request with inactive API key', function () {
    createApiKey('inactive-key', false);

    $response = $this->getJson('/api/v1/customers', ['X-API-KEY' => 'inactive-key']);

    $response->assertStatus(401);
});

it('accepts request with valid API key', function () {
    $response = $this->getJson('/api/v1/customers', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});
