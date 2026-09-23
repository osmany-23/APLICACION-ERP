<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Sale;
use App\Services\CompanySettingsService;
use App\Services\SalesService;
use App\Traits\AuthorizesSales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    use AuthorizesSales;

    public function __construct(
        private SalesService $salesService,
        private CompanySettingsService $companySettings,
    ) {
    }

    /**
     * Datos de empresa + configuracion de recibos que necesita el ticket
     * impreso (ReceiptTicket.tsx), accesibles para cualquiera que pueda
     * facturar (no exige el permiso de "general_settings" que si exige
     * GeneralSettingsController — un cajero normal no lo tiene).
     */
    public function receiptConfig(Request $request): JsonResponse
    {
        $this->authorizeSales($request);
        $companyId = (int) $request->user()->company_id;

        $company = Company::find($companyId);
        $receipts = $this->companySettings->all($companyId)['receipts'] ?? [];

        return response()->json([
            'data' => [
                'company_name' => $company ? ($company->short_name ?: $company->name) : null,
                'company_tax_id' => $company?->tax_id,
                // Respaldo para el bloque "Datos de la empresa" del ticket
                // cuando la sucursal de la venta no tiene su propio
                // telefono/direccion cargados todavia.
                'company_phone' => $company ? ($company->phone ?: $company->mobile) : null,
                'company_address' => $company ? ($company->commercial_address ?: $company->fiscal_address ?: $company->full_address) : null,
                'show_logo' => (bool) ($receipts['show_logo'] ?? true),
                'display_name' => $receipts['display_name'] ?: null,
                'footer_note' => $receipts['footer_note'] ?? '',
                'claim_days' => (int) ($receipts['claim_days'] ?? 5),
            ],
        ]);
    }

    /**
     * Tipo de cambio USD -> moneda base de la empresa, para que el TPV
     * pueda convertir un pago en dolares y calcular el vuelto en la moneda
     * en la que realmente se registra la venta. Vive aqui (no en
     * Configuracion) porque cualquier rol que pueda facturar necesita
     * poder leerlo, sin que eso le de acceso al resto de "settings"
     * generales de la empresa.
     */
    public function exchangeRate(Request $request): JsonResponse
    {
        $this->authorizeSales($request);
        $companyId = (int) $request->user()->company_id;

        $company = DB::table('companies')->where('id', $companyId)->first();
        $baseCurrency = $company ? DB::table('currencies')->where('id', (int) $company->currency_id)->first() : null;
        $usd = DB::table('currencies')->where('code', 'USD')->where('is_active', true)->first();

        if (! $baseCurrency || ! $usd || (int) $usd->id === (int) $baseCurrency->id) {
            return response()->json(['data' => null]);
        }

        $rate = DB::table('exchange_rates')
            ->where(function ($query) use ($companyId) {
                $query->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->where('from_currency_id', $usd->id)
            ->where('to_currency_id', $baseCurrency->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (! $rate) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'rate' => (float) $rate->rate,
                'date' => $rate->date,
                'base_currency' => ['id' => (int) $baseCurrency->id, 'code' => $baseCurrency->code, 'symbol' => $baseCurrency->symbol],
                'foreign_currency' => ['id' => (int) $usd->id, 'code' => $usd->code, 'symbol' => $usd->symbol],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeSales($request);
        $companyId = (int) $request->user()->company_id;

        $query = Sale::query()
            ->with([
                'customer:id,full_name,business_name,code,tax_id,phone,address',
                'salesperson:id,full_name',
                'createdBy:id,full_name',
                'branch:id,name,full_address,address,phone',
                'paymentMethod:id,name,cash,card,bank',
            ])
            ->where('company_id', $companyId)
            ->orderByDesc('sale_date')
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', (int) $customerId);
        }

        if ($salespersonId = $request->query('salesperson_id')) {
            $query->where('salesperson_id', (int) $salespersonId);
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

    /**
     * Registra un abono (parcial o por el saldo completo) sobre una venta
     * "Pendiente de pago" (contra entrega): a diferencia de confirm() (que
     * finaliza un borrador/proforma), esta accion recibe el monto y el
     * metodo de pago realmente recibido, y puede llamarse varias veces
     * hasta completar el saldo. Ver SalesService::registerPendingPayment().
     */
    public function registerPayment(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $saleModel = $this->findSale($companyId, $sale);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ], [
            'amount.required' => 'Ingresa el monto del abono.',
            'amount.min' => 'El monto debe ser mayor que cero.',
            'payment_method_id.required' => 'Selecciona el metodo de pago recibido.',
            'payment_method_id.exists' => 'El metodo de pago indicado no existe.',
        ]);

        $saleModel = $this->salesService->registerPendingPayment(
            $saleModel,
            (float) $validated['amount'],
            (int) $validated['payment_method_id'],
            (int) $user->id,
            $validated['reference'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => $saleModel->status === 'COMPLETED'
                ? 'Abono registrado. La factura quedo completamente pagada.'
                : 'Abono registrado. Saldo pendiente: '.number_format((float) $saleModel->balance_due, 2).'.',
            'item' => $this->detailPayload($saleModel),
        ]);
    }

    public function cancel(Request $request, int $sale): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeSales($request);
        $companyId = (int) $user->company_id;

        $validated = $request->validate([
            'authorization_pin' => ['required', 'string', 'digits:4'],
        ], [
            'authorization_pin.required' => 'Se requiere el codigo de un administrador para anular.',
            'authorization_pin.digits' => 'El codigo debe tener 4 digitos.',
        ]);

        $saleModel = $this->findSale($companyId, $sale);
        $saleModel = $this->salesService->cancelSale($saleModel, (int) $user->id, $validated['authorization_pin']);

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
            'payment_reference' => ['nullable', 'string', 'max:120'],
            // Un cliente puede pagar con una mezcla de billetes en las dos
            // monedas a la vez (ej. un billete de $10 y uno de C$500 en la
            // misma venta): se aceptan ambos montos por separado.
            'amount_tendered_base' => ['nullable', 'numeric', 'min:0'],
            'amount_tendered_foreign' => ['nullable', 'numeric', 'min:0'],
            'change_amount' => ['nullable', 'numeric', 'min:0'],
            'sale_date' => ['nullable', 'date'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'confirm' => ['sometimes', 'boolean'],
            'is_pending_payment' => ['sometimes', 'boolean'],
            'override_credit_limit' => ['sometimes', 'boolean'],
            'salesperson_id' => ['nullable', 'integer'],
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
            // sale_date es DATE puro (sin hora); created_at si trae hora
            // real, lo usa el frontend para mostrar "fecha con AM/PM" en
            // el historial de facturas.
            'created_at' => $sale->created_at?->toIso8601String(),
            'status' => $sale->status,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer?->full_name,
            'customer_code' => $sale->customer?->code,
            // Solo se muestran en el ticket si tienen valor: un "cliente
            // rapido" creado al vuelo (QuickCustomerModal) normalmente no
            // trae RUC/direccion, mientras que un cliente registrado con
            // esos datos si los lleva — no hay ningun flag especial de
            // "cliente rapido", es la misma tabla customers para ambos.
            'customer_business_name' => $sale->customer?->business_name,
            'customer_tax_id' => $sale->customer?->tax_id,
            'customer_phone' => $sale->customer?->phone,
            'customer_address' => $sale->customer?->address,
            'branch' => $sale->branch ? [
                'id' => $sale->branch->id,
                'name' => $sale->branch->name,
                'address' => $sale->branch->full_address ?: $sale->branch->address,
                'phone' => $sale->branch->phone,
            ] : null,
            'payment_method_name' => $sale->paymentMethod?->name,
            'payment_method_flags' => $sale->paymentMethod ? [
                'cash' => (bool) $sale->paymentMethod->cash,
                'card' => (bool) $sale->paymentMethod->card,
                'bank' => (bool) $sale->paymentMethod->bank,
            ] : null,
            'payment_reference' => $sale->payment_reference,
            'amount_tendered_base' => $sale->amount_tendered_base !== null ? (float) $sale->amount_tendered_base : null,
            'amount_tendered_foreign' => $sale->amount_tendered_foreign !== null ? (float) $sale->amount_tendered_foreign : null,
            'change_amount' => $sale->change_amount !== null ? (float) $sale->change_amount : null,
            'subtotal' => (float) $sale->subtotal,
            'discount' => (float) $sale->discount,
            'shipping' => (float) $sale->shipping,
            'tax' => (float) $sale->tax,
            'total' => (float) $sale->total,
            'paid_amount' => (float) $sale->paid_amount,
            'balance_due' => (float) $sale->balance_due,
            'ir_withholding_rate' => $sale->ir_withholding_rate !== null ? (float) $sale->ir_withholding_rate : null,
            'ir_withholding_amount' => (float) $sale->ir_withholding_amount,
            // OJO: antes esto era "payment_method_id === null", pero una
            // venta Pendiente de pago TAMBIEN tiene payment_method_id null
            // (todavia no se sabe como se va a cobrar) sin ser credito
            // real del cliente. "is_credit" se mantiene solo por
            // compatibilidad con quien ya lo consuma; transaction_status
            // (abajo) es la fuente de verdad para distinguir los 3 casos.
            'is_credit' => $sale->status !== 'PENDING' && $sale->payment_method_id === null,
            'transaction_status' => $this->transactionStatus($sale),
            'created_by' => $sale->created_by,
            'created_by_name' => $sale->createdBy?->full_name,
            'salesperson_id' => $sale->salesperson_id,
            'salesperson_name' => $sale->salesperson?->full_name,
        ];
    }

    /**
     * Tercer estado real de negocio (distinto del workflow status
     * DRAFT/PENDING/COMPLETED/CANCELLED): como quedo la plata de la venta.
     * 'PENDING_PAYMENT' = pendiente de pago / contra entrega, todavia no
     * confirmada. 'CREDIT' = credito real del cliente (cuenta por
     * cobrar). 'PAID' = ya cobrada (de contado, o pendiente de pago ya
     * confirmada).
     */
    private function transactionStatus(Sale $sale): string
    {
        if ($sale->status === 'PENDING') {
            return 'PENDING_PAYMENT';
        }

        return $sale->payment_method_id === null ? 'CREDIT' : 'PAID';
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

        // Politicas de credito/mora del cliente, para mostrarlas en el
        // ticket/factura cuando la venta quedo a credito (ver
        // ReceiptTicket.tsx) — el cliente debe saber desde cuando se le
        // empieza a cobrar mora si no paga a tiempo.
        $payload['due_date'] = $sale->accountsReceivable?->due_date?->toDateString()
            ?? ($sale->customer?->credit_days
                ? date('Y-m-d', strtotime($sale->sale_date?->toDateString().' +'.(int) $sale->customer->credit_days.' days'))
                : null);
        $payload['customer_credit_days'] = $sale->customer?->credit_days;
        $payload['customer_applies_late_fee'] = (bool) ($sale->customer?->applies_late_fee ?? false);
        $payload['customer_late_fee_percentage'] = $sale->customer?->late_fee_percentage !== null
            ? (float) $sale->customer->late_fee_percentage
            : null;
        $payload['customer_late_fee_period_unit'] = $sale->customer?->late_fee_period_unit;
        $payload['payment_confirmed_at'] = $sale->payment_confirmed_at?->toIso8601String();
        $payload['payment_confirmed_by_name'] = $sale->paymentConfirmedBy?->full_name;
        $payload['cancelled_at'] = $sale->cancelled_at?->toIso8601String();
        $payload['cancelled_by_name'] = $sale->cancelledBy?->full_name;
        $payload['cancellation_authorized_by_name'] = $sale->cancellationAuthorizedBy?->full_name;

        $payload['payments'] = $sale->payments->map(fn ($payment) => [
            'id' => $payment->id,
            'amount' => (float) $payment->amount,
            'payment_method_name' => $payment->paymentMethod?->name,
            'reference' => $payment->reference,
            'created_at' => $payment->created_at?->toIso8601String(),
            'created_by_name' => $payment->createdBy?->full_name,
        ])->values();

        $payload['credit_notes'] = $sale->creditNotes->map(fn ($note) => [
            'id' => $note->id,
            'number' => $note->return_number,
            'total' => (float) $note->total,
            'reason' => $note->reason,
            'created_at' => $note->created_at?->toIso8601String(),
        ])->values();

        $payload['debit_notes'] = $sale->debitNotes->map(fn ($note) => [
            'id' => $note->id,
            'number' => $note->debit_number,
            'total' => (float) $note->total,
            'reason' => $note->reason,
            'created_at' => $note->created_at?->toIso8601String(),
        ])->values();

        $productNames = DB::table('products')
            ->whereIn('id', $sale->items->pluck('product_id'))
            ->pluck('short_name', 'id');

        // Unidad de venta del producto (ej. CAJA, UND, LTR, GAL) para la
        // columna "UND" del ticket — mismo fallback 'UND' que ya usa
        // ProductController cuando el producto no tiene unidad de venta
        // configurada.
        $productUnits = DB::table('products')
            ->leftJoin('units', 'units.id', '=', 'products.sale_unit_id')
            ->whereIn('products.id', $sale->items->pluck('product_id'))
            ->select(['products.id', DB::raw("COALESCE(units.short_name, units.name, 'UND') as unit")])
            ->get()
            ->pluck('unit', 'id');

        $payload['items'] = $sale->items->map(fn ($item) => [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $productNames->get($item->product_id, 'Producto'),
            'unit' => $productUnits->get($item->product_id, 'UND'),
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'unit_cost' => (float) $item->unit_cost,
            'discount' => (float) $item->discount,
            'tax' => (float) $item->tax,
            'subtotal' => (float) $item->subtotal,
            'total' => (float) $item->total,
            'has_warranty' => (bool) $item->has_warranty,
            'warranty_type' => $item->warranty_type,
            'warranty_expires_at' => $item->warranty_expires_at?->toDateString(),
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
            ->with([
                'items.taxes',
                'customer:id,full_name,business_name,code,tax_id,phone,address,credit_days,applies_late_fee,late_fee_percentage,late_fee_period_unit',
                'salesperson:id,full_name',
                'createdBy:id,full_name',
                'branch:id,name,full_address,address,phone',
                'paymentMethod:id,name,cash,card,bank',
                'accountsReceivable:id,due_date',
                'payments.paymentMethod:id,name',
                'payments.createdBy:id,full_name',
                'creditNotes',
                'debitNotes',
            ])
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        abort_unless($sale, 404, 'Factura no encontrada.');

        return $sale;
    }

}
