<?php

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('different api keys generate different request hashes', function () {
    $apiKey1 = ApiKey::create(['key' => 'test-key-idem-1', 'name' => 'Test 1', 'is_active' => true]);
    $apiKey2 = ApiKey::create(['key' => 'test-key-idem-2', 'name' => 'Test 2', 'is_active' => true]);

    $payload = [
        'name' => 'Test Customer',
        'phone_no' => '254712345678',
        'id_no' => 'ID12345',
        'email' => 'test1@example.com',
        'account_no' => 'ACC001',
    ];

    $hash1 = hash('sha256', json_encode($payload).'store@v1/customers'.$apiKey1->key);
    $hash2 = hash('sha256', json_encode($payload).'store@v1/customers'.$apiKey2->key);

    expect($hash1)->not->toBe($hash2);
});

it('same api key same body generates same request hash', function () {
    $apiKey = ApiKey::create(['key' => 'test-key-idem-3', 'name' => 'Test 3', 'is_active' => true]);

    $payload = [
        'name' => 'Test Customer',
        'phone_no' => '254712345678',
        'id_no' => 'ID12345',
        'email' => 'test1@example.com',
        'account_no' => 'ACC001',
    ];

    $hash1 = hash('sha256', json_encode($payload).'store@v1/customers'.$apiKey->key);
    $hash2 = hash('sha256', json_encode($payload).'store@v1/customers'.$apiKey->key);

    expect($hash1)->toBe($hash2);
});
