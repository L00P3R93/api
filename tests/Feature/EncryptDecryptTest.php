<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->apiKey = createApiKey('test-encrypt-key');
});

it('encrypts an identifier', function () {
    $response = $this->postJson('/api/v1/encrypt', [
        'identifier' => '123',
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJsonStructure(['decrypted_id', 'encrypted_id']);
});

it('decrypts an identifier', function () {
    $encrypted = encryptId('123');

    $response = $this->postJson('/api/v1/decrypt', [
        'identifier' => $encrypted,
    ], apiHeaders($this->apiKey->key));

    $response->assertOk();
    $response->assertJson(['decrypted_id' => '123']);
});

it('encrypt then decrypt returns original value', function () {
    $original = '42';
    $encrypted = encryptId($original);

    $decrypted = decryptOpenSSL($encrypted);

    expect($decrypted)->toBe($original);
});
