import { AuthUser } from '../types/auth';

/**
 * El modulo de Caja usa permisos puntuales (abrir/cerrar/agregar_efectivo/
 * retirar_efectivo/ver_todas/anular_movimiento/autorizar_movimiento/
 * realizar_arqueo) ademas de los genericos ver/crear/editar/eliminar del
 * resto del sistema. "manage"/"administrar" siempre habilita todo, igual
 * que en el backend (AuthorizesCashRegister::authorizeCajaAction).
 */
export function hasCajaAction(user: AuthUser | null, action: string): boolean {
  return Boolean(
    user?.role?.permissions?.some(
      (permission) =>
        permission.module === 'cajas'
        && (permission.action === action || permission.action === 'manage' || permission.action === 'administrar'),
    ),
  );
}

export function canViewAllCajas(user: AuthUser | null): boolean {
  return hasCajaAction(user, 'ver_todas');
}

export function canManageCajas(user: AuthUser | null): boolean {
  return Boolean(
    user?.role?.permissions?.some(
      (permission) =>
        permission.module === 'cajas'
        && (permission.action === 'manage' || permission.action === 'administrar' || permission.action === 'crear' || permission.action === 'editar'),
    ),
  );
}
