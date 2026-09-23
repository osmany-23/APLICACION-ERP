<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SalesDebitNote;
use App\Models\SalesReturn;
use Illuminate\Support\Facades\DB;

class JournalPostingService
{
    // Códigos del plan de cuentas sembrado por AccountingAccountsSeeder.
    // Nunca hardcodear IDs: siempre resolver por company_id + code.
    private const ACC_CAJA = '1101';
    private const ACC_BANCOS = '1102';
    private const ACC_CXC = '1103';
    private const ACC_INVENTARIO = '1104';
    private const ACC_IVA_ACREDITABLE = '1105';
    private const ACC_CXP = '2101';
    private const ACC_IVA_POR_PAGAR = '2102';
    private const ACC_INGRESOS_VENTAS = '4101';
    private const ACC_DEVOLUCIONES_VENTAS = '4102';
    private const ACC_RETENCIONES_IR = '1106';
    private const ACC_COSTO_VENTAS = '5101';

    /**
     * Genera y postea el asiento contable de una venta ya confirmada.
     * Debe ejecutarse dentro de la misma transaccion que confirma la venta:
     * si el asiento no cuadra, post() lanza una excepcion y todo se revierte.
     *
     * @param float $totalCost costo total de las lineas (quantity * unit_cost)
     * @param bool $isCredit si la venta genero una cuenta por cobrar (true) o se cobro de inmediato (false)
     */
    public function postSale(Sale $sale, float $totalCost, bool $isCredit, int $userId): JournalEntry
    {
        $companyId = (int) $sale->company_id;

        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'branch_id' => $sale->branch_id,
            'entry_number' => JournalEntry::generateEntryNumber($companyId),
            'entry_date' => $sale->sale_date,
            'description' => 'Venta '.$sale->sale_number,
            'fiscal_period_id' => $this->resolveFiscalPeriod($companyId, (string) $sale->sale_date),
            'reference_table' => 'sales',
            'reference_id' => $sale->id,
            'status' => 'DRAFT',
            'currency_id' => $sale->currency_id,
            'exchange_rate' => $sale->exchange_rate,
            'created_by' => $userId,
        ]);

        $debitAccountCode = $isCredit
            ? self::ACC_CXC
            : $this->resolveCashOrBankAccountCode($sale->payment_method_id);

        $retention = round((float) ($sale->ir_withholding_amount ?? 0), 4);

        if (! $isCredit && $retention > 0) {
            // El cliente retuvo IR: el efectivo/banco que realmente entra
            // es total - retencion; la diferencia queda como un activo
            // (credito a favor de la empresa contra su propio IR anual),
            // no como caja. Misma idea que las lineas de debito multiples
            // de postSaleCollection().
            $entry->addLine(
                AccountingAccount::byCode($companyId, $debitAccountCode)->id,
                round((float) $sale->total - $retention, 4),
                0,
                'Cobro de venta '.$sale->sale_number,
            );

            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_RETENCIONES_IR)->id,
                $retention,
                0,
                'Retencion IR sufrida '.$sale->sale_number,
            );
        } else {
            $entry->addLine(
                AccountingAccount::byCode($companyId, $debitAccountCode)->id,
                round((float) $sale->total, 4),
                0,
                $isCredit ? 'Venta a credito '.$sale->sale_number : 'Cobro de venta '.$sale->sale_number,
            );
        }

        $this->addSaleRevenueAndCogsLines($entry, $sale, $totalCost);

        $entry->post();

        return $entry;
    }

    /**
     * Postea el asiento de una venta "Pendiente de pago" que se termino de
     * cobrar via uno o varios abonos (ver
     * SalesService::registerPendingPayment()). A diferencia de postSale(),
     * el debito puede repartirse entre Caja y Bancos en vez de asumir un
     * unico metodo de pago, porque los abonos pudieron haberse cobrado con
     * metodos distintos a lo largo del tiempo.
     */
    public function postSaleCollection(Sale $sale, float $totalCost, float $cashAmount, float $bankAmount, int $userId): JournalEntry
    {
        $companyId = (int) $sale->company_id;

        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'branch_id' => $sale->branch_id,
            'entry_number' => JournalEntry::generateEntryNumber($companyId),
            'entry_date' => now()->toDateString(),
            'description' => 'Cobro de venta pendiente '.$sale->sale_number,
            'fiscal_period_id' => $this->resolveFiscalPeriod($companyId, now()->toDateString()),
            'reference_table' => 'sales',
            'reference_id' => $sale->id,
            'status' => 'DRAFT',
            'currency_id' => $sale->currency_id,
            'exchange_rate' => $sale->exchange_rate,
            'created_by' => $userId,
        ]);

        $cashAmount = round($cashAmount, 4);
        $bankAmount = round($bankAmount, 4);

        if ($cashAmount > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_CAJA)->id,
                $cashAmount,
                0,
                'Cobro en efectivo de venta '.$sale->sale_number,
            );
        }

        if ($bankAmount > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_BANCOS)->id,
                $bankAmount,
                0,
                'Cobro no efectivo de venta '.$sale->sale_number,
            );
        }

        $this->addSaleRevenueAndCogsLines($entry, $sale, $totalCost);

        $entry->post();

        return $entry;
    }

    /**
     * Asiento inverso de una Nota de Credito (ver CreditNoteService): reduce
     * el ingreso reconocido (via la contra-cuenta "Devoluciones sobre
     * Ventas", no tocando directamente Ingresos por Ventas) y el IVA por
     * pagar, y reduce lo que el cliente debe (CxC) o el efectivo/banco que
     * ya se le devolvio. Si hubo devolucion fisica de producto, revierte
     * ademas el costo de venta.
     */
    public function postCreditNote(SalesReturn $return, Sale $originalSale, float $costTotal, int $userId): JournalEntry
    {
        $companyId = (int) $return->company_id;

        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'branch_id' => $return->branch_id,
            'entry_number' => JournalEntry::generateEntryNumber($companyId),
            'entry_date' => $return->return_date,
            'description' => 'Nota de credito '.$return->return_number.' (factura '.$originalSale->sale_number.')',
            'fiscal_period_id' => $this->resolveFiscalPeriod($companyId, (string) $return->return_date),
            'reference_table' => 'sales_returns',
            'reference_id' => $return->id,
            'status' => 'DRAFT',
            'currency_id' => $originalSale->currency_id,
            'exchange_rate' => $originalSale->exchange_rate,
            'created_by' => $userId,
        ]);

        $subtotalNet = round((float) $return->subtotal, 4);
        $tax = round((float) $return->tax, 4);
        $total = round((float) $return->total, 4);

        if ($subtotalNet > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_DEVOLUCIONES_VENTAS)->id,
                $subtotalNet,
                0,
                'Devolucion sobre venta '.$return->return_number,
            );
        }

        if ($tax > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_IVA_POR_PAGAR)->id,
                $tax,
                0,
                'Reverso de IVA por pagar '.$return->return_number,
            );
        }

        $creditAccountCode = $originalSale->accounts_receivable_id
            ? self::ACC_CXC
            : $this->resolveCashOrBankAccountCode($originalSale->payment_method_id);

        $entry->addLine(
            AccountingAccount::byCode($companyId, $creditAccountCode)->id,
            0,
            $total,
            $originalSale->accounts_receivable_id
                ? 'Reduccion de cuenta por cobrar '.$return->return_number
                : 'Reintegro de venta '.$return->return_number,
        );

        $costTotal = round($costTotal, 4);

        if ($costTotal > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_INVENTARIO)->id,
                $costTotal,
                0,
                'Entrada de inventario por devolucion '.$return->return_number,
            );

            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_COSTO_VENTAS)->id,
                0,
                $costTotal,
                'Reverso de costo de venta '.$return->return_number,
            );
        }

        $entry->post();

        return $entry;
    }

    /**
     * Asiento normal (no inverso) de una Nota de Debito (ver
     * DebitNoteService): aumenta lo que el cliente debe (CxC) e ingresos,
     * sin tocar inventario/costo de venta (no hay movimiento fisico de
     * producto).
     */
    public function postDebitNote(SalesDebitNote $note, Sale $originalSale, int $userId): JournalEntry
    {
        $companyId = (int) $note->company_id;

        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'branch_id' => $note->branch_id,
            'entry_number' => JournalEntry::generateEntryNumber($companyId),
            'entry_date' => $note->debit_date,
            'description' => 'Nota de debito '.$note->debit_number.' (factura '.$originalSale->sale_number.')',
            'fiscal_period_id' => $this->resolveFiscalPeriod($companyId, (string) $note->debit_date),
            'reference_table' => 'sales_debit_notes',
            'reference_id' => $note->id,
            'status' => 'DRAFT',
            'currency_id' => $originalSale->currency_id,
            'exchange_rate' => $originalSale->exchange_rate,
            'created_by' => $userId,
        ]);

        $subtotalNet = round((float) $note->subtotal, 4);
        $tax = round((float) $note->tax, 4);
        $total = round((float) $note->total, 4);

        $entry->addLine(
            AccountingAccount::byCode($companyId, self::ACC_CXC)->id,
            $total,
            0,
            'Cargo adicional a cliente '.$note->debit_number,
        );

        if ($subtotalNet > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_INGRESOS_VENTAS)->id,
                0,
                $subtotalNet,
                'Ingreso por nota de debito '.$note->debit_number,
            );
        }

        if ($tax > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_IVA_POR_PAGAR)->id,
                0,
                $tax,
                'IVA por pagar '.$note->debit_number,
            );
        }

        $entry->post();

        return $entry;
    }

    /**
     * Lineas de ingreso/IVA/costo-de-venta compartidas entre postSale() y
     * postSaleCollection() — lo unico que cambia entre una venta de contado/
     * credito normal y una "pendiente de pago" recien cobrada es la(s)
     * linea(s) de debito (una cuenta unica vs. Caja+Bancos repartido), este
     * bloque de credito es identico en ambos casos.
     */
    private function addSaleRevenueAndCogsLines(JournalEntry $entry, Sale $sale, float $totalCost): void
    {
        $companyId = (int) $sale->company_id;
        $subtotalNet = round((float) $sale->subtotal - (float) $sale->discount, 4);
        $shipping = round((float) $sale->shipping, 4);
        $tax = round((float) $sale->tax, 4);

        // El envio se contabiliza como ingreso de venta (no hay cuenta
        // dedicada en el plan sembrado): el debito ya incluye sale->total
        // completo (subtotal neto + impuesto + envio), asi que el credito
        // de ingresos tiene que sumar el envio o el asiento queda descuadrado.
        $entry->addLine(
            AccountingAccount::byCode($companyId, self::ACC_INGRESOS_VENTAS)->id,
            0,
            $subtotalNet + $shipping,
            'Ingreso por venta '.$sale->sale_number,
        );

        if ($tax > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_IVA_POR_PAGAR)->id,
                0,
                $tax,
                'IVA por pagar '.$sale->sale_number,
            );
        }

        $totalCost = round($totalCost, 4);

        if ($totalCost > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_COSTO_VENTAS)->id,
                $totalCost,
                0,
                'Costo de venta '.$sale->sale_number,
            );

            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_INVENTARIO)->id,
                0,
                $totalCost,
                'Salida de inventario por venta '.$sale->sale_number,
            );
        }
    }

    /**
     * Genera y postea el asiento contable de una compra ya confirmada
     * (mercancia recibida): debito a Inventario (costo neto) e IVA
     * Acreditable (credito fiscal), credito a Cuentas por Pagar o a
     * Caja/Bancos segun la forma de pago.
     */
    public function postPurchase(Purchase $purchase, bool $isCredit, int $userId): JournalEntry
    {
        $companyId = (int) $purchase->company_id;

        $entry = JournalEntry::create([
            'company_id' => $companyId,
            'branch_id' => $purchase->branch_id,
            'entry_number' => JournalEntry::generateEntryNumber($companyId),
            'entry_date' => $purchase->purchase_date,
            'description' => 'Compra '.$purchase->purchase_number,
            'fiscal_period_id' => $this->resolveFiscalPeriod($companyId, (string) $purchase->purchase_date),
            'reference_table' => 'purchases',
            'reference_id' => $purchase->id,
            'status' => 'DRAFT',
            'currency_id' => $purchase->currency_id,
            'exchange_rate' => $purchase->exchange_rate,
            'created_by' => $userId,
        ]);

        $creditAccountCode = $isCredit
            ? self::ACC_CXP
            : $this->resolveCashOrBankAccountCode($purchase->payment_method_id);

        $netCost = round((float) $purchase->subtotal - (float) $purchase->discount, 4);
        $tax = round((float) $purchase->tax, 4);
        $total = round((float) $purchase->total, 4);

        $entry->addLine(
            AccountingAccount::byCode($companyId, self::ACC_INVENTARIO)->id,
            $netCost,
            0,
            'Entrada de inventario por compra '.$purchase->purchase_number,
        );

        if ($tax > 0) {
            $entry->addLine(
                AccountingAccount::byCode($companyId, self::ACC_IVA_ACREDITABLE)->id,
                $tax,
                0,
                'IVA acreditable '.$purchase->purchase_number,
            );
        }

        $entry->addLine(
            AccountingAccount::byCode($companyId, $creditAccountCode)->id,
            0,
            $total,
            $isCredit ? 'Compra a credito '.$purchase->purchase_number : 'Pago de compra '.$purchase->purchase_number,
        );

        $entry->post();

        return $entry;
    }

    private function resolveCashOrBankAccountCode(?int $paymentMethodId): string
    {
        if (! $paymentMethodId) {
            return self::ACC_BANCOS;
        }

        $method = DB::table('payment_methods')->where('id', $paymentMethodId)->first();

        if (! $method) {
            return self::ACC_BANCOS;
        }

        return (bool) $method->cash ? self::ACC_CAJA : self::ACC_BANCOS;
    }

    private function resolveFiscalPeriod(int $companyId, string $saleDate): ?int
    {
        return DB::table('fiscal_periods')
            ->join('fiscal_years', 'fiscal_years.id', '=', 'fiscal_periods.fiscal_year_id')
            ->where('fiscal_years.company_id', $companyId)
            ->where('fiscal_periods.start_date', '<=', $saleDate)
            ->where('fiscal_periods.end_date', '>=', $saleDate)
            ->value('fiscal_periods.id');
    }
}
