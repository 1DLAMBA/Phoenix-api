<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalRecordRequest extends FormRequest
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
            'client_id'=> 'required|integer',
            'assigned_doctor_id' => 'required|integer',
            'record_number'=> 'required|string',
            'diagnosis'=> 'required|string',
            'past_diagnosis'=> 'required|string',
            'allergies'=> 'required|string',
            'treatment'=> 'required|string',
        ];
    }
}
