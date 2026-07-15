<?php

namespace App\Http\Controllers;

use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RelationController extends Controller
{
    use StatusUpdateable;
    
    private const RELATIONS = ['suppliers', 'warehouses', 'branches', 'companies'];

    public function index(Request $request, string $relation): JsonResponse
    {
        $this->ensureRelation($relation);
        $companyId = (int) $request->user()->company_id;

        return response()->json([
            'data' => match ($relation) {
                'suppliers' => $this->suppliers($companyId),
                'warehouses' => $this->warehouses($companyId),
                'branches' => $this->branches($companyId),
                'companies' => $this->companies($companyId),
            },
        ]);
    }

    public function store(Request $request, string $relation): JsonResponse
    {
        $this->ensureRelation($relation);
        abort_if($relation === 'companies', 405, 'La empresa actual solo se puede editar.');

        $companyId = (int) $request->user()->company_id;
        $id = match ($relation) {
            'suppliers' => $this->storeSupplier($request, $companyId),
            'warehouses' => $this->storeWarehouse($request, $companyId),
            'branches' => $this->storeBranch($request, $companyId),
            'companies' => 0,
        };

        return response()->json([
            'message' => $this->savedMessage($relation),
            'item' => $this->findItem($companyId, $relation, $id),
        ], 201);
    }

    public function update(Request $request, string $relation, int $id): JsonResponse
    {
        $this->ensureRelation($relation);
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $relation, $id);

        match ($relation) {
            'suppliers' => $this->updateSupplier($request, $companyId, $id),
            'warehouses' => $this->updateWarehouse($request, $companyId, $id),
            'branches' => $this->updateBranch($request, $companyId, $id),
            'companies' => $this->updateCompany($request, $companyId),
        };

        return response()->json([
            'message' => $this->savedMessage($relation),
            'item' => $this->findItem($companyId, $relation, $id),
        ]);
    }

    public function destroy(Request $request, string $relation, int $id): JsonResponse
    {
        $this->ensureRelation($relation);
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $relation, $id);

        $blockedMessage = $this->deleteBlockMessage($companyId, $relation, $id);

        if ($blockedMessage) {
            return response()->json(['message' => $blockedMessage], 409);
        }

        try {
            match ($relation) {
                'suppliers' => DB::table('suppliers')->where('id', $id)->where('company_id', $companyId)->delete(),
                'warehouses' => DB::table('warehouses')->where('id', $id)->where('company_id', $companyId)->delete(),
                'branches' => DB::table('branches')->where('id', $id)->where('company_id', $companyId)->delete(),
                'companies' => abort(405, 'La empresa actual no se puede eliminar.'),
            };
        } catch (QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar porque tiene registros relacionados.',
            ], 409);
        }

        return response()->json([
            'message' => $this->deletedMessage($relation),
            'deleted' => true,
        ]);
    }

    public function updateStatus(Request $request, string $relation, int $id): JsonResponse
    {
        $this->ensureRelation($relation);
        $companyId = (int) $request->user()->company_id;
        $this->ensureItemExists($companyId, $relation, $id);
        
        $validated = $this->validateStatusUpdate($request);

        $table = match ($relation) {
            'suppliers' => 'suppliers',
            'warehouses' => 'warehouses',
            'branches' => 'branches',
            'companies' => 'companies',
        };

        DB::table($table)
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update(['status' => $validated['status']]);

        $item = DB::table($table)
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente.',
            'data' => [
                'id' => (int) $item->id,
                'status' => (bool) $item->status,
                'status_label' => $item->status ? 'Activo' : 'Inactivo',
            ],
        ], 200);
    }

    private function suppliers(int $companyId)
    {
        return DB::table('suppliers')
            ->where('suppliers.company_id', $companyId)
            ->select([
                'suppliers.id',
                'suppliers.code',
                'suppliers.name',
                'suppliers.tax_id',
                'suppliers.phone',
                'suppliers.email',
                'suppliers.website',
                'suppliers.address',
                'suppliers.contact_person',
                'suppliers.contact_phone',
                'suppliers.contact_email',
                'suppliers.payment_term_id',
                'suppliers.credit_limit',
                'suppliers.credit_days',
                'suppliers.bank_name',
                'suppliers.bank_account',
                'suppliers.notes',
                'suppliers.payment_days',
                'suppliers.status',
            ])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('products')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('products.supplier_id', 'suppliers.id')
                    ->where('products.company_id', $companyId);
            }, 'products_count')
            ->selectSub(function ($query) {
                $query->from('purchases')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('purchases.supplier_id', 'suppliers.id');
            }, 'purchases_count')
            ->selectSub(function ($query) {
                $query->from('accounts_payable')
                    ->selectRaw('COALESCE(SUM(balance), 0)')
                    ->whereColumn('accounts_payable.supplier_id', 'suppliers.id');
            }, 'payable_balance')
            ->orderBy('suppliers.name')
            ->get()
            ->map(fn ($supplier) => [
                'id' => (int) $supplier->id,
                'code' => (string) ($supplier->code ?? ''),
                'name' => (string) $supplier->name,
                'tax_id' => $supplier->tax_id,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'website' => $supplier->website,
                'address' => $supplier->address,
                'contact_person' => $supplier->contact_person,
                'contact_phone' => $supplier->contact_phone,
                'contact_email' => $supplier->contact_email,
                'payment_term_id' => $supplier->payment_term_id ? (int) $supplier->payment_term_id : null,
                'credit_limit' => $supplier->credit_limit !== null ? (float) $supplier->credit_limit : null,
                'credit_days' => $supplier->credit_days !== null ? (int) $supplier->credit_days : null,
                'bank_name' => $supplier->bank_name,
                'bank_account' => $supplier->bank_account,
                'notes' => $supplier->notes,
                'payment_days' => (int) ($supplier->payment_days ?? 0),
                'status' => (int) ($supplier->status ?? 0),
                'products_count' => (int) $supplier->products_count,
                'purchases_count' => (int) $supplier->purchases_count,
                'payable_balance' => (float) $supplier->payable_balance,
            ])
            ->values();
    }

    private function warehouses(int $companyId)
    {
        return DB::table('warehouses')
            ->leftJoin('branches', 'branches.id', '=', 'warehouses.branch_id')
            ->where('warehouses.company_id', $companyId)
            ->select([
                'warehouses.id',
                'warehouses.branch_id',
                'warehouses.name',
                'warehouses.address',
                'warehouses.manager_name',
                'branches.name as branch_name',
            ])
            ->selectSub(function ($query) {
                $query->from('inventory_stock')
                    ->selectRaw('COUNT(DISTINCT product_id)')
                    ->whereColumn('inventory_stock.warehouse_id', 'warehouses.id');
            }, 'products_count')
            ->selectSub(function ($query) {
                $query->from('inventory_stock')
                    ->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->whereColumn('inventory_stock.warehouse_id', 'warehouses.id');
            }, 'stock_units')
            ->orderBy('branches.name')
            ->orderBy('warehouses.name')
            ->get()
            ->map(fn ($warehouse) => [
                'id' => (int) $warehouse->id,
                'name' => (string) ($warehouse->name ?? 'Almacen'),
                'branch_id' => $warehouse->branch_id ? (int) $warehouse->branch_id : null,
                'branch_name' => $warehouse->branch_name,
                'address' => $warehouse->address,
                'manager_name' => $warehouse->manager_name,
                'products_count' => (int) $warehouse->products_count,
                'stock_units' => (float) $warehouse->stock_units,
            ])
            ->values();
    }

    private function branches(int $companyId)
    {
        return DB::table('branches')
            ->where('branches.company_id', $companyId)
            ->select([
                'branches.id',
                'branches.uuid',
                'branches.name',
                'branches.phone',
                'branches.address',
                'branches.status',
            ])
            ->selectSub(function ($query) use ($companyId) {
                $query->from('warehouses')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('warehouses.branch_id', 'branches.id')
                    ->where('warehouses.company_id', $companyId);
            }, 'warehouses_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('inventory_stock')
                    ->join('warehouses', 'warehouses.id', '=', 'inventory_stock.warehouse_id')
                    ->selectRaw('COUNT(DISTINCT inventory_stock.product_id)')
                    ->whereColumn('warehouses.branch_id', 'branches.id')
                    ->where('warehouses.company_id', $companyId);
            }, 'products_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('users')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('users.branch_id', 'branches.id')
                    ->where('users.company_id', $companyId);
            }, 'users_count')
            ->orderBy('branches.name')
            ->get()
            ->map(fn ($branch) => [
                'id' => (int) $branch->id,
                'uuid' => $branch->uuid,
                'name' => (string) $branch->name,
                'phone' => $branch->phone,
                'address' => $branch->address,
                'status' => (int) ($branch->status ?? 0),
                'warehouses_count' => (int) $branch->warehouses_count,
                'products_count' => (int) $branch->products_count,
                'users_count' => (int) $branch->users_count,
            ])
            ->values();
    }

    private function companies(int $companyId)
    {
        return DB::table('companies')
            ->where('companies.id', $companyId)
            ->select([
                'companies.id',
                'companies.uuid',
                'companies.name',
                'companies.legal_name',
                'companies.tax_id',
                'companies.phone',
                'companies.email',
                'companies.commercial_address as address',
                'companies.logo',
                'companies.currency_id',
                'companies.status',
            ])
            ->selectSub(function ($query) {
                $query->from('branches')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('branches.company_id', 'companies.id');
            }, 'branches_count')
            ->selectSub(function ($query) {
                $query->from('warehouses')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('warehouses.company_id', 'companies.id');
            }, 'warehouses_count')
            ->selectSub(function ($query) {
                $query->from('suppliers')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('suppliers.company_id', 'companies.id');
            }, 'suppliers_count')
            ->selectSub(function ($query) {
                $query->from('products')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('products.company_id', 'companies.id');
            }, 'products_count')
            ->get()
            ->map(fn ($company) => [
                'id' => (int) $company->id,
                'uuid' => $company->uuid,
                'name' => (string) $company->name,
                'legal_name' => $company->legal_name,
                'tax_id' => $company->tax_id,
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'logo' => $company->logo,
                'currency_id' => $company->currency_id !== null ? (int) $company->currency_id : null,
                'status' => (int) ($company->status ?? 0),
                'branches_count' => (int) $company->branches_count,
                'warehouses_count' => (int) $company->warehouses_count,
                'suppliers_count' => (int) $company->suppliers_count,
                'products_count' => (int) $company->products_count,
            ])
            ->values();
    }

    private function storeSupplier(Request $request, int $companyId): int
    {
        $validated = $this->supplierValidation($request, $companyId);
        $validated['code'] = $this->generateSupplierCode($companyId);

        return DB::table('suppliers')->insertGetId([
            ...$this->supplierData($validated, $companyId),
            'company_id' => $companyId,
            'created_at' => now(),
        ]);
    }

    private function updateSupplier(Request $request, int $companyId, int $id): void
    {
        $validated = $this->supplierValidation($request, $companyId, $id);

        $existingCode = DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->value('code');

        $validated['code'] = !empty(trim((string) $existingCode))
            ? $existingCode
            : $this->generateSupplierCode($companyId);

        DB::table('suppliers')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update($this->supplierData($validated, $companyId));
    }

    private function storeWarehouse(Request $request, int $companyId): int
    {
        $validated = $this->warehouseValidation($request, $companyId);

        return DB::table('warehouses')->insertGetId([
            ...$this->warehouseData($validated),
            'company_id' => $companyId,
            'created_at' => now(),
        ]);
    }

    private function updateWarehouse(Request $request, int $companyId, int $id): void
    {
        $validated = $this->warehouseValidation($request, $companyId);

        DB::table('warehouses')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update($this->warehouseData($validated));
    }

    private function storeBranch(Request $request, int $companyId): int
    {
        $validated = $this->branchValidation($request);

        return DB::table('branches')->insertGetId([
            ...$this->branchData($validated),
            'company_id' => $companyId,
            'uuid' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    private function updateBranch(Request $request, int $companyId, int $id): void
    {
        $validated = $this->branchValidation($request);

        DB::table('branches')
            ->where('id', $id)
            ->where('company_id', $companyId)
            ->update($this->branchData($validated));
    }

    private function updateCompany(Request $request, int $companyId): void
    {
        $validated = $this->companyValidation($request);

        $companyData = [
            'name' => $this->cleanName($validated['name']),
            'legal_name' => $this->nullableText($validated['legal_name'] ?? null),
            'tax_id' => $this->nullableText($validated['tax_id'] ?? null),
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'email' => $this->nullableText($validated['email'] ?? null),
            'commercial_address' => $this->nullableText($validated['commercial_address'] ?? $validated['address'] ?? null),
            'status' => $this->statusValue($validated['status'] ?? 1),
            'updated_at' => now(),
        ];

        if (array_key_exists('currency_id', $validated)) {
            $companyData['currency_id'] = $validated['currency_id'] !== null ? (int) $validated['currency_id'] : 1;
        }

        DB::table('companies')
            ->where('id', $companyId)
            ->update($companyData);
    }

    public function paymentTerms(Request $request): JsonResponse
    {
        $terms = DB::table('payment_terms')
            ->where('is_active', true)
            ->select(['id', 'name', 'days'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $terms]);
    }

    private function supplierValidation(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'website' => ['nullable', 'url', 'max:200'],
            'address' => ['nullable', 'string'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'payment_term_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_terms', 'id'),
            ],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
        ], $this->messages());
    }

    private function warehouseValidation(Request $request, int $companyId): array
    {
        return $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
            'manager_name' => ['nullable', 'string', 'max:120'],
        ], $this->messages());
    }

    private function branchValidation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
        ], $this->messages());
    }

    private function companyValidation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string'],
            'commercial_address' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'status' => ['required', Rule::in(['Activo', 'Inactivo', 'activo', 'inactivo', 1, 0, '1', '0', true, false])],
        ], $this->messages());
    }

    private function supplierData(array $validated, int $companyId): array
    {
        return [
            'code' => $this->nullableText($validated['code'] ?? null),
            'name' => $this->cleanName($validated['name']),
            'tax_id' => $this->nullableText($validated['tax_id'] ?? null),
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'email' => $this->nullableText($validated['email'] ?? null),
            'website' => $this->nullableText($validated['website'] ?? null),
            'address' => $this->nullableText($validated['address'] ?? null),
            'contact_person' => $this->nullableText($validated['contact_person'] ?? null),
            'contact_phone' => $this->nullableText($validated['contact_phone'] ?? null),
            'contact_email' => $this->nullableText($validated['contact_email'] ?? null),
            'payment_term_id' => isset($validated['payment_term_id']) && $validated['payment_term_id'] !== null ? (int) $validated['payment_term_id'] : null,
            'currency_id' => $this->supplierCurrencyId($companyId),
            'bank_name' => $this->nullableText($validated['bank_name'] ?? null),
            'bank_account' => $this->nullableText($validated['bank_account'] ?? null),
            'credit_limit' => array_key_exists('credit_limit', $validated) ? $validated['credit_limit'] : null,
            'credit_days' => array_key_exists('credit_days', $validated) && $validated['credit_days'] !== null ? (int) $validated['credit_days'] : null,
            'notes' => $this->nullableText($validated['notes'] ?? null),
            'status' => $this->statusValue($validated['status'] ?? 1),
        ];
    }

    private function warehouseData(array $validated): array
    {
        return [
            'branch_id' => (int) ($validated['branch_id'] ?? 0) ?: null,
            'name' => $this->cleanName($validated['name']),
            'address' => $this->nullableText($validated['address'] ?? null),
            'manager_name' => $this->nullableText($validated['manager_name'] ?? null),
        ];
    }

    private function generateSupplierCode(int $companyId): string
    {
        $codes = DB::table('suppliers')
            ->where('company_id', $companyId)
            ->where('code', 'like', 'PRV-%')
            ->pluck('code');

        $next = 1;

        foreach ($codes as $code) {
            if (preg_match('/^PRV-(\d+)$/', (string) $code, $matches)) {
                $next = max($next, (int) $matches[1] + 1);
            }
        }

        return sprintf('PRV-%06d', $next);
    }

    private function supplierCurrencyId(int $companyId): int
    {
        return (int) DB::table('companies')
            ->where('id', $companyId)
            ->value('currency_id');
    }

    private function branchData(array $validated): array
    {
        return [
            'name' => $this->cleanName($validated['name']),
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'address' => $this->nullableText($validated['address'] ?? null),
            'status' => $this->statusValue($validated['status'] ?? 1),
        ];
    }

    private function findItem(int $companyId, string $relation, int $id): array
    {
        $item = match ($relation) {
            'suppliers' => $this->suppliers($companyId)->firstWhere('id', $id),
            'warehouses' => $this->warehouses($companyId)->firstWhere('id', $id),
            'branches' => $this->branches($companyId)->firstWhere('id', $id),
            'companies' => $this->companies($companyId)->firstWhere('id', $companyId),
        };

        abort_unless($item, 404, 'Registro no encontrado.');

        return $item;
    }

    private function ensureItemExists(int $companyId, string $relation, int $id): void
    {
        $exists = match ($relation) {
            'suppliers' => DB::table('suppliers')->where('id', $id)->where('company_id', $companyId)->exists(),
            'warehouses' => DB::table('warehouses')->where('id', $id)->where('company_id', $companyId)->exists(),
            'branches' => DB::table('branches')->where('id', $id)->where('company_id', $companyId)->exists(),
            'companies' => $id === $companyId && DB::table('companies')->where('id', $companyId)->exists(),
        };

        abort_unless($exists, 404, 'Registro no encontrado.');
    }

    private function deleteBlockMessage(int $companyId, string $relation, int $id): ?string
    {
        if ($relation === 'companies') {
            return 'La empresa actual no se puede eliminar.';
        }

        if ($relation === 'suppliers') {
            if ($this->countRows('products', 'supplier_id', $id, $companyId) > 0) {
                return 'No se puede eliminar el proveedor porque tiene productos relacionados.';
            }

            if ($this->countRows('purchases', 'supplier_id', $id) > 0 || $this->countRows('accounts_payable', 'supplier_id', $id) > 0) {
                return 'No se puede eliminar el proveedor porque tiene compras o cuentas por pagar.';
            }
        }

        if ($relation === 'warehouses') {
            foreach (['inventory_stock', 'inventory_movements', 'inventory_lots', 'sales', 'purchases'] as $table) {
                if ($this->countRows($table, 'warehouse_id', $id) > 0) {
                    return 'No se puede eliminar el almacen porque tiene inventario o documentos relacionados.';
                }
            }
        }

        if ($relation === 'branches') {
            foreach (['users', 'warehouses', 'sales', 'purchases', 'cash_registers', 'expenses', 'journal_entries'] as $table) {
                if ($this->countRows($table, 'branch_id', $id, in_array($table, ['users', 'warehouses'], true) ? $companyId : null) > 0) {
                    return 'No se puede eliminar la sucursal porque tiene registros relacionados.';
                }
            }
        }

        return null;
    }

    private function countRows(string $table, string $field, int $id, ?int $companyId = null): int
    {
        $query = DB::table($table)->where($field, $id);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        return $query->count();
    }

    private function ensureRelation(string $relation): void
    {
        abort_unless(in_array($relation, self::RELATIONS, true), 404, 'Relacion no encontrada.');
    }

    private function savedMessage(string $relation): string
    {
        return match ($relation) {
            'suppliers' => 'Proveedor guardado correctamente.',
            'warehouses' => 'Almacen guardado correctamente.',
            'branches' => 'Sucursal guardada correctamente.',
            'companies' => 'Empresa actualizada correctamente.',
        };
    }

    private function deletedMessage(string $relation): string
    {
        return match ($relation) {
            'suppliers' => 'Proveedor eliminado correctamente.',
            'warehouses' => 'Almacen eliminado correctamente.',
            'branches' => 'Sucursal eliminada correctamente.',
            'companies' => 'Empresa eliminada correctamente.',
        };
    }

    private function statusValue(mixed $status): int
    {
        return in_array($status, ['Activo', 'activo', 1, '1', true], true) ? 1 : 0;
    }

    private function cleanName(string $value): string
    {
        return trim($value);
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre.',
            'code.unique' => 'Ya existe un registro con ese codigo.',
            'email.email' => 'Ingresa un correo valido.',
            'branch_id.exists' => 'La sucursal seleccionada no existe.',
            'status.required' => 'Selecciona el estado.',
        ];
    }
}
