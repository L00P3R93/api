<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'game_wallet_id' => 'required|integer|exists:game_wallets,id',
            'amount' => 'required|numeric|min:0.01',
        ];
    }
}
