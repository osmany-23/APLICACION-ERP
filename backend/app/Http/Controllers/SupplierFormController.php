<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierFormController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $suppliers = DB::table('suppliers')
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'paymentTerms' => $this->paymentTerms($companyId),
            'currencies' => DB::table('currencies')->select(['id', 'name', 'code'])->orderBy('name')->get(),
            'taxIdTypes' => ['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'],
        ]);
    }

    public function create(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        return view('suppliers.form', [
            'supplier' => null,
            'paymentTerms' => $this->paymentTerms($companyId),
            'currencies' => DB::table('currencies')->select(['id', 'name', 'code'])->orderBy('name')->get(),
            'taxIdTypes' => ['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'],
            'statuses' => [1 => 'Activo', 0 => 'Inactivo'],
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;

        $supplier = DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        abort_unless($supplier, 404, 'Proveedor no encontrado.');

        return view('suppliers.form', [
            'supplier' => $supplier,
            'paymentTerms' => $this->paymentTerms($companyId),
            'currencies' => DB::table('currencies')->select(['id', 'name', 'code'])->orderBy('name')->get(),
            'taxIdTypes' => ['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'],
            'statuses' => [1 => 'Activo', 0 => 'Inactivo'],
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $this->supplierValidation($request, $companyId);

        if (empty(trim($validated['code'] ?? ''))) {
            $validated['code'] = $this->generateSupplierCode($validated['name'], $companyId);
        }

        if ($request->hasFile('image')) {
            $validated['image_url'] = $this->storeSupplierImage($request->file('image'));
        }

        $id = DB::table('suppliers')->insertGetId([
            ...$this->supplierData($validated),
            'company_id' => $companyId,
            'created_at' => now(),
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function update(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $this->supplierValidation($request, $companyId, $id);

        if (empty(trim($validated['code'] ?? ''))) {
            $validated['code'] = DB::table('suppliers')
                ->where('id', $id)
                ->where('company_id', $companyId)
                ->value('code');
        }

        $existingImageUrl = DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->value('image_url');

        if ($request->hasFile('image')) {
            $validated['image_url'] = $this->storeSupplierImage($request->file('image'));
        } else {
            $validated['image_url'] = $existingImageUrl;
        }

        $existingCurrencyId = DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->value('currency_id');

        $validated['currency_id'] = $validated['currency_id'] ?? $existingCurrencyId;

        DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update([
                ...$this->supplierData($validated),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function toggleStatus(Request $request, int $id)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $request->validate([
            'status' => ['required', Rule::in([1, 0, '1', '0', true, false, 'Activo', 'Inactivo', 'activo', 'inactivo'])],
        ]);

        DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update([
                'status' => $this->statusValue($validated['status']),
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Estado del proveedor actualizado.');
    }

    private function paymentTerms(int $companyId)
    {
        return DB::table('payment_terms')
            ->whereNull('company_id')
            ->orWhere('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    private function supplierValidation(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_id_type' => ['nullable', 'string', Rule::in(['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'website' => ['nullable', 'string', 'max:200'],
            'address' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'payment_term_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_terms', 'id')
                    ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId)),
            ],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'swift' => ['nullable', 'string', 'max:20'],
            'iban' => ['nullable', 'string', 'max:60'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0'],
            'payment_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
        ], $this->messages());
    }

    private function supplierData(array $validated): array
    {
        return [
            'code' => $this->nullableText($validated['code'] ?? null),
            'name' => $this->cleanName($validated['name']),
            'tax_id' => $this->nullableText($validated['tax_id'] ?? null),
            'tax_id_type' => $this->nullableText($validated['tax_id_type'] ?? null),
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'email' => $this->nullableText($validated['email'] ?? null),
            'website' => $this->nullableText($validated['website'] ?? null),
            'address' => $this->nullableText($validated['address'] ?? null),
            'image_url' => $this->nullableText($validated['image_url'] ?? null),
            'contact_person' => $this->nullableText($validated['contact_person'] ?? null),
            'contact_phone' => $this->nullableText($validated['contact_phone'] ?? null),
            'contact_email' => $this->nullableText($validated['contact_email'] ?? null),
            'payment_term_id' => $validated['payment_term_id'] ? (int) $validated['payment_term_id'] : null,
            'currency_id' => (int) ($validated['currency_id'] ?? 1),
            'bank_name' => $this->nullableText($validated['bank_name'] ?? null),
            'bank_account' => $this->nullableText($validated['bank_account'] ?? null),
            'swift' => $this->nullableText($validated['swift'] ?? null),
            'iban' => $this->nullableText($validated['iban'] ?? null),
            'credit_limit' => isset($validated['credit_limit']) ? $validated['credit_limit'] : null,
            'credit_days' => $validated['credit_days'] !== null ? (int) $validated['credit_days'] : null,
            'payment_days' => $validated['payment_days'] !== null ? (int) $validated['payment_days'] : null,
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'status' => $this->statusValue($validated['status'] ?? 1),
        ];
    }

    private function storeSupplierImage($image): string
    {
        $path = $image->store('suppliers', 'public');

        return Storage::url($path);
    }

    private function generateSupplierCode(string $name, int $companyId): string
    {
        $base = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(substr(trim($name), 0, 20)));
        $base = $base !== '' ? $base : 'SUP';

        $count = DB::table('suppliers')
            ->where('company_id', $companyId)
            ->where('code', 'like', $base . '%')
            ->count();

        return $count === 0 ? $base : sprintf('%s-%d', $base, $count + 1);
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function cleanName(string $value): string
    {
        return trim($value);
    }

    private function statusValue(mixed $status): int
    {
        return in_array($status, ['Activo', 'activo', 1, '1', true], true) ? 1 : 0;
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre.',
            'code.unique' => 'Ya existe un proveedor con ese código.',
            'email.email' => 'Ingresa un correo válido.',
            'contact_email.email' => 'Ingresa un correo válido.',
            'payment_term_id.exists' => 'La condición de pago seleccionada no existe.',
            'currency_id.exists' => 'La moneda seleccionada no existe.',
            'status.required' => 'Selecciona el estado.',
        ];
    }
}
