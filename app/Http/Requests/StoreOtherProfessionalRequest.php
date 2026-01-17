<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOtherProfessionalRequest extends FormRequest
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
            'user_id'=>'required|integer',
            'professional_type'=>'required|string',
            'license_number'=>'nullable|string', // Optional for other professionals
            'med_school'=>'required|string', 
            'specialization'=>'required|string',
            'grad_year'=>'required',   
            'degree_file'=>'',
            'signature'=>'',
            'id_card'=>'',
        ];
    }
}
