<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A complaint is about exactly one game wallet or one competition wallet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'game_wallet_id' => ['nullable', 'integer', 'required_without:competition_wallet_id', 'prohibits:competition_wallet_id', 'exists:game_wallets,id'],
            'competition_wallet_id' => ['nullable', 'integer', 'required_without:game_wallet_id', 'exists:competition_wallets,id'],
            'transaction_ids' => ['nullable', 'array', 'max:50'],
            'transaction_ids.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
