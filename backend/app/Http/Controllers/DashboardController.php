<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analítica del panel de control: series de tendencia (ventas, compras,
 * utilidad, flujo de caja) y rankings/distribuciones (por categoría,
 * sucursal, vendedor, cliente, método de pago, rentabilidad) listos para
 * graficar. Todo se calcula agregando en SQL (nunca trayendo listados
 * completos al frontend) y siempre escapado a la empresa del usuario
 * autenticado.
 *
 * Supuestos de negocio, documentados aquí porque no hay un modulo contable
 * de flujo de caja dedicado:
 *  - "Ventas"/"Compras" de las tendencias solo cuentan documentos
 *    confirmados (sales.status = COMPLETED, purchases.status = RECEIVED);
 *    los DRAFT/PENDING/CANCELLED no mueven inventario ni contabilidad y no
 *    deberían aparecer como actividad real del negocio.
 *  - "Utilidad bruta"/"Margen %"/"Ventas vs costo" usan
 *    sale_items.unit_cost (costo promedio capturado al momento de la
 *    venta); si es null (ventas muy antiguas antes de esa columna) se cae
 *    a products.cost como aproximación.
 *  - "Flujo de caja" es una aproximación de caja real: ventas cobradas en
 *    efectivo (payment_methods.cash = true) menos compras pagadas en
 *    efectivo, por día. No sustituye un estado de flujo de efectivo
 *    contable completo (que requeriría rastrear cada cuenta bancaria y
 *    pago parcial), pero refleja el efectivo que realmente entra/sale del
 *    negocio en el día a día.
 *  - "Productos con stock bajo"/"sin movimiento" y "Estado de cuentas por
 *    cobrar" son fotos del estado actual, no se filtran por el periodo
 *    seleccionado (no tendría sentido: el stock bajo de hoy no cambia
 *    porque el usuario mire "el año pasado").
 */
class DashboardController extends Controller
{
    private const SALE_STATUSES_COUNTED = ['COMPLETED'];

    private const PURCHASE_STATUSES_COUNTED = ['RECEIVED'];

    public function analytics(Request $request): JsonResponse
    {
        $this->authorizeDashboard($request);
        $companyId = (int) $request->user()->company_id;

        $period = (string) $request->query('period', '30d');
        $compare = $request->boolean('compare');

        $range = $this->resolveRange($period);

        $data = [
            'period' => [
                'key' => $period,
                'label' => $range['label'],
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'granularity' => $range['granularity'],
            ],
            'trends' => $this->buildTrends($companyId, $range),
            'sales_by_category' => $this->salesByCategory($companyId, $range),
            'sales_by_branch' => $this->salesByBranch($companyId, $range),
            'sales_by_seller' => $this->salesBySeller($companyId, $range),
            'top_products' => $this->topProducts($companyId, $range),
            'top_customers' => $this->topCustomers($companyId, $range),
            'payment_methods' => $this->salesByPaymentMethod($companyId, $range),
            'receivables_status' => $this->receivablesStatus($companyId),
            'low_stock_products' => $this->lowStockProducts($companyId),
            'stagnant_products' => $this->stagnantProducts($companyId),
            'profitability' => [
                'by_category' => $this->profitByCategory($companyId, $range),
                'by_product' => $this->profitByProduct($companyId, $range),
                'most_profitable' => $this->mostProfitableProducts($companyId, $range),
            ],
        ];

        if ($compare) {
            $previousRange = $this->resolvePreviousRange($range);
            $data['comparison'] = [
                'period' => [
                    'label' => $previousRange['label'],
                    'start' => $previousRange['start']->toDateString(),
                    'end' => $previousRange['end']->toDateString(),
                ],
                'trends' => $this->buildTrends($companyId, $previousRange),
            ];
        }

        return response()->json(['data' => $data]);
    }

    // ------------------------------------------------------------------
    // Rango de fechas
    // ------------------------------------------------------------------

