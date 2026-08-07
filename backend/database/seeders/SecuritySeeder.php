<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SecuritySeeder extends Seeder
{
    public function run(): void
    {
        // Obtener la empresa y sucursal creadas en el seeder anterior
        $company = DB::table('companies')->first();
        $branch = DB::table('branches')->first();

        if (!$company) {
            return;
        }

        // 1. Crear o buscar el Rol de Administrador
        // Usamos updateOrInsert para evitar duplicados si ya existe el rol
        DB::table('roles')->updateOrInsert(
            ['company_id' => $company->id, 'name' => 'Administrador'],
            ['description' => 'Acceso total a todos los módulos del sistema ERP.', 'updated_at' => now()]
        );
        $adminRoleId = DB::table('roles')->where('company_id', $company->id)->where('name', 'Administrador')->value('id');

        DB::table('roles')->updateOrInsert(
            ['company_id' => $company->id, 'name' => 'Vendedor'],
            ['description' => 'Acceso limitado a facturación, clientes y cotizaciones.', 'updated_at' => now()]
        );

        // 2. Permisos Expandidos por Módulos
        $modules = [
            'inventario',
            'facturacion',
            'clientes',
            'compras',
            'contabilidad',
            'usuarios',
            'productos',
            'products',
            'settings',
            'general_settings',
            'global_catalogs',
            'payment_methods',
            'payment_terms',
        ];
        $actions = ['ver', 'crear', 'editar', 'eliminar', 'view', 'index', 'store', 'update', 'destroy', 'status', 'manage', 'administrar'];

        $permissionIds = [];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                // updateOrInsert asegura que si ya existe la combinación 'module_name'-'action_name', no falle
                DB::table('permissions')->updateOrInsert(
                    ['module_name' => $module, 'action_name' => $action],
                    ['updated_at' => now()]
                );

                // Recuperamos el ID del permiso (ya sea nuevo o viejo)
                $permissionIds[] = DB::table('permissions')
                    ->where('module_name', $module)
                    ->where('action_name', $action)
                    ->value('id');
            }
        }

        // 3. Asignar de forma segura los permisos al Administrador
        // Limpiamos los permisos viejos del admin antes para no duplicar relaciones
        DB::table('role_permissions')->where('role_id', $adminRoleId)->delete();

        foreach (array_unique($permissionIds) as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3b. Asignar permisos limitados al rol Vendedor: puede operar
        // facturación, clientes y productos, pero no eliminar ni administrar.
        $vendedorRoleId = DB::table('roles')->where('company_id', $company->id)->where('name', 'Vendedor')->value('id');
        $vendedorModules = ['facturacion', 'clientes', 'productos', 'products', 'inventario'];
        $vendedorActions = ['ver', 'crear', 'editar', 'view', 'index', 'store', 'update'];

        DB::table('role_permissions')->where('role_id', $vendedorRoleId)->delete();

        $vendedorPermissionIds = DB::table('permissions')
            ->whereIn('module_name', $vendedorModules)
            ->whereIn('action_name', $vendedorActions)
            ->pluck('id');

        foreach ($vendedorPermissionIds->unique() as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => $vendedorRoleId,
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Crear o Actualizar el Usuario Administrador Principal
        DB::table('users')->updateOrInsert(
            ['username' => 'admin'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id ?? null,
                'uuid' => Str::uuid(),
                'password_hash' => Hash::make('admin123'),
                'full_name' => 'Administrador del Sistema',
                'email' => 'admin@sigmaenterprise.com',
                'role_id' => $adminRoleId,
                'status' => 1,
                'updated_at' => now(),
            ]
        );
    }
}
