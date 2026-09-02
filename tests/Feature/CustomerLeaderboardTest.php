<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-leaderboard-key');
});

it('returns customer leaderboard with date range', function () {
    $response = $this->postJson('/api/v1/customers/leaderboard', [
        'start_date' => now()->subMonths(1)->toDateString(),
        'end_date' => now()->toDateString(),
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
});

it('returns combined leaderboard', function () {
    $response = $this->postJson('/api/v1/customers/combined-leaderboard', [], apiHeaders($this->apiKey->key));

    $response->assertOk();
});
