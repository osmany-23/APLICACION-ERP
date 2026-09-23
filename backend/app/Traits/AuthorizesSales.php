<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Helpers de autorizacion compartidos por los controladores del modulo de
 * Facturacion (ventas, notas de credito, notas de debito) — todos son
 * acciones sobre la misma factura, no modulos aparte. Extraido de
 * SaleController para que CreditNoteController/DebitNoteController no
 * dupliquen el mismo par de metodos.
 */
trait AuthorizesSales
{
    protected function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }

    protected function authorizeSales(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para facturacion.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'facturacion')
            ->whereIn('permissions.action_name', ['manage', 'view'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar facturacion.');
    }
}
