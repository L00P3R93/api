<?php

namespace App\Http\Requests;

use App\Models\PromoCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new signup bonus promo code. `expires_at` is a date and time in the app timezone (Africa/Nairobi),
 * e.g. `2026-10-31 23:59`. The code is stored upper case.
 */
class StorePromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{4,30}$/', function (string $attribute, mixed $value, \Closure $fail) {
                if (PromoCode::where('code', PromoCode::normalise((string) $value))->exists()) {
                    $fail('That promo code already exists.');
                }
            }],
            'expires_at' => ['required', 'date', 'after:now'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
