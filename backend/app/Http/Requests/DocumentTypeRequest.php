<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $documentTypeId = $this->route('id');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('document_types', 'code')->ignore($documentTypeId)],
            'name' => ['required', 'string', 'max:80'],
            'prefix' => ['nullable', 'string', 'max:10'],
            'next_number' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es obligatorio.',
            'code.max' => 'El código no puede superar los 20 caracteres.',
            'code.unique' => 'El código ya existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'prefix.max' => 'El prefijo no puede superar los 10 caracteres.',
            'next_number.required' => 'El número siguiente es obligatorio.',
            'next_number.integer' => 'El número siguiente debe ser un número entero.',
            'next_number.min' => 'El número siguiente debe ser al menos 1.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
