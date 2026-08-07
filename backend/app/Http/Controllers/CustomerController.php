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
            'item' => $this->payload($customer->fresh()),
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

            return $customer->fresh();
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

        return [
            'id' => $customer->id,
            'code' => $customer->code,
            'full_name' => $customer->full_name,
            'business_name' => $customer->business_name,
            'tax_id' => $customer->tax_id,
            'tax_id_type' => $customer->tax_id_type,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
            'contact_person' => $customer->contact_person,
            'contact_phone' => $customer->contact_phone,
            'contact_email' => $customer->contact_email,
            'payment_term_id' => $customer->payment_term_id,
            'currency_id' => $customer->currency_id,
            'price_list_id' => $customer->price_list_id,
            'credit_limit' => $creditLimit,
            'credit_days' => $customer->credit_days,
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
        ];
    }

    private function validatedCustomer(Request $request, int $companyId, ?int $customerId = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_id_type' => ['nullable', Rule::in(['RUC', 'CÉDULA', 'PASAPORTE', 'OTRO'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'payment_term_id' => [
                'nullable', 'integer',
                Rule::exists('payment_terms', 'id'),
            ],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0'],
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
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'email' => $this->nullableText($validated['email'] ?? null),
            'address' => $this->nullableText($validated['address'] ?? null),
            'contact_person' => $this->nullableText($validated['contact_person'] ?? null),
            'contact_phone' => $this->nullableText($validated['contact_phone'] ?? null),
            'contact_email' => $this->nullableText($validated['contact_email'] ?? null),
            'payment_term_id' => $validated['payment_term_id'] ?? null,
            'currency_id' => $validated['currency_id'] ?? $this->companyCurrencyId($companyId),
            'price_list_id' => $validated['price_list_id'] ?? null,
            'credit_limit' => (float) ($validated['credit_limit'] ?? 0),
            'credit_days' => $validated['credit_days'] ?? null,
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
        $customer = Customer::query()->where('company_id', $companyId)->where('id', $id)->first();

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
}
