<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Helpers de autorizacion compartidos por los controladores del modulo de
 * Caja. El manejo de dinero fisico exige permisos mas finos que el patron
 * generico ver/crear/editar/eliminar del resto del sistema: un Cajero puede
 * tener "abrir"/"agregar_efectivo" sin tener "manage" (administrar cajas) ni
 * "ver_todas" (ver las cajas de otros).
 */
trait AuthorizesCashRegister
{
    protected function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }

    protected function hasCajaPermission(Request $request, array $actions): bool
    {
        $user = $request->user();

        if (! $user || ! $user->role_id) {
            return false;
        }

        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'cajas')
            ->whereIn('permissions.action_name', $actions)
            ->exists();
    }

    /**
     * Puede ver el modulo (listados, resumenes de su propia caja, etc).
     */
    protected function authorizeCajaView(Request $request): void
    {
        abort_unless(
            $this->hasCajaPermission($request, ['ver', 'view', 'index', 'manage', 'administrar', 'ver_todas']),
            403,
            'No tienes permiso para ver el modulo de caja.'
        );
    }

    /**
     * Puede administrar cajas/terminales/motivos (crear, editar, eliminar).
     */
    protected function authorizeCajaManage(Request $request): void
    {
        abort_unless(
            $this->hasCajaPermission($request, ['manage', 'administrar', 'crear', 'editar', 'eliminar']),
            403,
            'No tienes permiso para administrar cajas.'
        );
    }

    /**
     * Chequea una accion puntual (abrir, cerrar, agregar_efectivo,
     * retirar_efectivo, anular_movimiento, autorizar_movimiento,
     * realizar_arqueo). "manage"/"administrar" siempre pasa (el
     * Administrador tiene acceso total sin tener que listar cada accion).
     */
    protected function authorizeCajaAction(Request $request, string $action): void
    {
        abort_unless(
            $this->hasCajaPermission($request, [$action, 'manage', 'administrar']),
            403,
            'No tienes permiso para esta accion de caja.'
        );
    }

    protected function canViewAllCajas(Request $request): bool
    {
        return $this->hasCajaPermission($request, ['ver_todas', 'manage', 'administrar']);
    }
}
