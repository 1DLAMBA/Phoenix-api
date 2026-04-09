<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_number' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
            'bank_code' => ['required', 'string'],
            'bank_name' => ['required', 'string'],
            'account_name' => ['required', 'string'],
            'consultation_fee' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.size' => 'Account number must be exactly 10 digits.',
            'account_number.regex' => 'Account number must contain only digits.',
            'consultation_fee.min' => 'Consultation fee cannot be negative.',
        ];
    }
}
