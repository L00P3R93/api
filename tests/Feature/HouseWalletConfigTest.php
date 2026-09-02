<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('house wallet uses config value', function () {
    config(['wallets.house_wallet_id' => 42]);

    expect(config('wallets.house_wallet_id'))->toBe(42);
});

it('house wallet defaults to 1', function () {
    expect(config('wallets.house_wallet_id'))->toBe(1);
});
