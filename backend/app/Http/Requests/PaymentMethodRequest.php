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
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'code' => ['required', 'string', 'max:20', Rule::unique('payment_methods', 'code')->ignore($paymentMethodId)],
            'name' => ['required', 'string', 'max:80', Rule::unique('payment_methods', 'name')->ignore($paymentMethodId)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:CASH,BANK_TRANSFER,CARD,CHECK,DEPOSIT,DIGITAL_WALLET,CREDIT_INTERNAL,OTHER'],
            'cash' => ['nullable', 'boolean'],
            'card' => ['nullable', 'boolean'],
            'bank' => ['nullable', 'boolean'],
            'check' => ['nullable', 'boolean'],
            'digital_wallet' => ['nullable', 'boolean'],
            'credit' => ['nullable', 'boolean'],
            'other' => ['nullable', 'boolean'],
            'requires_reference' => ['nullable', 'boolean'],
            'requires_bank' => ['nullable', 'boolean'],
            'requires_authorization' => ['nullable', 'boolean'],
            'allow_change' => ['nullable', 'boolean'],
            'allow_partial_payment' => ['nullable', 'boolean'],
            'is_online' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = strtoupper(trim((string) $this->input('code')));

        $this->merge([
            'code' => $code,
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'type' => $this->input('type') ?: $this->inferType($code, (string) $this->input('name')),
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = (string) $this->input('type');
            $requiresBank = $this->boolean('requires_bank');
            $requiresReference = $this->boolean('requires_reference');

            if ($type === 'CASH' && $requiresBank) {
                $validator->errors()->add('requires_bank', 'El efectivo no debe requerir banco.');
            }

            if (in_array($type, ['BANK_TRANSFER', 'DEPOSIT', 'CHECK'], true) && ! $requiresBank) {
                $validator->errors()->add('requires_bank', 'Este tipo de metodo debe requerir banco.');
            }

            if (in_array($type, ['BANK_TRANSFER', 'DEPOSIT', 'CHECK'], true) && ! $requiresReference) {
                $validator->errors()->add('requires_reference', 'Este tipo de metodo debe requerir referencia.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'code.required' => 'El codigo es obligatorio.',
            'code.max' => 'El codigo no puede superar los 20 caracteres.',
            'code.unique' => 'El codigo ya existe.',
            'name.required' => 'El nombre es obligatorio.',
            'name.unique' => 'El nombre ya existe.',
            'name.max' => 'El nombre no puede superar los 80 caracteres.',
            'description.max' => 'La descripcion no puede superar los 255 caracteres.',
            'type.required' => 'El tipo es obligatorio.',
            'type.in' => 'El tipo seleccionado no es valido.',
            'requires_reference.boolean' => 'El flag de referencia debe ser verdadero o falso.',
            'requires_bank.boolean' => 'El flag de banco debe ser verdadero o falso.',
            'is_active.required' => 'El estado es obligatorio.',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }

    private function inferType(string $code, string $name): string
    {
        $haystack = strtoupper($code.' '.$name);

        return match (true) {
            str_contains($haystack, 'CASH') || str_contains($haystack, 'EFECTIVO') => 'CASH',
            str_contains($haystack, 'TRF') || str_contains($haystack, 'TRANSFER') => 'BANK_TRANSFER',
            str_contains($haystack, 'DEP') || str_contains($haystack, 'DEPOS') => 'DEPOSIT',
            str_contains($haystack, 'CARD') || str_contains($haystack, 'CRT') || str_contains($haystack, 'DBT') || str_contains($haystack, 'TARJ') => 'CARD',
            str_contains($haystack, 'CHK') || str_contains($haystack, 'CHEQ') => 'CHECK',
            str_contains($haystack, 'WAL') || str_contains($haystack, 'PAYPAL') || str_contains($haystack, 'BILLETERA') => 'DIGITAL_WALLET',
            str_contains($haystack, 'CREDIT') || str_contains($haystack, 'CREDITO') || str_contains($haystack, 'CRINT') => 'CREDIT_INTERNAL',
            default => 'OTHER',
        };
    }
}
