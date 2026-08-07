<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Services\PurchasesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function __construct(private PurchasesService $purchasesService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizePurchases($request);
        $companyId = (int) $request->user()->company_id;

        $query = Purchase::query()
            ->where('company_id', $companyId)
            ->orderByDesc('purchase_date')
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', (int) $supplierId);
        }

        $purchaseModels = $query->get();
        $suppliers = DB::table('suppliers')->whereIn('id', $purchaseModels->pluck('supplier_id'))->get(['id', 'name', 'code'])->keyBy('id');
        $purchases = $purchaseModels->map(fn (Purchase $purchase) => $this->listPayload($purchase, $suppliers->get($purchase->supplier_id)));

        return response()->json([
            'data' => $purchases,
            'meta' => [
                'total' => $purchases->count(),
                'total_amount' => round((float) $purchases->sum('total'), 2),
                'balance_due' => round((float) $purchases->sum('balance_due'), 2),
            ],
        ]);
    }

    public function show(Request $request, int $purchase): JsonResponse
    {
        $this->authorizePurchases($request);
        $companyId = (int) $request->user()->company_id;

        $purchaseModel = $this->findPurchase($companyId, $purchase);

        return response()->json(['item' => $this->detailPayload($purchaseModel)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePurchases($request);

        $validated = $this->validatedPurchase($request);
        $companyId = (int) $user->company_id;

        $purchase = $this->purchasesService->createPurchase($companyId, (int) $user->id, $validated);

        return response()->json([
            'message' => $purchase->status === 'RECEIVED' ? 'Compra recibida correctamente.' : 'Borrador de compra guardado.',
            'item' => $this->detailPayload($purchase),
        ], 201);
    }

    public function update(Request $request, int $purchase): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePurchases($request);
        $companyId = (int) $user->company_id;

        $purchaseModel = $this->findPurchase($companyId, $purchase);

        abort_unless($purchaseModel->status === 'DRAFT', 409, 'Solo se pueden editar compras en borrador.');

        $validated = $request->validate([
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'payment_term_id' => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        $purchaseModel->update($validated);

        return response()->json([
            'message' => 'Compra actualizada correctamente.',
            'item' => $this->detailPayload($purchaseModel->fresh(['items.taxes'])),
        ]);
    }

    public function confirm(Request $request, int $purchase): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePurchases($request);
        $companyId = (int) $user->company_id;

        $purchaseModel = $this->findPurchase($companyId, $purchase);
        $purchaseModel = $this->purchasesService->confirmPurchase($purchaseModel, (int) $user->id);

        return response()->json([
            'message' => 'Compra recibida correctamente.',
            'item' => $this->detailPayload($purchaseModel),
        ]);
    }

    public function cancel(Request $request, int $purchase): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizePurchases($request);
        $companyId = (int) $user->company_id;

        $purchaseModel = $this->findPurchase($companyId, $purchase);
        $purchaseModel = $this->purchasesService->cancelPurchase($purchaseModel, (int) $user->id);

        return response()->json([
            'message' => 'Compra anulada correctamente.',
            'item' => $this->detailPayload($purchaseModel),
        ]);
    }

    private function validatedPurchase(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'payment_term_id' => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'purchase_date' => ['nullable', 'date'],
            'confirm' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'supplier_id.required' => 'Selecciona el proveedor.',
            'warehouse_id.required' => 'Selecciona el almacen de ingreso.',
            'items.required' => 'La compra debe tener al menos un producto.',
            'items.min' => 'La compra debe tener al menos un producto.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ]);
    }

    private function listPayload(Purchase $purchase, ?object $supplier = null): array
    {
        $supplier ??= DB::table('suppliers')->where('id', $purchase->supplier_id)->first(['id', 'name', 'code']);

        return [
            'id' => $purchase->id,
            'purchase_number' => $purchase->purchase_number,
            'purchase_date' => $purchase->purchase_date?->toDateString(),
            'status' => $purchase->status,
            'supplier_id' => $purchase->supplier_id,
            'supplier_name' => $supplier?->name,
            'supplier_code' => $supplier?->code,
            'subtotal' => (float) $purchase->subtotal,
            'discount' => (float) $purchase->discount,
            'tax' => (float) $purchase->tax,
            'total' => (float) $purchase->total,
            'paid_amount' => (float) $purchase->paid_amount,
            'balance_due' => (float) $purchase->balance_due,
            'is_credit' => $purchase->payment_method_id === null,
        ];
    }

    private function detailPayload(Purchase $purchase): array
    {
        $payload = $this->listPayload($purchase);
        $payload['warehouse_id'] = $purchase->warehouse_id;
        $payload['branch_id'] = $purchase->branch_id;
        $payload['payment_method_id'] = $purchase->payment_method_id;
        $payload['payment_term_id'] = $purchase->payment_term_id;
        $payload['currency_id'] = $purchase->currency_id;
        $payload['exchange_rate'] = (float) $purchase->exchange_rate;
        $payload['notes'] = $purchase->notes;
        $payload['accounts_payable_id'] = $purchase->accounts_payable_id;
        $payload['journal_entry_id'] = $purchase->journal_entry_id;

        $productNames = DB::table('products')
            ->whereIn('id', $purchase->items->pluck('product_id'))
            ->pluck('short_name', 'id');

        $payload['items'] = $purchase->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $productNames->get($item->product_id, 'Producto'),
            'quantity' => (float) $item->quantity,
            'unit_cost' => (float) $item->unit_cost,
            'discount' => (float) $item->discount,
            'tax' => (float) $item->tax,
            'subtotal' => (float) $item->subtotal,
            'total' => (float) $item->total,
            'taxes' => $item->taxes->map(fn ($tax) => [
                'tax_id' => $tax->tax_id,
                'rate' => (float) $tax->rate,
                'taxable_base' => (float) $tax->taxable_base,
                'tax_amount' => (float) $tax->tax_amount,
            ]),
        ]);

        return $payload;
    }

    private function findPurchase(int $companyId, int $id): Purchase
    {
        $purchase = Purchase::query()
            ->with('items.taxes')
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        abort_unless($purchase, 404, 'Compra no encontrada.');

        return $purchase;
    }

    private function authorizePurchases(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para compras.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'compras')
            ->whereIn('permissions.action_name', ['manage', 'view'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar compras.');
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }
}
