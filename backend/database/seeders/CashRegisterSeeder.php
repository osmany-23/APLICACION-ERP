<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashRegisterSeeder extends Seeder
{
    /**
     * Catalogo global de motivos de movimiento (company_id null: visible
     * para todas las empresas, ver CashMovementReason::scopeForCompany) mas
     * una caja y una terminal de demostracion en la sucursal principal, para
     * que el modulo se pueda probar de inmediato tras un fresh-seed.
     */
    public function run(): void
    {
        if (! Schema::hasTable('cash_movement_reasons')) {
            return;
        }

        $ingresoReasons = [
            ['name' => 'Fondo adicional', 'sort_order' => 10],
            ['name' => 'Cambio de efectivo', 'sort_order' => 20],
            ['name' => 'Correccion', 'sort_order' => 30],
            ['name' => 'Devolucion de prestamo', 'sort_order' => 40],
            ['name' => 'Otro', 'sort_order' => 990, 'requires_note' => true],
        ];

        $egresoReasons = [
            ['name' => 'Pago a proveedor', 'sort_order' => 10],
            ['name' => 'Compra urgente', 'sort_order' => 20],
            ['name' => 'Gastos operativos', 'sort_order' => 30],
            ['name' => 'Retiro administrativo', 'sort_order' => 40],
            ['name' => 'Transporte', 'sort_order' => 50],
            ['name' => 'Cambio de efectivo', 'sort_order' => 60],
            ['name' => 'Correccion', 'sort_order' => 70],
            ['name' => 'Otro', 'sort_order' => 990, 'requires_note' => true],
        ];

        foreach (['INGRESO' => $ingresoReasons, 'EGRESO' => $egresoReasons] as $type => $reasons) {
            foreach ($reasons as $reason) {
                DB::table('cash_movement_reasons')->updateOrInsert(
                    ['company_id' => null, 'type' => $type, 'name' => $reason['name']],
                    [
                        'requires_note' => $reason['requires_note'] ?? false,
                        'is_system' => true,
                        'is_active' => true,
                        'sort_order' => $reason['sort_order'],
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        if (! Schema::hasTable('companies') || ! Schema::hasTable('branches') || ! Schema::hasTable('cash_registers')) {
            return;
        }

        $company = DB::table('companies')->first();
        $branch = DB::table('branches')->where('company_id', $company->id ?? 0)->first();
        $admin = DB::table('users')->where('username', 'admin')->first();

        if (! $company || ! $branch) {
            return;
        }

        DB::table('cash_registers')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'CAJA-01'],
            [
                'branch_id' => $branch->id,
                'name' => 'Caja #1',
                'description' => 'Caja principal de mostrador.',
                'default_currency_id' => $company->currency_id ?? 1,
                'status' => 'ACTIVA',
                'created_by' => $admin->id ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $cashRegisterId = DB::table('cash_registers')
            ->where('company_id', $company->id)
            ->where('code', 'CAJA-01')
            ->value('id');

        if ($admin && $cashRegisterId) {
            $alreadyAuthorized = DB::table('cash_register_users')
                ->where('cash_register_id', $cashRegisterId)
                ->where('user_id', $admin->id)
                ->exists();

            if (! $alreadyAuthorized) {
                DB::table('cash_register_users')->insert([
                    'cash_register_id' => $cashRegisterId,
                    'user_id' => $admin->id,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
