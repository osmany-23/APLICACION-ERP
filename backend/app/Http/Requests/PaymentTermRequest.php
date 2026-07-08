<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentTermId = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('payment_terms', 'name')->ignore($paymentTermId)],
            'days' => ['required', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'name.unique' => 'El nombre ya existe.',
            'days.required' => 'Los días son obligatorios.',
            'days.integer' => 'Los días deben ser un número entero.',
            'days.min' => 'Los días no pueden ser negativos.',
            'discount_percent.numeric' => 'El descuento debe ser un número.',
            'discount_percent.min' => 'El descuento no puede ser menor a 0.',
            'discount_percent.max' => 'El descuento no puede ser mayor a 100.',
            'discount_days.integer' => 'Los días de descuento deben ser un número entero.',
            'discount_days.min' => 'Los días de descuento no pueden ser negativos.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }
}
