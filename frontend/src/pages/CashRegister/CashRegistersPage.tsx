import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { FiEdit2, FiPlus, FiSearch, FiTrash2, FiUsers, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';
import ActionsMenu from '../../components/ActionsMenu';
import { CashRegister, CashRegisterListResponse, CashRegisterSaveResponse } from '../../types/cashRegister';
import { ManagedUser, UserListResponse } from '../../types/user';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

type BranchOption = { id: number; name: string };

type FormState = {
  branch_id: string;
  code: string;
  name: string;
  description: string;
  authorization_threshold: string;
  authorized_user_ids: number[];
};

const emptyForm: FormState = { branch_id: '', code: '', name: '', description: '', authorization_threshold: '', authorized_user_ids: [] };

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function Field({ label, children, error }: { label: string; children: React.ReactNode; error?: string }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">{label}</span>
      {children}
      {error && <span className="mt-2 block text-xs font-semibold text-red-500">{error}</span>}
    </label>
  );
}

export default function CashRegistersPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<CashRegister[]>([]);
  const [branches, setBranches] = useState<BranchOption[]>([]);
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<CashRegister | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<CashRegisterListResponse>('/cash-registers', {}, token);
      setItems(response.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [token]);

  const loadOptions = useCallback(async () => {
    if (!token) return;

    try {
      const [branchesResponse, usersResponse] = await Promise.all([
        apiRequest<{ data: BranchOption[] }>('/relations/branches', {}, token),
        apiRequest<UserListResponse>('/users', {}, token).catch(() => ({ data: [] } as UserListResponse)),
      ]);
      setBranches(branchesResponse.data);
      setUsers(usersResponse.data);
    } catch {
      // Los selects simplemente quedan vacios si esto falla.
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadOptions();
  }, [loadItems, loadOptions]);

  const filteredItems = useMemo(() => {
    const normalized = search.trim().toLowerCase();
    if (!normalized) return items;
    return items.filter((item) => item.name.toLowerCase().includes(normalized) || item.code.toLowerCase().includes(normalized));
  }, [items, search]);

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setFormError('');
    setDialogOpen(true);
  }

  function openEdit(item: CashRegister) {
    setEditing(item);
    setForm({
      branch_id: String(item.branch_id),
      code: item.code,
      name: item.name,
      description: item.description ?? '',
      authorization_threshold: item.authorization_threshold !== null ? String(item.authorization_threshold) : '',
      authorized_user_ids: item.authorized_user_ids,
    });
    setFormError('');
    setDialogOpen(true);
  }

  function closeDialog() {
    setDialogOpen(false);
  }

  function toggleUser(userId: number) {
    setForm((current) => ({
      ...current,
      authorized_user_ids: current.authorized_user_ids.includes(userId)
        ? current.authorized_user_ids.filter((id) => id !== userId)
        : [...current.authorized_user_ids, userId],
    }));
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setFormError('');
    setSubmitting(true);

    const payload = {
      branch_id: Number(form.branch_id),
      code: form.code,
      name: form.name,
      description: form.description || undefined,
      authorization_threshold: form.authorization_threshold ? Number(form.authorization_threshold) : null,
      authorized_user_ids: form.authorized_user_ids,
    };

    try {
      const response = editing
        ? await apiRequest<CashRegisterSaveResponse>(`/cash-registers/${editing.id}`, { method: 'PUT', body: JSON.stringify(payload) }, token)
        : await apiRequest<CashRegisterSaveResponse>('/cash-registers', { method: 'POST', body: JSON.stringify(payload) }, token);

      setNotice(response.message ?? 'Caja guardada.');
      setDialogOpen(false);
      await loadItems();
    } catch (err) {
      setFormError(getErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleToggleStatus(item: CashRegister) {
    if (!token) return;

    try {
      await apiRequest(
        `/cash-registers/${item.id}/status`,
        { method: 'PATCH', body: JSON.stringify({ status: item.status === 'ACTIVA' ? 'INACTIVA' : 'ACTIVA' }) },
        token,
      );
      await loadItems();
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  async function handleDelete(item: CashRegister) {
    if (!token) return;
    const confirmed = window.confirm(`¿Eliminar la caja "${item.name}"? Esta accion no se puede deshacer.`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<{ message: string }>(`/cash-registers/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-title-md2 font-black uppercase text-black dark:text-white">Cajas</h1>
          <p className="mt-1 text-sm text-slate-500">Administra las cajas fisicas de cada sucursal y quien puede operarlas.</p>
        </div>
        <button type="button" onClick={openCreate} className="inline-flex h-12 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-bold text-white hover:bg-opacity-90">
          <FiPlus /> Nueva caja
        </button>
      </div>

      {notice && <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

      <label className="flex h-12 items-center gap-2 rounded-lg border border-stroke bg-white px-4 dark:border-strokedark dark:bg-boxdark">
        <FiSearch className="text-slate-400" />
        <input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full bg-transparent text-sm outline-none" placeholder="Buscar por nombre o codigo..." />
      </label>

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-4 py-3">Codigo</th>
                <th className="px-4 py-3">Nombre</th>
                <th className="px-4 py-3">Sucursal</th>
                <th className="px-4 py-3">Usuarios autorizados</th>
                <th className="px-4 py-3">Estado en vivo</th>
                <th className="px-4 py-3">Activa</th>
                <th className="px-4 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-4 py-3 font-semibold text-black dark:text-white">{item.code}</td>
                  <td className="px-4 py-3">{item.name}</td>
                  <td className="px-4 py-3">{item.branch_name}</td>
                  <td className="px-4 py-3">
                    {item.authorized_users.length === 0 ? (
                      <span className="text-xs text-slate-400">Cualquiera con permiso</span>
                    ) : (
                      <span className="inline-flex items-center gap-1 text-xs font-semibold text-primary"><FiUsers /> {item.authorized_users.length} usuario(s)</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {item.is_open ? (
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/40 dark:text-green-300">
                        <span className="h-2 w-2 rounded-full bg-green-500" /> Abierta
                      </span>
                    ) : (
                      <span className="text-xs text-slate-400">Cerrada</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <SwitchField checked={item.status === 'ACTIVA'} onChange={() => void handleToggleStatus(item)} label="Activa" />
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de ${item.name}`}
                        items={[
                          { label: 'Editar', icon: FiEdit2, onClick: () => openEdit(item) },
                          { label: 'Eliminar', icon: FiTrash2, variant: 'danger', onClick: () => void handleDelete(item) },
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-sm text-slate-500">No hay cajas registradas todavia.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleSubmit} className="max-h-full w-full max-w-2xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar caja' : 'Nueva caja'}</p>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {formError && <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">{formError}</div>}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              <Field label="Sucursal">
                <select value={form.branch_id} onChange={(event) => setForm((current) => ({ ...current, branch_id: event.target.value }))} className={inputClass} required>
                  <option value="" disabled hidden>Selecciona una sucursal</option>
                  {branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>{branch.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Codigo">
                <input value={form.code} onChange={(event) => setForm((current) => ({ ...current, code: event.target.value }))} className={inputClass} placeholder="CAJA-02" required />
              </Field>
              <Field label="Nombre">
                <input value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} placeholder="Caja #2" required />
              </Field>
              <Field label="Umbral de autorizacion" error={undefined}>
                <input
                  type="number" min="0" step="0.01"
                  value={form.authorization_threshold}
                  onChange={(event) => setForm((current) => ({ ...current, authorization_threshold: event.target.value }))}
                  className={inputClass}
                  placeholder="Sin limite"
                />
              </Field>
              <div className="sm:col-span-2">
                <Field label="Descripcion">
                  <textarea
                    value={form.description}
                    onChange={(event) => setForm((current) => ({ ...current, description: event.target.value }))}
                    className="min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                  />
                </Field>
              </div>
              <div className="sm:col-span-2">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">
                  Usuarios autorizados <span className="font-normal text-slate-500">(vacio = cualquiera con permiso de caja puede operarla)</span>
                </span>
                <div className="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-stroke p-3 dark:border-strokedark">
                  {users.length === 0 && <p className="text-xs text-slate-400">No se pudo cargar la lista de usuarios.</p>}
                  {users.map((managedUser) => (
                    <label key={managedUser.id} className="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-meta-4">
                      <input
                        type="checkbox"
                        checked={form.authorized_user_ids.includes(managedUser.id)}
                        onChange={() => toggleUser(managedUser.id)}
                        className="h-4 w-4 rounded border-stroke text-primary"
                      />
                      {managedUser.full_name} <span className="text-xs text-slate-400">({managedUser.username})</span>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={closeDialog} className="inline-flex h-11 items-center rounded-lg border border-stroke px-5 text-sm font-bold text-black dark:border-strokedark dark:text-white">
                Cancelar
              </button>
              <button type="submit" disabled={submitting} className="inline-flex h-11 items-center rounded-lg bg-primary px-5 text-sm font-bold text-white disabled:opacity-60">
                {submitting ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
