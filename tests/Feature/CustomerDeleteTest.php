<?php

use App\Models\Customer;
use App\Models\Wallet;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->customerService = app(CustomerService::class);
});

it('delete customer also deletes wallet', function () {
    $customer = Customer::factory()->create();
    $wallet = Wallet::factory()->create(['customer_id' => $customer->id, 'balance' => 100]);

    $result = $this->customerService->deleteCustomer($customer->id);

    expect($result)->toBeTrue();
    expect(Customer::find($customer->id))->toBeNull();
    expect(Wallet::find($wallet->id))->toBeNull();
});

it('delete customer returns false for nonexistent customer', function () {
    $result = $this->customerService->deleteCustomer(9999);

    expect($result)->toBeFalse();
});
