<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(private SalesService $salesService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSales($request);
        $companyId = (int) $request->user()->company_id;

        $query = Sale::query()
            ->with('customer:id,full_name,code')
            ->where('company_id', $companyId)
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', (int) $customerId);
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('sale_date', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->whereDate('sale_date', '<=', $to);
        }

        $sales = $query->get()->map(fn (Sale $sale) => $this->listPayload($sale));

        return response()->json([
            'data' => $sales,
            'meta' => [
                'total' => $sales->count(),
                'total_amount' => round((float) $sales->sum('total'), 2),
                'balance_due' => round((float) $sales->sum('balance_due'), 2),
            ],
        ]);
    }

    public function show(Request $request, int $sale): JsonResponse
    {
        $this->authorizeSales($request);
        $companyId = (int) $request->user()->company_id;

        $saleModel = $this->findSale($companyId, $sale);

        return response()->json(['item' => $this->detailPayload($saleModel)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);

        $validated = $this->validatedSale($request);
        $companyId = (int) $user->company_id;

        $sale = $this->salesService->createSale($companyId, (int) $user->id, $validated);

        return response()->json([
            'message' => $sale->status === 'COMPLETED' ? 'Factura confirmada correctamente.' : 'Borrador de factura guardado.',
            'item' => $this->detailPayload($sale),
        ], 201);
    }

    public function update(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $saleModel = $this->findSale($companyId, $sale);

        abort_unless($saleModel->status === 'DRAFT', 409, 'Solo se pueden editar facturas en borrador.');

        $validated = $request->validate([
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'payment_term_id' => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],
            'notes' => ['nullable', 'string'],
        ]);

        $saleModel->update($validated);

        return response()->json([
            'message' => 'Factura actualizada correctamente.',
            'item' => $this->detailPayload($saleModel->fresh(['items.taxes'])),
        ]);
    }

    public function confirm(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $saleModel = $this->findSale($companyId, $sale);
        $overrideCreditLimit = (bool) $request->boolean('override_credit_limit');

        $saleModel = $this->salesService->confirmSale($saleModel, (int) $user->id, $overrideCreditLimit);

        return response()->json([
            'message' => 'Factura confirmada correctamente.',
            'item' => $this->detailPayload($saleModel),
        ]);
    }

    public function cancel(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $saleModel = $this->findSale($companyId, $sale);
        $saleModel = $this->salesService->cancelSale($saleModel, (int) $user->id);

        return response()->json([
            'message' => 'Factura anulada correctamente.',
            'item' => $this->detailPayload($saleModel),
        ]);
    }

    private function validatedSale(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'document_type_id' => ['nullable', 'integer'],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')],
            'payment_term_id' => ['nullable', 'integer', Rule::exists('payment_terms', 'id')],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'sale_date' => ['nullable', 'date'],
            'confirm' => ['sometimes', 'boolean'],
            'override_credit_limit' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ], [
            'customer_id.required' => 'Selecciona el cliente.',
            'warehouse_id.required' => 'Selecciona el almacen de salida.',
            'items.required' => 'La factura debe tener al menos un producto.',
            'items.min' => 'La factura debe tener al menos un producto.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor que cero.',
        ]);
    }

    private function listPayload(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'sale_date' => $sale->sale_date?->toDateString(),
            'status' => $sale->status,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer?->full_name,
            'customer_code' => $sale->customer?->code,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'tax' => (float) $sale->tax,
            'total' => (float) $sale->total,
            'paid_amount' => (float) $sale->paid_amount,
            'balance_due' => (float) $sale->balance_due,
            'is_credit' => $sale->payment_method_id === null,
        ];
    }

    private function detailPayload(Sale $sale): array
    {
        $payload = $this->listPayload($sale);
        $payload['warehouse_id'] = $sale->warehouse_id;
        $payload['branch_id'] = $sale->branch_id;
        $payload['payment_method_id'] = $sale->payment_method_id;
        $payload['payment_term_id'] = $sale->payment_term_id;
        $payload['currency_id'] = $sale->currency_id;
        $payload['exchange_rate'] = (float) $sale->exchange_rate;
        $payload['notes'] = $sale->notes;
        $payload['accounts_receivable_id'] = $sale->accounts_receivable_id;
        $payload['journal_entry_id'] = $sale->journal_entry_id;

        $productNames = DB::table('products')
            ->whereIn('id', $sale->items->pluck('product_id'))
            ->pluck('short_name', 'id');

        $payload['items'] = $sale->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $productNames->get($item->product_id, 'Producto'),
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
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

    private function findSale(int $companyId, int $id): Sale
    {
        $sale = Sale::query()
            ->with(['items.taxes', 'customer:id,full_name,code'])
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        abort_unless($sale, 404, 'Factura no encontrada.');

        return $sale;
    }

    private function authorizeSales(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para facturacion.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'facturacion')
            ->whereIn('permissions.action_name', ['manage', 'view'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar facturacion.');
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }
}
