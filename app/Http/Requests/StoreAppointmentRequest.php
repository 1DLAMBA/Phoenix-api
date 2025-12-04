<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
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
            'client_id' => 'required|integer',
            'doctor_id' => 'nullable|integer|required_without:other_professional_id',
            'other_professional_id' => 'nullable|integer|required_without:doctor_id',
            'status' => 'required|string',
            'symptoms' => 'required|string',
            'date_time' => ''
        ];
    }
}
