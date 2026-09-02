<?php

use App\Models\SMSCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('phone verify route returns 404', function () {
    $response = $this->patchJson('/api/v1/customers/1/verify-phone', ['code' => '123456']);

    $response->assertStatus(404);
});

it('send code route returns 404', function () {
    $response = $this->postJson('/api/v1/customer/send-code', ['phone_no' => '254712345678']);

    $response->assertStatus(404);
});

it('sms code generate code throws exception', function () {
    $this->withoutExceptionHandling();

    $this->expectException(HttpException::class);

    SMSCode::generateCode();
});
