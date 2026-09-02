<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-stats-key');
});

it('returns customer stats', function () {
    $response = $this->getJson('/api/v1/stats/customers', apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJson(['success' => true]);
});
