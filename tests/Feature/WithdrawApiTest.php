<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-withdraw-key');
});

it('lists withdrawals', function () {
    $response = $this->getJson('/api/v1/withdraws', apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('returns 404 for nonexistent withdrawal', function () {
    $encryptedId = encryptId(9999);

    $response = $this->getJson("/api/v1/withdraws/{$encryptedId}", apiHeaders($this->apiKey->key));

    $response->assertStatus(404);
});
