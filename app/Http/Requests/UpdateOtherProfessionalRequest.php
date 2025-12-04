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
            'user_id'=>'sometimes|integer',
            'professional_type'=>'sometimes|string',
            'license_number'=>'sometimes|string', 
            'med_school'=>'sometimes|string', 
            'specialization'=>'sometimes|string',
            'grad_year'=>'sometimes|integer',   
            'degree_file'=>'nullable|string',
        ];
    }
}
