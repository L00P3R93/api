<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller {
    public function index() {
        try {
            $customers = Customer::all();
            return view('customers.list', compact('customers'));
        } catch (\Exception $e) {
            Log::error('Customer List Error: ', ['error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($encryptedIdentifier) {
        try {
            $customer = Customer::where('id', $encryptedIdentifier)->orWhere('account_no', $encryptedIdentifier)->first();
            if(!$customer) return back()->with('error', 'Customer not found');
            $wallet = $customer->wallet;
            $transactions = $wallet->transactions()->get();
            return view('customers.view', compact('customer', 'transactions'));
        } catch (\Exception $e) {
            Log::error('Customer Get Error: ', ['error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
}
