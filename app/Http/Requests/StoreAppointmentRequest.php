<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'doctor_id' => 'nullable|integer|required_without_all:other_professional_id,nurse_id',
            'other_professional_id' => 'nullable|integer|required_without_all:doctor_id,nurse_id',
            'nurse_id' => 'nullable|integer|required_without_all:doctor_id,other_professional_id',
            'status' => 'required|string',
            'symptoms' => 'required|string',
            'date_time' => 'required|date'
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $doctorId = $this->input('doctor_id');
            $otherProfessionalId = $this->input('other_professional_id');
            $nurseId = $this->input('nurse_id');

            $provided = array_filter([$doctorId, $otherProfessionalId, $nurseId], fn ($v) => !is_null($v));

            if (count($provided) !== 1) {
                $validator->errors()->add('provider_id', 'Exactly one of doctor_id, other_professional_id, or nurse_id must be provided.');
            }
        });
    }
}
