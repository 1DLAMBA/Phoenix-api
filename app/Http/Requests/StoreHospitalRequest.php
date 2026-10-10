<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHospitalRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phoneno' => 'required|string|max:255',
            'email' => 'required|email|unique:hospitals,email',
            'registration_number' => 'nullable|string|max:255|unique:hospitals,registration_number',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
