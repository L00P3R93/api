<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'TransID' => 'required|string',
            'TransactionType' => 'required|string',
            'TransTime' => 'required|date',
            'TransAmount' => 'required|numeric',
            'BusinessShortCode' => 'required|string',
            'BillRefNumber' => 'required|string',
            'MSISDN' => 'required|string',
            'FirstName' => 'required|string',
        ];
    }
}
