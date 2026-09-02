<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-middleware-key');
});

it('rejects request without API key', function () {
    $response = $this->getJson('/api/v1/customers');

    $response->assertStatus(401);
    $response->assertJson(['message' => 'Invalid or missing API key']);
});

it('rejects request with invalid API key', function () {
    $response = $this->getJson('/api/v1/customers', ['X-API-KEY' => 'invalid']);

    $response->assertStatus(401);
});

it('accepts request with valid API key', function () {
    $response = $this->getJson('/api/v1/customers', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('rejects request with inactive API key', function () {
    createApiKey('inactive-test-key', false);

    $response = $this->getJson('/api/v1/customers', ['X-API-KEY' => 'inactive-test-key']);

    $response->assertStatus(401);
});

it('encrypt and decrypt round trips correctly', function () {
    $original = '12345';
    $encrypted = encryptId($original);
    $decrypted = decryptOpenSSL($encrypted);

    expect($decrypted)->toBe($original);
});

it('decrypt identifier middleware rejects invalid identifier', function () {
    $response = $this->getJson('/api/v1/customers/invalid-encrypted', apiHeaders($this->apiKey->key));

    $response->assertStatus(400);
    $response->assertJson(['message' => 'Invalid identifier.']);
});
