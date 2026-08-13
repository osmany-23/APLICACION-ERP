import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { FiEdit2, FiPlus, FiShield, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import ActionsMenu from '../../components/ActionsMenu';
import { Role, RoleListResponse, RoleSaveResponse } from '../../types/role';
import { PermissionEntry, PermissionListResponse } from '../../types/permission';

type FormState = { name: string; description: string };
type FormErrors = Partial<Record<keyof FormState, string>>;

const emptyForm: FormState = { name: '', description: '' };

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

export default function RolesPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<Role[]>([]);
  const [permissions, setPermissions] = useState<PermissionEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Role | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formErrors, setFormErrors] = useState<FormErrors>({});
  const [submitting, setSubmitting] = useState(false);

  const [permTarget, setPermTarget] = useState<Role | null>(null);
  const [selectedPermIds, setSelectedPermIds] = useState<Set<number>>(new Set());
  const [permBusy, setPermBusy] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<RoleListResponse>('/roles', {}, token);
      setItems(response.data);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token]);

  const loadPermissions = useCallback(async () => {
    if (!token) return;

    try {
      const response = await apiRequest<PermissionListResponse>('/permissions', {}, token);
      setPermissions(response.data);
    } catch {
      setPermissions([]);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadPermissions();
  }, [loadItems, loadPermissions]);

  const modules = useMemo(() => Array.from(new Set(permissions.map((p) => p.module_name))).sort(), [permissions]);
  const actions = useMemo(() => Array.from(new Set(permissions.map((p) => p.action_name))).sort(), [permissions]);
  const permissionByModuleAction = useMemo(() => {
    const map = new Map<string, PermissionEntry>();
    permissions.forEach((p) => map.set(`${p.module_name}::${p.action_name}`, p));
    return map;
  }, [permissions]);

  function updateForm(field: keyof FormState, value: string) {
    setForm((current) => ({ ...current, [field]: value }));
    setFormErrors((current) => ({ ...current, [field]: undefined }));
  }

  function openCreate() {
    setForm(emptyForm);
    setEditing(null);
    setFormErrors({});
    setError('');
    setDialogOpen(true);
  }

  function openEdit(item: Role) {
    setForm({ name: item.name, description: item.description ?? '' });
    setEditing(item);
    setFormErrors({});
    setError('');
    setDialogOpen(true);
  }

  function closeDialog() {
    setDialogOpen(false);
    setEditing(null);
    setForm(emptyForm);
    setFormErrors({});
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!form.name.trim()) {
      setFormErrors({ name: 'Ingresa el nombre del rol.' });
      return;
    }
    if (!token) {
      setError('No autenticado.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<RoleSaveResponse>(
        editing ? `/roles/${editing.id}` : '/roles',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify({ name: form.name.trim(), description: form.description.trim() || null }),
        },
        token,
      );

      setNotice(response.message || 'Rol guardado correctamente.');
      closeDialog();
      await loadItems();
    } catch (saveError) {
      if (saveError instanceof ApiError && saveError.errors) {
        setFormErrors(
          Object.keys(saveError.errors).reduce((acc, field) => {
            const key = field as keyof FormErrors;
            return { ...acc, [key]: saveError.errors?.[field]?.[0] };
          }, {} as FormErrors),
        );
      }
      setError(getErrorMessage(saveError));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(item: Role) {
    if (!token) return;
    const confirmed = window.confirm(`¿Eliminar el rol "${item.name}"?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<{ message: string }>(`/roles/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (deleteError) {
      // 409 si el rol todavia tiene usuarios asignados.
      setError(getErrorMessage(deleteError));
    }
  }

  function openPermissions(item: Role) {
    setPermTarget(item);
    setSelectedPermIds(new Set(item.permission_ids));
    setError('');
  }

  function togglePermission(id: number) {
    setSelectedPermIds((current) => {
      const next = new Set(current);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  function toggleModuleRow(module: string) {
    const idsInRow = actions
      .map((action) => permissionByModuleAction.get(`${module}::${action}`)?.id)
      .filter((id): id is number => typeof id === 'number');
    const allSelected = idsInRow.every((id) => selectedPermIds.has(id));

    setSelectedPermIds((current) => {
      const next = new Set(current);
      idsInRow.forEach((id) => (allSelected ? next.delete(id) : next.add(id)));
      return next;
    });
  }

  async function savePermissions() {
    if (!token || !permTarget) return;

    setPermBusy(true);
    setError('');
    try {
      const response = await apiRequest<RoleSaveResponse>(
        `/roles/${permTarget.id}/permissions`,
        { method: 'PUT', body: JSON.stringify({ permission_ids: Array.from(selectedPermIds) }) },
        token,
      );
      setNotice(response.message || 'Permisos actualizados correctamente.');
      setPermTarget(null);
      await loadItems();
    } catch (saveError) {
      setError(getErrorMessage(saveError));
    } finally {
      setPermBusy(false);
    }
  }

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Roles</h2>
          <p className="mt-1 text-sm text-slate-500">Define roles y los permisos que cada uno tiene en el sistema.</p>
        </div>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
          onClick={openCreate}
        >
          <FiPlus className="h-4 w-4" /> Nuevo rol
        </button>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}
      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Rol</th>
                <th className="px-3 py-3">Descripcion</th>
                <th className="px-3 py-3 text-center">Usuarios</th>
                <th className="px-3 py-3 text-center">Permisos</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3">
                    <span className="inline-flex items-center gap-1.5 font-semibold text-black dark:text-white">
                      <FiShield className="h-4 w-4 text-primary" /> {item.name}
                    </span>
                  </td>
                  <td className="px-3 py-3 text-slate-500">{item.description ?? '-'}</td>
                  <td className="px-3 py-3 text-center">{item.users_count}</td>
                  <td className="px-3 py-3 text-center">{item.permission_ids.length}</td>
                  <td className="px-3 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de ${item.name}`}
                        items={[
                          { label: 'Permisos', icon: FiShield, onClick: () => openPermissions(item) },
                          { label: 'Editar', icon: FiEdit2, onClick: () => openEdit(item) },
                          { label: 'Eliminar', icon: FiTrash2, variant: 'danger', onClick: () => void handleDelete(item) },
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {items.length === 0 && (
                <tr>
                  <td colSpan={5} className="px-3 py-8 text-center text-sm text-slate-500">
                    No hay roles registrados todavia.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form
            onSubmit={handleSubmit}
            className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar rol' : 'Nuevo rol'}</p>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Nombre</span>
                <input value={form.name} onChange={(event) => updateForm('name', event.target.value)} className={inputClass} placeholder="Ej. Cajero turno noche" />
                {formErrors.name && <span className="mt-2 block text-xs font-semibold text-red-500">{formErrors.name}</span>}
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Descripcion</span>
                <textarea value={form.description} onChange={(event) => updateForm('description', event.target.value)} className="min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white" />
              </label>
            </div>

            {!editing && (
              <p className="mt-4 text-xs text-slate-500">
                Los permisos se asignan despues de crear el rol, desde el boton "Permisos" en la lista.
              </p>
            )}

            <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button type="button" onClick={closeDialog} className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black transition hover:border-primary hover:text-primary">
                Cancelar
              </button>
              <button type="submit" disabled={submitting} className="inline-flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white disabled:opacity-60">
                {submitting ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}

      {permTarget && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <div className="flex max-h-full w-full max-w-4xl flex-col overflow-hidden rounded-lg border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="flex items-center justify-between gap-4 border-b border-stroke p-6 dark:border-strokedark">
              <div>
                <p className="text-xl font-black text-black dark:text-white">Permisos de "{permTarget.name}"</p>
                <p className="mt-1 text-sm text-slate-500">Marca las acciones que este rol puede realizar en cada modulo.</p>
              </div>
              <button type="button" onClick={() => setPermTarget(null)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="overflow-auto p-6">
              <table className="min-w-full border-collapse text-left text-xs">
                <thead>
                  <tr>
                    <th className="sticky left-0 z-10 bg-white px-3 py-2 font-semibold text-black dark:bg-boxdark dark:text-white">Modulo</th>
                    {actions.map((action) => (
                      <th key={action} className="whitespace-nowrap px-3 py-2 text-center font-semibold capitalize text-black dark:text-white">
                        {action}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {modules.map((module) => (
                    <tr key={module} className="border-t border-stroke dark:border-strokedark">
                      <td className="sticky left-0 z-10 bg-white px-3 py-2 font-semibold capitalize text-black dark:bg-boxdark dark:text-white">
                        <button type="button" onClick={() => toggleModuleRow(module)} className="hover:text-primary" title="Marcar/desmarcar toda la fila">
                          {module.replace(/_/g, ' ')}
                        </button>
                      </td>
                      {actions.map((action) => {
                        const permission = permissionByModuleAction.get(`${module}::${action}`);
                        if (!permission) {
                          return <td key={action} className="px-3 py-2 text-center text-slate-300">—</td>;
                        }
                        return (
                          <td key={action} className="px-3 py-2 text-center">
                            <input
                              type="checkbox"
                              checked={selectedPermIds.has(permission.id)}
                              onChange={() => togglePermission(permission.id)}
                              className="h-4 w-4 rounded border-stroke text-primary focus:ring-primary"
                            />
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex items-center justify-between gap-4 border-t border-stroke p-6 dark:border-strokedark">
              <span className="text-xs text-slate-500">{selectedPermIds.size} permisos seleccionados</span>
              <div className="flex gap-3">
                <button type="button" onClick={() => setPermTarget(null)} className="inline-flex h-11 items-center justify-center rounded-lg border border-stroke px-5 text-sm font-bold text-black hover:border-primary hover:text-primary">
                  Cancelar
                </button>
                <button type="button" disabled={permBusy} onClick={() => void savePermissions()} className="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white disabled:opacity-60">
                  {permBusy ? 'Guardando...' : 'Guardar permisos'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
