<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetitionWalletRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'competition_id' => 'required|string',
            'cmp_uid' => 'required|string',
            'game_type' => 'required|int',
            'customer_id' => [
                'required',
                'int',
                'exists:customers,id',
                Rule::notIn([1])
            ],
            'jp_rounds' => 'required|int'
        ];
    }
}
