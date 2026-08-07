<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingAccountsSeeder extends Seeder
{
    /**
     * Plan de cuentas jerárquico mínimo, año/periodos fiscales del año en
     * curso, y el tipo de documento para numerar facturas. Todo resuelto
     * por company_id + code, nunca por IDs hardcodeados.
     */
    public function run(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $company = Company::query()->first();

        if (! $company) {
            return;
        }

        $this->seedAccountingAccounts((int) $company->id);
        $this->seedFiscalYearAndPeriods((int) $company->id);
        $this->seedInvoiceDocumentType();
    }

    private function seedAccountingAccounts(int $companyId): void
    {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        $accounts = [
            ['code' => '1000', 'name' => 'ACTIVO', 'type' => 'ASSET', 'parent' => null],
            ['code' => '1100', 'name' => 'Activo Corriente', 'type' => 'ASSET', 'parent' => '1000'],
            ['code' => '1101', 'name' => 'Caja General', 'type' => 'ASSET', 'parent' => '1100'],
            ['code' => '1102', 'name' => 'Bancos', 'type' => 'ASSET', 'parent' => '1100'],
            ['code' => '1103', 'name' => 'Cuentas por Cobrar Clientes', 'type' => 'ASSET', 'parent' => '1100'],
            ['code' => '1104', 'name' => 'Inventario de Mercancías', 'type' => 'ASSET', 'parent' => '1100'],
            ['code' => '1105', 'name' => 'IVA Acreditable', 'type' => 'ASSET', 'parent' => '1100'],
            ['code' => '2000', 'name' => 'PASIVO', 'type' => 'LIABILITY', 'parent' => null],
            ['code' => '2100', 'name' => 'Pasivo Corriente', 'type' => 'LIABILITY', 'parent' => '2000'],
            ['code' => '2101', 'name' => 'Cuentas por Pagar Proveedores', 'type' => 'LIABILITY', 'parent' => '2100'],
            ['code' => '2102', 'name' => 'IVA por Pagar', 'type' => 'LIABILITY', 'parent' => '2100'],
            ['code' => '3000', 'name' => 'CAPITAL', 'type' => 'EQUITY', 'parent' => null],
            ['code' => '3101', 'name' => 'Capital Social', 'type' => 'EQUITY', 'parent' => '3000'],
            ['code' => '4000', 'name' => 'INGRESOS', 'type' => 'REVENUE', 'parent' => null],
            ['code' => '4101', 'name' => 'Ingresos por Ventas', 'type' => 'REVENUE', 'parent' => '4000'],
            ['code' => '5000', 'name' => 'COSTOS Y GASTOS', 'type' => 'EXPENSE', 'parent' => null],
            ['code' => '5101', 'name' => 'Costo de Ventas', 'type' => 'EXPENSE', 'parent' => '5000'],
            ['code' => '5201', 'name' => 'Gastos Operativos', 'type' => 'EXPENSE', 'parent' => '5000'],
        ];

        // Primera pasada: crear/actualizar todas las cuentas sin resolver parent_id todavia.
        foreach ($accounts as $account) {
            DB::table('accounting_accounts')->updateOrInsert(
                ['company_id' => $companyId, 'code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        // Segunda pasada: resolver parent_id por company_id + code.
        foreach ($accounts as $account) {
            if ($account['parent'] === null) {
                continue;
            }

            $parentId = DB::table('accounting_accounts')
                ->where('company_id', $companyId)
                ->where('code', $account['parent'])
                ->value('id');

            DB::table('accounting_accounts')
                ->where('company_id', $companyId)
                ->where('code', $account['code'])
                ->update(['parent_id' => $parentId]);
        }
    }

    private function seedFiscalYearAndPeriods(int $companyId): void
    {
        if (! Schema::hasTable('fiscal_years') || ! Schema::hasTable('fiscal_periods')) {
            return;
        }

        $year = (int) now()->year;
        $yearName = (string) $year;

        DB::table('fiscal_years')->updateOrInsert(
            ['company_id' => $companyId, 'name' => $yearName],
            [
                'start_date' => "{$year}-01-01",
                'end_date' => "{$year}-12-31",
                'status' => 'OPEN',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $fiscalYearId = (int) DB::table('fiscal_years')
            ->where('company_id', $companyId)
            ->where('name', $yearName)
            ->value('id');

        for ($month = 1; $month <= 12; $month++) {
            $start = sprintf('%d-%02d-01', $year, $month);
            $end = date('Y-m-t', strtotime($start));

            DB::table('fiscal_periods')->updateOrInsert(
                ['fiscal_year_id' => $fiscalYearId, 'period_number' => $month],
                [
                    'start_date' => $start,
                    'end_date' => $end,
                    'status' => 'OPEN',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function seedInvoiceDocumentType(): void
    {
        if (! Schema::hasTable('document_types')) {
            return;
        }

        DB::table('document_types')->updateOrInsert(
            ['code' => 'FACT'],
            [
                'name' => 'Factura de Venta',
                'prefix' => 'FACT',
                'next_number' => DB::table('document_types')->where('code', 'FACT')->value('next_number') ?? 1,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
