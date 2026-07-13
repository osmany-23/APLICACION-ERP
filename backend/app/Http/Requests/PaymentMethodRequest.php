<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMethodId = $this->route('id');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('payment_methods', 'code')->ignore($paymentMethodId)],
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('payment_methods', 'name')->ignore($paymentMethodId),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'requires_reference' => ['nullable', 'boolean'],
            'requires_bank' => ['nullable', 'boolean'],
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
            'name.unique' => 'El nombre ya existe.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'description.max' => 'La descripción no puede superar los 255 caracteres.',
            'requires_reference.boolean' => 'El flag de referencia debe ser verdadero o falso.',
            'requires_bank.boolean' => 'El flag de banco debe ser verdadero o falso.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
