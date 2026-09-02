<?php

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-game-key');
    $this->customer = Customer::factory()->create(['id_no' => 'GAME001']);
});

it('lists game wallets', function () {
    $response = $this->getJson('/api/v1/game/wallets', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('creates a game wallet', function () {
    $response = $this->postJson('/api/v1/game/wallets', [
        'game_id' => 'GAME_TEST_001',
        'game_type' => 1,
    ], apiHeaders($this->apiKey->key));

    $response->assertStatus(201);
    $response->assertJson(['status' => 'success']);
});

it('validates required fields for game wallet creation', function () {
    $response = $this->postJson('/api/v1/game/wallets', [], apiHeaders($this->apiKey->key));

    $response->assertStatus(422);
});

it('lists game bets', function () {
    $response = $this->getJson('/api/v1/game/bets', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('returns game results', function () {
    $response = $this->getJson('/api/v1/game/results', apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});

it('returns game income', function () {
    $response = $this->postJson('/api/v1/game/income', [], apiHeaders($this->apiKey->key));

    $response->assertStatus(200);
});
