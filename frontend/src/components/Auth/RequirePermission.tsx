import { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import Loader from '../../common/Loader';
import { useAuth } from '../../context/AuthContext';

const VIEW_ACTIONS = ['manage', 'administrar', 'view', 'ver'];

/**
 * Protege una ruta (ademas de esconder el link del menu) exigiendo que el
 * rol del usuario tenga permiso sobre `module`. Complementa a
 * ProtectedRoute.tsx (que solo exige estar autenticado) — usarlo dentro de
 * este, no en su lugar. El backend vuelve a validar todo por su cuenta
 * (authorizeUsers/authorizeRoles/...), esto es solo para no dejar la opcion
 * visible/alcanzable por URL directa a quien no deberia verla.
 */
export function RequirePermission({ module, children }: { module: string; children: ReactNode }) {
  const { user, loading } = useAuth();

  if (loading) {
    return <Loader />;
  }

  const hasAccess = Boolean(
    user?.role?.permissions?.some(
      (permission) => permission.module === module && permission.action && VIEW_ACTIONS.includes(permission.action),
    ),
  );

  if (!hasAccess) {
    return <Navigate to="/" replace />;
  }

  return <>{children}</>;
}

/**
 * Igual que hasModuleAccess de RequirePermission, pero como funcion pura
 * para usar fuera de rutas (ej. para decidir si mostrar un item del menu).
 * Acepta un modulo o una lista (basta con tener acceso a UNO de ellos) —
 * util para secciones del menu que agrupan varios modulos relacionados
 * (ej. "Administración" = usuarios/roles/permisos/empleados).
 */
export function hasModuleAccess(
  user: { role?: { permissions?: { module: string | null; action: string | null }[] } | null } | null,
  module: string | string[],
): boolean {
  const modules = Array.isArray(module) ? module : [module];

  return Boolean(
    user?.role?.permissions?.some(
      (permission) =>
        permission.module
        && modules.includes(permission.module)
        && permission.action
        && VIEW_ACTIONS.includes(permission.action),
    ),
  );
}
