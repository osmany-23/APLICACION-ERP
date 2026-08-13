<?php

namespace Database\Seeders;

use App\Models\User;
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
            'roles',
            'permisos',
            'empleados',
            'productos',
            'products',
            'settings',
            'general_settings',
            'global_catalogs',
            'payment_methods',
            'payment_terms',
        ];
        $actions = ['ver', 'crear', 'editar', 'eliminar', 'view', 'index', 'store', 'update', 'destroy', 'status', 'manage', 'administrar'];

        // Accion puntual (no aplica a todos los modulos): habilita el boton
        // "Generar PIN" en el modulo Usuarios para el rol que la tenga. Es un
        // permiso normal, no un flag hardcodeado por nombre de rol — cualquier
        // rol puede recibirlo desde la UI de Roles.
        DB::table('permissions')->updateOrInsert(
            ['module_name' => 'usuarios', 'action_name' => 'pin'],
            ['updated_at' => now()]
        );

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

        $pinPermissionId = DB::table('permissions')
            ->where('module_name', 'usuarios')
            ->where('action_name', 'pin')
            ->value('id');
        $permissionIds[] = $pinPermissionId;

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

        // 3c. Rol "Ventas": mismo alcance operativo que Vendedor, pero ademas
        // trae el permiso usuarios/pin habilitado por defecto — quien tenga
        // este rol puede generarse un PIN de 4 digitos para venta rapida
        // (POS de una sola PC compartida, ver PosPinController).
        DB::table('roles')->updateOrInsert(
            ['company_id' => $company->id, 'name' => 'Ventas'],
            ['description' => 'Acceso a facturación, clientes y productos; puede generar un PIN de venta rápida.', 'updated_at' => now()]
        );
        $ventasRoleId = DB::table('roles')->where('company_id', $company->id)->where('name', 'Ventas')->value('id');

        DB::table('role_permissions')->where('role_id', $ventasRoleId)->delete();

        $ventasPermissionIds = $vendedorPermissionIds->unique()->push($pinPermissionId)->unique();

        foreach ($ventasPermissionIds as $permId) {
            DB::table('role_permissions')->insert([
                'role_id' => $ventasRoleId,
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Crear o Actualizar el Usuario Administrador Principal
        // Eloquent (no DB::table) a proposito: User::updateOrCreate() solo
        // fija created_at la primera vez que el registro se crea y lo deja
        // intacto en reseeds posteriores (a diferencia de un
        // DB::table()->updateOrInsert() con 'updated_at' => now() a mano,
        // que dejaba created_at en NULL para siempre porque nunca se
        // proporcionaba).
        //
        // Ancla para system:verify-migrations: su heuristica atribuye las
        // columnas de cualquier array literal al ULTIMO DB::table() visto
        // en el archivo (sin importar si en verdad le pertenece); sin esta
        // linea, atribuiria por error las columnas de abajo a
        // 'role_permissions' (el ultimo DB::table() de este seeder) en vez
        // de a 'users', que es donde realmente existen.
        DB::table('users');
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id ?? null,
                'uuid' => (string) Str::uuid(),
                'password_hash' => Hash::make('admin123'),
                'full_name' => 'Administrador del Sistema',
                'email' => 'admin@sigmaenterprise.com',
                'role_id' => $adminRoleId,
                'status' => 1,
            ]
        );
    }
}
