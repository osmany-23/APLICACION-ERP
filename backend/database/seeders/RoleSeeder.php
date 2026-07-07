<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            1 => ['Administrador', 'Acceso completo al sistema.'],
            2 => ['Supervisor', 'Supervision operativa.'],
            3 => ['Vendedor', 'Gestion de ventas y clientes.'],
            4 => ['Bodega', 'Gestion de inventario y almacenes.'],
            5 => ['Contabilidad', 'Gestion contable y financiera.'],
        ];

        foreach ($roles as $id => [$name, $description]) {
            DB::table('roles')->updateOrInsert(
                ['id' => $id],
                [
                    'company_id' => 1,
                    'name' => $name,
                    'description' => $description,
                    'created_at' => $now,
                ]
            );
        }

        $this->syncPermissions();
    }

    private function syncPermissions(): void
    {
        $allPermissionIds = DB::table('permissions')->pluck('id');

        foreach ($allPermissionIds as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => 1, 'permission_id' => $permissionId],
                ['role_id' => 1, 'permission_id' => $permissionId]
            );
        }

        $roleModules = [
            2 => ['dashboard', 'products', 'inventory', 'customers', 'suppliers', 'sales', 'purchases'],
            3 => ['dashboard', 'customers', 'products', 'sales'],
            4 => ['dashboard', 'warehouses', 'inventory', 'products', 'transfers'],
            5 => ['dashboard', 'accounts_receivable', 'accounts_payable', 'bank_accounts', 'accounting', 'expenses'],
        ];

        foreach ($roleModules as $roleId => $modules) {
            $permissionIds = DB::table('permissions')->whereIn('module_name', $modules)->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['role_id' => $roleId, 'permission_id' => $permissionId]
                );
            }
        }
    }
}
