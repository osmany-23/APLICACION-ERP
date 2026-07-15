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
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'code' => ['nullable', 'string', 'max:20', Rule::unique('payment_terms', 'code')->ignore($paymentTermId)],
            'name' => ['required', 'string', 'max:80', Rule::unique('payment_terms', 'name')->ignore($paymentTermId)],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:CASH,CREDIT,ADVANCE,INSTALLMENTS,OTHER'],
            'cash' => ['nullable', 'boolean'],
            'credit' => ['nullable', 'boolean'],
            'advance' => ['nullable', 'boolean'],
            'days' => ['nullable', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_days' => ['nullable', 'integer', 'min:0'],
            'late_fee_percent' => ['nullable', 'numeric', 'min:0'],
            'down_payment_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allow_partial_payments' => ['nullable', 'boolean'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => $this->filled('code') ? strtoupper(trim((string) $this->input('code'))) : null,
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'type' => $this->input('type') ?: $this->inferType(),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('type');
            $days = (int) $this->input('days', 0);
            $downPayment = $this->input('down_payment_percent');
            $installments = $this->input('installments');
            $discountPercent = (float) $this->input('discount_percent', 0);
            $discountDays = $this->input('discount_days');

            if ($type === 'CASH' && $days !== 0) {
                $validator->errors()->add('days', 'Un termino de contado debe tener 0 dias.');
            }

            if ($type === 'CREDIT' && $days < 1) {
                $validator->errors()->add('days', 'Un termino de credito debe tener al menos 1 dia.');
            }

            if ($type === 'ADVANCE' && (! is_numeric($downPayment) || (float) $downPayment <= 0 || (float) $downPayment > 100)) {
                $validator->errors()->add('down_payment_percent', 'El anticipo debe estar entre 0.01 y 100.');
            }

            if ($type === 'INSTALLMENTS' && (! is_numeric($installments) || (int) $installments < 2)) {
                $validator->errors()->add('installments', 'Las cuotas deben ser 2 o mas.');
            }

            if ($discountDays !== null && $discountDays !== '' && $discountPercent <= 0) {
                $validator->errors()->add('discount_days', 'Define un descuento mayor a 0 para usar dias de descuento.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'code.max' => 'El codigo no puede superar los 20 caracteres.',
            'code.unique' => 'El codigo ya existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'name.unique' => 'El nombre ya existe.',
            'type.required' => 'El tipo es obligatorio.',
            'type.in' => 'El tipo seleccionado no es valido.',
            'days.integer' => 'Los dias deben ser un numero entero.',
            'days.min' => 'Los dias no pueden ser negativos.',
            'discount_percent.numeric' => 'El descuento debe ser un numero.',
            'discount_percent.min' => 'El descuento no puede ser menor a 0.',
            'discount_percent.max' => 'El descuento no puede ser mayor a 100.',
            'discount_days.integer' => 'Los dias de descuento deben ser un numero entero.',
            'discount_days.min' => 'Los dias de descuento no pueden ser negativos.',
            'down_payment_percent.numeric' => 'El anticipo debe ser un numero.',
            'down_payment_percent.max' => 'El anticipo no puede ser mayor a 100.',
            'installments.integer' => 'Las cuotas deben ser un numero entero.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }

    private function inferType(): string
    {
        if ($this->boolean('advance') || $this->filled('down_payment_percent')) {
            return 'ADVANCE';
        }

        if ($this->boolean('allow_partial_payments') || $this->filled('installments')) {
            return 'INSTALLMENTS';
        }

        if ($this->boolean('cash') || (int) $this->input('days', 0) === 0) {
            return 'CASH';
        }

        return 'CREDIT';
    }
}
