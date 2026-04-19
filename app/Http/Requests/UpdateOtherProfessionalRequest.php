<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOtherProfessionalRequest extends FormRequest
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
            'user_id' => 'sometimes|integer',
            'professional_type' => 'sometimes|nullable|string|max:255',
            'license_number' => 'sometimes|nullable|string|max:255',
            'med_school' => 'sometimes|nullable|string|max:255',
            'specialization' => 'sometimes|nullable|string|max:255',
            'grad_year' => 'sometimes|nullable|integer',
            'degree_file' => 'sometimes|nullable|string|max:2048',
            'signature' => 'sometimes|nullable|string|max:2048',
            'id_card' => 'sometimes|nullable|string|max:2048',
        ];
    }
}
