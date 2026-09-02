<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-playground-key');
});

it('lists playground entries', function () {
    $response = $this->getJson('/api/v1/playground', apiHeaders($this->apiKey->key));

    $response->assertOk();
});
