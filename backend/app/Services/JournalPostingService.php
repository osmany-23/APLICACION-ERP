<?php

namespace App\Services;

use App\Models\AccountingAccount;
use App\Models\JournalEntry;
use App\Models\Purchase;
use App\Models\Sale;
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

        $subtotalNet = round((float) $sale->subtotal - (float) $sale->discount, 4);
        $tax = round((float) $sale->tax, 4);
        $total = round((float) $sale->total, 4);

        $entry->addLine(
            AccountingAccount::byCode($companyId, $debitAccountCode)->id,
            $total,
            0,
            $isCredit ? 'Venta a credito '.$sale->sale_number : 'Cobro de venta '.$sale->sale_number,
        );

        $entry->addLine(
            AccountingAccount::byCode($companyId, self::ACC_INGRESOS_VENTAS)->id,
            0,
            $subtotalNet,
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

        $entry->post();

        return $entry;
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
