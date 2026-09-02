<?php

use App\Models\ApiKey;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may need some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createApiKey(string $key = 'test-api-key', bool $active = true): ApiKey
{
    return ApiKey::create([
        'key' => $key,
        'name' => 'Test API Key',
        'is_active' => $active,
    ]);
}

function encryptId(mixed $id): string
{
    return encryptOpenSSL((string) $id);
}

function apiHeaders(?string $apiKey = null): array
{
    return [
        'X-API-KEY' => $apiKey ?? 'test-api-key',
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ];
}
