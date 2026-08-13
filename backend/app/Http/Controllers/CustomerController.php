<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use StatusUpdateable;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeCustomers($request);
        $companyId = (int) $request->user()->company_id;

        $search = trim((string) $request->query('search', ''));

        $query = Customer::query()
            ->where('company_id', $companyId)
            ->withCount('sales')
            ->with('phoneCountry:id,phone_code')
            ->orderBy('full_name');

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('full_name', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        $customers = $query->get()->map(fn (Customer $customer) => $this->payload($customer));

        return response()->json(['data' => $customers]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeCustomers($request);
        $companyId = (int) $request->user()->company_id;

        $customer = $this->findCustomer($companyId, $id);

        $recentSales = DB::table('sales')
            ->where('company_id', $companyId)
            ->where('customer_id', $id)
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'sale_number', 'sale_date', 'status', 'total', 'balance_due']);

        return response()->json([
            'item' => $this->payload($customer),
            'recent_sales' => $recentSales,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeCustomers($request);

        $companyId = (int) $user->company_id;
        $validated = $this->validatedCustomer($request, $companyId);

        $customer = DB::transaction(function () use ($validated, $companyId, $user) {
            $data = $this->customerData($validated, $companyId);
            $data['code'] = $this->generateCustomerCode($companyId);
            $data['created_by'] = $user->id;

            return Customer::create($data);
        });

        return response()->json([
            'message' => 'Cliente guardado correctamente.',
            'item' => $this->payload($customer->fresh(['phoneCountry'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeCustomers($request);

        $companyId = (int) $user->company_id;
        $customer = $this->findCustomer($companyId, $id);
        $validated = $this->validatedCustomer($request, $companyId, $id);

        $customer = DB::transaction(function () use ($validated, $companyId, $customer) {
            $customer->update($this->customerData($validated, $companyId));

            return $customer->fresh(['phoneCountry']);
        });

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'item' => $this->payload($customer),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeCustomers($request);
        $companyId = (int) $request->user()->company_id;
        $customer = $this->findCustomer($companyId, $id);

        if ($this->hasReferences($id)) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque tiene ventas o cuentas por cobrar relacionadas.',
            ], 409);
        }

        try {
            $customer->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Cliente eliminado correctamente.', 'deleted' => true]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $this->authorizeCustomers($request);
        $companyId = (int) $request->user()->company_id;
        $customer = $this->findCustomer($companyId, $id);
        $validated = $this->validateStatusUpdate($request);

        return $this->changeStatus($customer, $validated['status'] ? 1 : 0, 'status');
    }

    private function payload(Customer $customer): array
    {
        $creditLimit = (float) $customer->credit_limit;
        $balance = (float) $customer->current_balance;
        $phoneCode = $customer->relationLoaded('phoneCountry') ? $customer->phoneCountry?->phone_code : null;

        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'full_name' => $customer->full_name,
            'business_name' => $customer->business_name,
            'tax_id' => $customer->tax_id,
            'tax_id_type' => $customer->tax_id_type,
            'residency_type' => $customer->residency_type,
            'gender' => $customer->gender,
            'sales_type' => $customer->sales_type,
            'phone' => $customer->phone,
            'phone_country_id' => $customer->phone_country_id,
            'phone_display' => $customer->phone ? trim(($phoneCode ? $phoneCode.' ' : '').$customer->phone) : null,
            'has_landline' => (bool) $customer->has_landline,
            'landline_phone' => $customer->landline_phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'country_id' => $customer->country_id,
            'city' => $customer->city,
            'contact_person' => $customer->contact_person,
            'contact_phone' => $customer->contact_phone,
            'contact_email' => $customer->contact_email,
            'payment_term_id' => $customer->payment_term_id,
            'currency_id' => $customer->currency_id,
            'price_list_id' => $customer->price_list_id,
            'credit_limit' => $creditLimit,
            'credit_days' => $customer->credit_days,
            'applies_late_fee' => (bool) $customer->applies_late_fee,
            'late_fee_percentage' => $customer->late_fee_percentage !== null ? (float) $customer->late_fee_percentage : null,
            'late_fee_period_unit' => $customer->late_fee_period_unit,
            'current_balance' => $balance,
            'credit_available' => max(0, $creditLimit - $balance),
            'discount_rate' => (float) $customer->discount_rate,
            'birthday' => $customer->birthday?->toDateString(),
            'salesperson_id' => $customer->salesperson_id,
            'rating' => $customer->rating,
            'notes' => $customer->notes,
            'status' => (int) $customer->status,
            'status_label' => (int) $customer->status === 1 ? 'Activo' : 'Inactivo',
            'sales_count' => (int) ($customer->sales_count ?? 0),
            'registered_at' => $customer->created_at?->toIso8601String(),
        ];
    }

    private function validatedCustomer(Request $request, int $companyId, ?int $customerId = null): array
    {
        // Solo numeros, espacios y guiones (ej. "8888-8888", "2222 3333").
        // Cubre telefono movil, convencional y de contacto.
        $phonePattern = 'regex:/^[0-9]{1,4}([\-\s][0-9]{1,4})*$/';

        return $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_id_type' => ['nullable', Rule::in(['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'])],
            'residency_type' => ['sometimes', Rule::in(['NACIONAL', 'EXTRANJERO'])],
            'gender' => ['nullable', Rule::in(['FEMENINO', 'MASCULINO', 'EMPRESA', 'OTRO'])],
            'sales_type' => ['sometimes', Rule::in(['CONTADO', 'CREDITO'])],
            'phone' => ['nullable', 'string', 'max:30', $phonePattern],
            'phone_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'has_landline' => ['sometimes', 'boolean'],
            'landline_phone' => ['nullable', 'string', 'max:30', $phonePattern, 'required_if:has_landline,true'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string'],
            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'city' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30', $phonePattern],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'payment_term_id' => [
                'nullable', 'integer',
                Rule::exists('payment_terms', 'id'),
            ],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0'],
            'applies_late_fee' => ['sometimes', 'boolean'],
            'late_fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:applies_late_fee,true'],
            'late_fee_period_unit' => ['nullable', Rule::in(['DAYS', 'WEEKS', 'MONTHS']), 'required_if:applies_late_fee,true'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'birthday' => ['nullable', 'date'],
            'salesperson_id' => ['nullable', 'integer'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in([1, 0, '1', '0', true, false, 'Activo', 'Inactivo'])],
        ], [
            'full_name.required' => 'Ingresa el nombre del cliente.',
            'email.email' => 'Ingresa un correo valido.',
            'payment_term_id.exists' => 'La condicion de pago seleccionada no existe.',
            'currency_id.exists' => 'La moneda seleccionada no existe.',
            'phone.regex' => 'El telefono solo puede tener numeros, espacios y guiones.',
            'landline_phone.regex' => 'El telefono convencional solo puede tener numeros, espacios y guiones.',
            'landline_phone.required_if' => 'Ingresa el numero de telefono convencional.',
            'contact_phone.regex' => 'El telefono de contacto solo puede tener numeros, espacios y guiones.',
            'late_fee_percentage.required_if' => 'Ingresa el porcentaje de mora.',
            'late_fee_period_unit.required_if' => 'Selecciona el periodo de mora (dias, semanas o meses).',
        ]);
    }

    private function customerData(array $validated, int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'full_name' => trim((string) $validated['full_name']),
            'business_name' => $this->nullableText($validated['business_name'] ?? null),
            'tax_id' => $this->nullableText($validated['tax_id'] ?? null),
            'tax_id_type' => $validated['tax_id_type'] ?? null,
            'residency_type' => $validated['residency_type'] ?? 'NACIONAL',
            'gender' => $validated['gender'] ?? null,
            'sales_type' => $validated['sales_type'] ?? 'CREDITO',
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'phone_country_id' => $validated['phone_country_id'] ?? null,
            'has_landline' => $this->boolValue($validated['has_landline'] ?? false),
            'landline_phone' => $this->nullableText($validated['landline_phone'] ?? null),
            'email' => $this->nullableText($validated['email'] ?? null),
            'address' => $this->nullableText($validated['address'] ?? null),
            'country_id' => $validated['country_id'] ?? null,
            'city' => $this->nullableText($validated['city'] ?? null),
            'contact_person' => $this->nullableText($validated['contact_person'] ?? null),
            'contact_phone' => $this->nullableText($validated['contact_phone'] ?? null),
            'contact_email' => $this->nullableText($validated['contact_email'] ?? null),
            'payment_term_id' => $validated['payment_term_id'] ?? null,
            'currency_id' => $validated['currency_id'] ?? $this->companyCurrencyId($companyId),
            'price_list_id' => $validated['price_list_id'] ?? null,
            'credit_limit' => (float) ($validated['credit_limit'] ?? 0),
            'credit_days' => $validated['credit_days'] ?? null,
            'applies_late_fee' => $this->boolValue($validated['applies_late_fee'] ?? false),
            'late_fee_percentage' => isset($validated['late_fee_percentage']) ? (float) $validated['late_fee_percentage'] : null,
            'late_fee_period_unit' => $validated['late_fee_period_unit'] ?? null,
            'discount_rate' => (float) ($validated['discount_rate'] ?? 0),
            'birthday' => $validated['birthday'] ?? null,
            'salesperson_id' => $validated['salesperson_id'] ?? null,
            'rating' => $validated['rating'] ?? null,
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'status' => in_array($validated['status'], [1, '1', true, 'Activo'], true) ? 1 : 0,
        ];
    }

    private function companyCurrencyId(int $companyId): int
    {
        return (int) (DB::table('companies')->where('id', $companyId)->value('currency_id') ?: 1);
    }

    private function generateCustomerCode(int $companyId): string
    {
        $codes = DB::table('customers')
            ->where('company_id', $companyId)
            ->where('code', 'like', 'CLI-%')
            ->pluck('code');

        $next = 1;

        foreach ($codes as $code) {
            if (preg_match('/^CLI-(\d+)$/', (string) $code, $matches)) {
                $next = max($next, (int) $matches[1] + 1);
            }
        }

        return sprintf('CLI-%06d', $next);
    }

    private function hasReferences(int $customerId): bool
    {
        foreach (['sales', 'accounts_receivable', 'quotations'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'customer_id')
                && DB::table($table)->where('customer_id', $customerId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function findCustomer(int $companyId, int $id): Customer
    {
        $customer = Customer::query()->with('phoneCountry:id,phone_code')->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($customer, 404, 'Cliente no encontrado.');

        return $customer;
    }

    private function authorizeCustomers(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para clientes.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'clientes')
            ->whereIn('permissions.action_name', ['manage', 'view'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar clientes.');
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function boolValue(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true'], true);
    }
}