    private function resolveRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'granularity' => 'day',
                'label' => 'Hoy',
            ],
            '7d' => [
                'start' => $now->copy()->subDays(6)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'granularity' => 'day',
                'label' => 'Últimos 7 días',
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'granularity' => 'day',
                'label' => 'Este mes',
            ],
            'year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
                'granularity' => 'month',
                'label' => 'Este año',
            ],
            default => [
                'start' => $now->copy()->subDays(29)->startOfDay(),
                'end' => $now->copy()->endOfDay(),
                'granularity' => 'day',
                'label' => 'Últimos 30 días',
            ],
        };
    }

    private function resolvePreviousRange(array $range): array
    {
        /** @var Carbon $start */
        $start = $range['start'];
        /** @var Carbon $end */
        $end = $range['end'];

        // OJO: diffInDays() entre un start-of-day (00:00:00) y un
        // end-of-day (23:59:59.999999) cuenta la duracion exacta, casi un
        // dia entero MAS que la cantidad de dias-calendario que realmente
        // abarca el rango (p. ej. un año completo da 365.999... en vez de
        // 365). Normalizar ambos extremos a medianoche antes de restar
        // evita ese redondeo, que si no corria el periodo de comparacion
        // un dia entero.
        $lengthDays = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        $prevEnd = $start->copy()->subDay()->endOfDay();
        $prevStart = $prevEnd->copy()->startOfDay()->subDays($lengthDays - 1)->startOfDay();

        // Etiqueta concreta (no un generico "Periodo anterior" repetido)
        // para que la leyenda del grafico ("Periodo anterior (X)") tenga
        // sentido: el año pasado, el mes pasado, o el rango de fechas.
        $label = match ($range['granularity']) {
            'month' => $prevStart->format('Y'),
            default => $prevStart->isSameDay($prevEnd)
                ? $prevStart->translatedFormat('d M')
                : $prevStart->translatedFormat('d M').' - '.$prevEnd->translatedFormat('d M'),
        };

        return [
            'start' => $prevStart,
            'end' => $prevEnd,
            'granularity' => $range['granularity'],
            'label' => $label,
        ];
    }

    /**
     * Genera todos los "buckets" (dias o meses) del rango, para que las
     * lineas de tendencia no tengan huecos donde no hubo actividad.
     *
     * @return array<int, array{key:string,label:string}>
     */
    private function buckets(array $range): array
    {
        $buckets = [];
        /** @var Carbon $cursor */
        $cursor = $range['start']->copy();
        $end = $range['end'];

        if ($range['granularity'] === 'month') {
            $cursor = $cursor->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();

            while ($cursor->lte($endMonth)) {
                $buckets[] = ['key' => $cursor->format('Y-m'), 'label' => ucfirst($cursor->translatedFormat('M Y'))];
                $cursor->addMonth();
            }

            return $buckets;
        }

        while ($cursor->lte($end)) {
            $buckets[] = ['key' => $cursor->format('Y-m-d'), 'label' => $cursor->translatedFormat('d M')];
            $cursor->addDay();
        }

        return $buckets;
    }

    // ------------------------------------------------------------------
    // Tendencias (lineas)
    // ------------------------------------------------------------------

    private function buildTrends(int $companyId, array $range): array
    {
        $buckets = $this->buckets($range);
        $format = $range['granularity'] === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $salesByBucket = DB::table('sales')
            ->where('company_id', $companyId)
            ->whereIn('status', self::SALE_STATUSES_COUNTED)
            ->whereDate('sale_date', '>=', $range['start']->toDateString())->whereDate('sale_date', '<=', $range['end']->toDateString())
            ->select(DB::raw("{$this->dateFormatSql('sale_date', $format)} as bucket"), DB::raw('SUM(total) as total'))
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $purchasesByBucket = DB::table('purchases')
            ->where('company_id', $companyId)
            ->whereIn('status', self::PURCHASE_STATUSES_COUNTED)
            ->whereDate('purchase_date', '>=', $range['start']->toDateString())->whereDate('purchase_date', '<=', $range['end']->toDateString())
            ->select(DB::raw("{$this->dateFormatSql('purchase_date', $format)} as bucket"), DB::raw('SUM(total) as total'))
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $profitRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.company_id', $companyId)
            ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
            ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
            ->select(
                DB::raw("{$this->dateFormatSql('sales.sale_date', $format)} as bucket"),
                DB::raw('SUM(sale_items.total) as revenue'),
                DB::raw('SUM(sale_items.quantity * COALESCE(sale_items.unit_cost, products.cost, 0)) as cost'),
            )
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');

        $cashInByBucket = DB::table('sales')
            ->join('payment_methods', 'payment_methods.id', '=', 'sales.payment_method_id')
            ->where('sales.company_id', $companyId)
            ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
            ->where('payment_methods.cash', true)
            ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
            ->select(DB::raw("{$this->dateFormatSql('sales.sale_date', $format)} as bucket"), DB::raw('SUM(sales.total) as total'))
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $cashOutByBucket = DB::table('purchases')
            ->join('payment_methods', 'payment_methods.id', '=', 'purchases.payment_method_id')
            ->where('purchases.company_id', $companyId)
            ->whereIn('purchases.status', self::PURCHASE_STATUSES_COUNTED)
            ->where('payment_methods.cash', true)
            ->whereDate('purchases.purchase_date', '>=', $range['start']->toDateString())->whereDate('purchases.purchase_date', '<=', $range['end']->toDateString())
            ->select(DB::raw("{$this->dateFormatSql('purchases.purchase_date', $format)} as bucket"), DB::raw('SUM(purchases.total) as total'))
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        $labels = [];
        $sales = [];
        $purchases = [];
        $grossProfit = [];
        $cost = [];
        $marginPercent = [];
        $cashFlow = [];

        foreach ($buckets as $bucket) {
            $key = $bucket['key'];
            $labels[] = $bucket['label'];

            $saleTotal = round((float) ($salesByBucket[$key] ?? 0), 2);
            $purchaseTotal = round((float) ($purchasesByBucket[$key] ?? 0), 2);
            $revenue = round((float) ($profitRows[$key]->revenue ?? 0), 2);
            $itemCost = round((float) ($profitRows[$key]->cost ?? 0), 2);
            $profit = round($revenue - $itemCost, 2);
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;
            $cashIn = round((float) ($cashInByBucket[$key] ?? 0), 2);
            $cashOut = round((float) ($cashOutByBucket[$key] ?? 0), 2);

            $sales[] = $saleTotal;
            $purchases[] = $purchaseTotal;
            $grossProfit[] = $profit;
            $cost[] = $itemCost;
            $marginPercent[] = $margin;
            $cashFlow[] = round($cashIn - $cashOut, 2);
        }

        return [
            'labels' => $labels,
            'sales' => $sales,
            'purchases' => $purchases,
            'gross_profit' => $grossProfit,
            'cost' => $cost,
            'margin_percent' => $marginPercent,
            'cash_flow' => $cashFlow,
        ];
    }

    // ------------------------------------------------------------------
    // Distribuciones y rankings (barras / dona)
    // ------------------------------------------------------------------

    private function salesByCategory(int $companyId, array $range): array
    {
        return $this->groupSum(
            DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->where('sales.company_id', $companyId)
                ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
                ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
                ->select(DB::raw("COALESCE(categories.name, 'Sin categoria') as label"), DB::raw('SUM(sale_items.total) as value'))
                ->groupBy('label'),
            10,
        );
    }

    private function salesByBranch(int $companyId, array $range): array
    {
        return $this->groupSum(
            DB::table('sales')
                ->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
                ->where('sales.company_id', $companyId)
                ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
                ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
                ->select(DB::raw("COALESCE(branches.name, 'Sin sucursal') as label"), DB::raw('SUM(sales.total) as value'))
                ->groupBy('label'),
            10,
        );
    }

    private function salesBySeller(int $companyId, array $range): array
    {
        return $this->groupSum(
            DB::table('sales')
                ->leftJoin('users', 'users.id', '=', DB::raw('COALESCE(sales.salesperson_id, sales.created_by)'))
                ->where('sales.company_id', $companyId)
                ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
                ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
                ->select(DB::raw("COALESCE(users.full_name, 'Sin vendedor') as label"), DB::raw('SUM(sales.total) as value'))
                ->groupBy('label'),
            10,
        );
    }

    private function topProducts(int $companyId, array $range): array
    {
        return $this->groupSum(
            DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->where('sales.company_id', $companyId)
                ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
                ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
                ->select(DB::raw("COALESCE(products.short_name, products.long_name, 'Producto') as label"), DB::raw('SUM(sale_items.total) as value'))
                ->groupBy('label'),
            10,
        );
    }

    private function topCustomers(int $companyId, array $range): array
    {
        return $this->groupSum(
            DB::table('sales')
                ->join('customers', 'customers.id', '=', 'sales.customer_id')
                ->where('sales.company_id', $companyId)
                ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
                ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
                ->select('customers.full_name as label', DB::raw('SUM(sales.total) as value'))
                ->groupBy('label'),
            10,
        );
    }

    private function salesByPaymentMethod(int $companyId, array $range): array
    {
        return $this->groupSum(
            DB::table('sales')
                ->leftJoin('payment_methods', 'payment_methods.id', '=', 'sales.payment_method_id')
                ->where('sales.company_id', $companyId)
                ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
                ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
                ->select(DB::raw("COALESCE(payment_methods.name, 'Credito / Sin metodo') as label"), DB::raw('SUM(sales.total) as value'))
                ->groupBy('label'),
            8,
        );
    }

    private function receivablesStatus(int $companyId): array
    {
        $labels = [
            'PENDING' => 'Pendiente',
            'PARTIAL' => 'Parcial',
            'OVERDUE' => 'Vencida',
        ];

        $rows = DB::table('accounts_receivable')
            ->where('company_id', $companyId)
            ->whereIn('status', array_keys($labels))
            ->select('status', DB::raw('SUM(balance) as value'))
            ->groupBy('status')
            ->pluck('value', 'status');

        return collect($labels)
            ->map(fn ($label, $status) => ['label' => $label, 'value' => round((float) ($rows[$status] ?? 0), 2)])
            ->filter(fn ($row) => $row['value'] > 0)
            ->values()
            ->all();
    }

    private function lowStockProducts(int $companyId): array
    {
        $stock = DB::table('inventory_movements')
            ->select('product_id', DB::raw($this->stockSql().' as current_stock'))
            ->where('company_id', $companyId)
            ->groupBy('product_id');

        return DB::table('products')
            ->leftJoinSub($stock, 'stock', fn ($join) => $join->on('stock.product_id', '=', 'products.id'))
            ->where('products.company_id', $companyId)
            ->where('products.status', 1)
            ->where('products.is_inventory', true)
            ->whereRaw('COALESCE(stock.current_stock, 0) <= products.minimum_stock')
            ->orderByRaw('COALESCE(stock.current_stock, 0) ASC')
            ->limit(10)
            ->get([
                DB::raw("COALESCE(products.short_name, products.long_name, 'Producto') as label"),
                DB::raw('COALESCE(stock.current_stock, 0) as value'),
                'products.minimum_stock',
            ])
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'value' => round((float) $row->value, 2),
                'minimum' => round((float) $row->minimum_stock, 2),
            ])
            ->all();
    }

    private function stagnantProducts(int $companyId): array
    {
        $lastSale = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.company_id', $companyId)
            ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
            ->select('sale_items.product_id', DB::raw('MAX(sales.sale_date) as last_sale_date'))
            ->groupBy('sale_items.product_id');

        $cutoff = Carbon::now()->subDays(90)->toDateString();

        return DB::table('products')
            ->leftJoinSub($lastSale, 'last_sale', fn ($join) => $join->on('last_sale.product_id', '=', 'products.id'))
            ->where('products.company_id', $companyId)
            ->where('products.status', 1)
            ->where('products.is_inventory', true)
            ->where('products.allow_sale', true)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_sale.last_sale_date')->orWhere('last_sale.last_sale_date', '<', $cutoff);
            })
            ->orderByRaw('last_sale.last_sale_date IS NULL DESC, last_sale.last_sale_date ASC')
            ->limit(10)
            ->get([
                DB::raw("COALESCE(products.short_name, products.long_name, 'Producto') as label"),
                'last_sale.last_sale_date',
            ])
            ->map(function ($row) {
                $days = $row->last_sale_date
                    ? Carbon::parse($row->last_sale_date)->diffInDays(Carbon::now())
                    : null;

                return [
                    'label' => (string) $row->label,
                    'value' => $days,
                    'never_sold' => $days === null,
                ];
            })
            ->all();
    }

    // ------------------------------------------------------------------
    // Rentabilidad
    // ------------------------------------------------------------------

    private function profitByCategory(int $companyId, array $range): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.company_id', $companyId)
            ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
            ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
            ->select(
                DB::raw("COALESCE(categories.name, 'Sin categoria') as label"),
                DB::raw('SUM(sale_items.total) as revenue'),
                DB::raw('SUM(sale_items.quantity * COALESCE(sale_items.unit_cost, products.cost, 0)) as cost'),
            )
            ->groupBy('label')
            ->get();

        return $rows
            ->map(fn ($row) => ['label' => (string) $row->label, 'value' => round((float) $row->revenue - (float) $row->cost, 2)])
            ->sortByDesc('value')
            ->take(10)
            ->values()
            ->all();
    }

    private function profitByProduct(int $companyId, array $range): array
    {
        $rows = $this->productProfitRows($companyId, $range);

        return $rows->sortByDesc('profit')
            ->take(10)
            ->map(fn ($row) => ['label' => $row['label'], 'value' => round($row['profit'], 2)])
            ->values()
            ->all();
    }

    private function mostProfitableProducts(int $companyId, array $range): array
    {
        $rows = $this->productProfitRows($companyId, $range);

        // "Mas rentables" = mejor margen porcentual (no el que mas gano en
        // cordobas, que es profitByProduct): dos vistas distintas y
        // complementarias, ambas comunes en dashboards de BI.
        return $rows
            ->filter(fn ($row) => $row['revenue'] > 0)
            ->sortByDesc('margin_percent')
            ->take(10)
            ->map(fn ($row) => ['label' => $row['label'], 'value' => round($row['margin_percent'], 2)])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{label:string,revenue:float,cost:float,profit:float,margin_percent:float}>
     */
    private function productProfitRows(int $companyId, array $range)
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.company_id', $companyId)
            ->whereIn('sales.status', self::SALE_STATUSES_COUNTED)
            ->whereDate('sales.sale_date', '>=', $range['start']->toDateString())->whereDate('sales.sale_date', '<=', $range['end']->toDateString())
            ->select(
                'products.id',
                DB::raw("COALESCE(products.short_name, products.long_name, 'Producto') as label"),
                DB::raw('SUM(sale_items.total) as revenue'),
                DB::raw('SUM(sale_items.quantity * COALESCE(sale_items.unit_cost, products.cost, 0)) as cost'),
            )
            ->groupBy('products.id', 'label')
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->revenue;
                $cost = (float) $row->cost;
                $profit = $revenue - $cost;

                return [
                    'label' => (string) $row->label,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => $profit,
                    'margin_percent' => $revenue > 0 ? ($profit / $revenue) * 100 : 0,
                ];
            });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Ejecuta un query ya agrupado por "label" con un SUM "value", ordena
     * de mayor a menor y limita a $limit filas — patron repetido por casi
     * todos los charts de barras/dona de este panel.
     */
    private function groupSum($query, int $limit): array
    {
        return $query
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'value' => round((float) $row->value, 2)])
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    // MySQL (produccion) usa DATE_FORMAT(); SQLite (usado por el suite de
    // tests) no lo tiene y usa strftime() con el mismo formato de tokens
    // %Y-%m-%d, solo que con el string de formato primero. Sin esto, todo
    // el modulo de tendencias truena en tests con "no such function:
    // DATE_FORMAT".
    private function dateFormatSql(string $column, string $format): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('{$format}', {$column})";
        }

        return "DATE_FORMAT({$column}, '{$format}')";
    }

    // Identica a ProductController::stockSql(): se duplica (en vez de
    // extraerla a un trait compartido) para no acoplar este controlador de
    // solo-lectura al de Productos; si algun dia diverge, cada uno debe
    // decidir su propia logica de stock de todas formas.
    private function stockSql(): string
    {
        return "COALESCE(SUM(CASE
            WHEN movement_type IN ('INITIAL_INVENTORY', 'PURCHASE_ENTRY', 'TRANSFER_IN', 'RETURN_IN') THEN COALESCE(quantity, 0)
            WHEN movement_type IN ('SALE_EXIT', 'TRANSFER_OUT', 'RETURN_OUT') THEN -COALESCE(quantity, 0)
            WHEN movement_type = 'ADJUSTMENT' THEN COALESCE(quantity, 0)
            ELSE 0
        END), 0)";
    }

    private function authorizeDashboard(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');
    }
}
