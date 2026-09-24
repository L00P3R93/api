<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveReferralCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The client generates the code, the link and the QR code. The QR code is a URL or a data URI.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Za-z0-9]{4,20}$/'],
            'link' => ['nullable', 'string', 'url', 'max:2048'],
            'qr_code' => ['nullable', 'string', 'max:500000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The code must be 4 to 20 letters or digits.',
        ];
    }
}
