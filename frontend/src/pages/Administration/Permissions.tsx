import { useCallback, useEffect, useState } from 'react';
import { FiShield } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { PermissionGroup, PermissionListResponse } from '../../types/permission';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    return error.message;
  }

  return 'La solicitud no pudo completarse.';
}

export default function PermissionsPage() {
  const { token } = useAuth();
  const [groups, setGroups] = useState<PermissionGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadPermissions = useCallback(async () => {
    if (!token) {
      setGroups([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<PermissionListResponse>('/permissions', {}, token);
      setGroups(response.grouped);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setGroups([]);
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadPermissions();
  }, [loadPermissions]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6">
        <h2 className="text-xl font-black text-black dark:text-white">Permisos</h2>
        <p className="mt-1 text-sm text-slate-500">
          Catalogo de solo lectura de todos los permisos del sistema, agrupados por modulo. Para asignarlos a un rol,
          ve a <span className="font-semibold">Roles</span> y usa el boton "Permisos".
        </p>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="space-y-6">
          {groups.map((group) => (
            <div key={group.module_name} className="rounded-lg border border-stroke dark:border-strokedark">
              <div className="flex items-center gap-2 border-b border-stroke bg-gray-2 px-4 py-3 dark:border-strokedark dark:bg-meta-4">
                <FiShield className="h-4 w-4 text-primary" />
                <span className="text-sm font-black capitalize text-black dark:text-white">
                  {group.module_name.replace(/_/g, ' ')}
                </span>
                <span className="ml-auto text-xs text-slate-500">{group.permissions.length} acciones</span>
              </div>
              <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead>
                    <tr className="text-xs font-semibold uppercase text-slate-500">
                      <th className="px-4 py-2">Accion</th>
                      <th className="px-4 py-2">Roles que lo usan</th>
                    </tr>
                  </thead>
                  <tbody>
                    {group.permissions.map((permission) => (
                      <tr key={permission.id} className="border-t border-stroke dark:border-strokedark">
                        <td className="px-4 py-2 font-medium capitalize text-black dark:text-white">{permission.action_name}</td>
                        <td className="px-4 py-2">
                          {permission.roles.length > 0 ? (
                            <div className="flex flex-wrap gap-1.5">
                              {permission.roles.map((roleName) => (
                                <span key={roleName} className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                                  {roleName}
                                </span>
                              ))}
                            </div>
                          ) : (
                            <span className="text-xs text-slate-400">Sin roles asignados</span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          ))}
          {groups.length === 0 && (
            <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">
              No hay permisos registrados.
            </div>
          )}
        </div>
      )}
    </div>
  );
}
