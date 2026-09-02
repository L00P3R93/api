<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('get user id returns padded string with year month prefix', function () {
    $role = Role::create(['name' => 'Admin']);
    $user = User::factory()->create(['id' => 42, 'role_id' => $role->id]);

    $userId = $user->getUserId();

    $yearMonth = date('Ym', strtotime($user->created_at));
    expect($userId)->toBe($yearMonth.'0042');
});

it('get user id pads single digit id to four digits', function () {
    $role = Role::create(['name' => 'Admin']);
    $user = User::factory()->create(['id' => 7, 'role_id' => $role->id]);

    $userId = $user->getUserId();

    $yearMonth = date('Ym', strtotime($user->created_at));
    expect($userId)->toBe($yearMonth.'0007');
});

it('get user id pads three digit id to four digits', function () {
    $role = Role::create(['name' => 'Admin']);
    $user = User::factory()->create(['id' => 123, 'role_id' => $role->id]);

    $userId = $user->getUserId();

    $yearMonth = date('Ym', strtotime($user->created_at));
    expect($userId)->toBe($yearMonth.'0123');
});
