<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ErpBaseSeeder extends Seeder
{
    /**
     * Seed the minimum data required to enter the ERP after a fresh migration.
     */
    public function run(): void
    {
        $now = now();

        DB::table('companies')->updateOrInsert(
            ['id' => 1],
            [
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'name' => 'Auto Repuestos Bryan',
                'legal_name' => 'Auto Repuestos Bryan',
                'tax_id' => null,
                'phone' => null,
                'email' => 'admin@erp.local',
                'address' => null,
                'logo' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('branches')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'uuid' => '22222222-2222-2222-2222-222222222222',
                'name' => 'Sucursal Principal',
                'phone' => null,
                'address' => null,
                'status' => 1,
                'created_at' => $now,
            ]
        );

        DB::table('roles')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'name' => 'Administrador',
                'description' => 'Acceso completo al sistema.',
                'created_at' => $now,
            ]
        );

        $permissions = [
            ['id' => 1, 'module_name' => 'dashboard', 'action_name' => 'view'],
            ['id' => 2, 'module_name' => 'companies', 'action_name' => 'manage'],
            ['id' => 3, 'module_name' => 'branches', 'action_name' => 'manage'],
            ['id' => 4, 'module_name' => 'users', 'action_name' => 'manage'],
            ['id' => 5, 'module_name' => 'customers', 'action_name' => 'manage'],
            ['id' => 6, 'module_name' => 'suppliers', 'action_name' => 'manage'],
            ['id' => 7, 'module_name' => 'products', 'action_name' => 'manage'],
            ['id' => 8, 'module_name' => 'inventory', 'action_name' => 'manage'],
            ['id' => 9, 'module_name' => 'sales', 'action_name' => 'manage'],
            ['id' => 10, 'module_name' => 'purchases', 'action_name' => 'manage'],
            ['id' => 11, 'module_name' => 'accounts_receivable', 'action_name' => 'manage'],
            ['id' => 12, 'module_name' => 'accounts_payable', 'action_name' => 'manage'],
            ['id' => 13, 'module_name' => 'cash_registers', 'action_name' => 'manage'],
            ['id' => 14, 'module_name' => 'banks', 'action_name' => 'manage'],
            ['id' => 15, 'module_name' => 'accounting', 'action_name' => 'manage'],
            ['id' => 16, 'module_name' => 'expenses', 'action_name' => 'manage'],
            ['id' => 17, 'module_name' => 'audit_logs', 'action_name' => 'view'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['id' => $permission['id']],
                [
                    'module_name' => $permission['module_name'],
                    'action_name' => $permission['action_name'],
                ]
            );

            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => 1, 'permission_id' => $permission['id']],
                ['role_id' => 1, 'permission_id' => $permission['id']]
            );
        }

        DB::table('users')->updateOrInsert(
            ['username' => 'admin'],
            [
                'company_id' => 1,
                'branch_id' => 1,
                'uuid' => '33333333-3333-3333-3333-333333333333',
                'password_hash' => Hash::make(env('ERP_DEFAULT_ADMIN_PASSWORD', 'Admin12345!')),
                'full_name' => 'Administrador del Sistema',
                'email' => '    ',
                'phone' => null,
                'role_id' => 1,
                'status' => 1,
                'last_login' => null,
                'created_at' => $now,
            ]
        );

        DB::table('customer_types')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'name' => 'Contado',
                'credit_days' => 0,
                'discount_percent' => 0,
            ]
        );

        DB::table('categories')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'parent_id' => null,
                'name' => 'General',
                'margin_percent' => 0,
                'created_at' => $now,
            ]
        );

        DB::table('brands')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'name' => 'Generica',
            ]
        );

        DB::table('units')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'name' => 'Unidad',
                'short_name' => 'UND',
            ]
        );

        DB::table('warehouses')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'branch_id' => 1,
                'name' => 'Bodega Principal',
                'address' => null,
                'manager_name' => null,
                'created_at' => $now,
            ]
        );

        foreach ([
            ['id' => 1, 'name' => 'Efectivo', 'requires_reference' => 0],
            ['id' => 2, 'name' => 'Tarjeta', 'requires_reference' => 1],
            ['id' => 3, 'name' => 'Transferencia', 'requires_reference' => 1],
            ['id' => 4, 'name' => 'Credito', 'requires_reference' => 0],
        ] as $paymentMethod) {
            DB::table('payment_methods')->updateOrInsert(
                ['id' => $paymentMethod['id']],
                [
                    'company_id' => 1,
                    'name' => $paymentMethod['name'],
                    'requires_reference' => $paymentMethod['requires_reference'],
                ]
            );
        }

        DB::table('cash_registers')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'branch_id' => 1,
                'name' => 'Caja Principal',
            ]
        );

        foreach ([
            ['id' => 1, 'name' => 'Activo'],
            ['id' => 2, 'name' => 'Pasivo'],
            ['id' => 3, 'name' => 'Patrimonio'],
            ['id' => 4, 'name' => 'Ingresos'],
            ['id' => 5, 'name' => 'Gastos'],
        ] as $accountType) {
            DB::table('accounting_account_types')->updateOrInsert(
                ['id' => $accountType['id']],
                ['name' => $accountType['name']]
            );
        }

        DB::table('expense_categories')->updateOrInsert(
            ['id' => 1],
            [
                'company_id' => 1,
                'name' => 'General',
            ]
        );
    }
}
