<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Catalogo de solo lectura: todos los permisos del sistema (modulo +
     * accion) agrupados por modulo, con la lista de roles de la empresa que
     * lo tienen asignado. La asignacion real se hace desde Roles
     * (RoleController::syncPermissions) — aqui solo se consulta.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizePermissions($request);
        $companyId = (int) $request->user()->company_id;

        $roleNamesByPermission = DB::table('role_permissions')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->where('roles.company_id', $companyId)
            ->select('role_permissions.permission_id', 'roles.name')
            ->get()
            ->groupBy('permission_id')
            ->map(fn ($rows) => $rows->pluck('name')->values());

        $permissions = Permission::query()
            ->orderBy('module_name')
            ->orderBy('action_name')
            ->get()
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'module_name' => $permission->module_name,
                'action_name' => $permission->action_name,
                'roles' => $roleNamesByPermission->get($permission->id, collect())->values(),
            ]);

        $grouped = $permissions->groupBy('module_name')->map(fn ($items, $module) => [
            'module_name' => $module,
            'permissions' => $items->values(),
        ])->values();

        return response()->json([
            'data' => $permissions,
            'grouped' => $grouped,
        ]);
    }

    private function authorizePermissions(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para permisos.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->whereIn('permissions.module_name', ['permisos', 'roles', 'usuarios'])
            ->whereIn('permissions.action_name', ['manage', 'administrar', 'view', 'ver'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para consultar permisos.');
    }
}
