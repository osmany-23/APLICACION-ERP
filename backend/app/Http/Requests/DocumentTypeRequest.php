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
            'code' => ['nullable', 'string', 'max:20', Rule::unique('document_types', 'code')->ignore($documentTypeId)],
            'name' => ['required', 'string', 'max:80', Rule::unique('document_types', 'name')->ignore($documentTypeId)],
            'prefix' => ['nullable', 'string', 'max:10'],
            'next_number' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null,
            'name' => trim((string) $this->input('name')),
            'prefix' => $this->filled('prefix') ? strtoupper(trim((string) $this->input('prefix'))) : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'code.max' => 'El codigo no puede superar los 20 caracteres.',
            'code.unique' => 'El codigo ya existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'name.unique' => 'El nombre ya existe.',
            'prefix.max' => 'El prefijo no puede superar los 10 caracteres.',
            'next_number.integer' => 'La numeracion debe ser un numero entero.',
            'next_number.min' => 'La numeracion debe iniciar en 1 o mas.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
