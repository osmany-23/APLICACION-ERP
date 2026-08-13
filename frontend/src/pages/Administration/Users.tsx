import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { FiCopy, FiEdit2, FiKey, FiLock, FiPlus, FiSearch, FiShield, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';
import ActionsMenu from '../../components/ActionsMenu';
import { GeneratePinResponse, ManagedUser, UserListResponse, UserSaveResponse } from '../../types/user';

type RoleOption = { id: number; name: string };
type BranchOption = { id: number; name: string };

type FormState = {
  username: string;
  full_name: string;
  email: string;
  phone: string;
  role_id: string;
  branch_id: string;
  password: string;
  status: boolean;
};

type FormErrors = Partial<Record<keyof FormState, string>>;

const emptyForm: FormState = {
  username: '',
  full_name: '',
  email: '',
  phone: '',
  role_id: '',
  branch_id: '',
  password: '',
  status: true,
};

function normalizeText(value: string) {
  return value.trim().toLowerCase();
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function Field({ label, children, error }: { label: string; children: React.ReactNode; error?: string }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">{label}</span>
      {children}
      {error && <span className="mt-2 block text-xs font-semibold text-red-500">{error}</span>}
    </label>
  );
}

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

export default function UsersPage() {
  const { token, user: currentUser } = useAuth();
  const [items, setItems] = useState<ManagedUser[]>([]);
  const [roles, setRoles] = useState<RoleOption[]>([]);
  const [branches, setBranches] = useState<BranchOption[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<ManagedUser | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formErrors, setFormErrors] = useState<FormErrors>({});
  const [submitting, setSubmitting] = useState(false);

  const [pinTarget, setPinTarget] = useState<ManagedUser | null>(null);
  const [pinResult, setPinResult] = useState<string | null>(null);
  const [pinBusy, setPinBusy] = useState(false);

  const [passwordTarget, setPasswordTarget] = useState<ManagedUser | null>(null);
  const [newPassword, setNewPassword] = useState('');
  const [passwordBusy, setPasswordBusy] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<UserListResponse>('/users', {}, token);
      setItems(response.data);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token]);

  const loadCatalogs = useCallback(async () => {
    if (!token) return;

    try {
      const [rolesRes, branchesRes] = await Promise.all([
        apiRequest<{ data: RoleOption[] }>('/roles', {}, token),
        apiRequest<{ data: BranchOption[] }>('/relations/branches', {}, token),
      ]);
      setRoles(rolesRes.data);
      setBranches(branchesRes.data);
    } catch {
      setRoles([]);
      setBranches([]);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadCatalogs();
  }, [loadItems, loadCatalogs]);

  function updateForm(field: keyof FormState, value: string | boolean) {
    setForm((current) => ({ ...current, [field]: value }));
    setFormErrors((current) => ({ ...current, [field]: undefined }));
  }

  function validateForm(state: FormState): FormErrors {
    const validation: FormErrors = {};

    if (!state.username.trim()) {
      validation.username = 'El usuario es obligatorio.';
    }
    if (!state.full_name.trim()) {
      validation.full_name = 'El nombre completo es obligatorio.';
    }
    if (!editing && state.password.trim().length < 8) {
      validation.password = 'La contrasena debe tener al menos 8 caracteres.';
    }
    if (state.email.trim() && !/^\S+@\S+\.\S+$/.test(state.email.trim())) {
      validation.email = 'Ingresa un correo valido.';
    }

    return validation;
  }

  function openCreate() {
    setForm(emptyForm);
    setEditing(null);
    setFormErrors({});
    setError('');
    setNotice('');
    setDialogOpen(true);
  }

  function openEdit(item: ManagedUser) {
    setForm({
      username: item.username,
      full_name: item.full_name,
      email: item.email ?? '',
      phone: item.phone ?? '',
      role_id: item.role_id ? String(item.role_id) : '',
      branch_id: item.branch_id ? String(item.branch_id) : '',
      password: '',
      status: item.status === 1,
    });
    setEditing(item);
    setFormErrors({});
    setError('');
    setNotice('');
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

    const errors = validateForm(form);
    setFormErrors(errors);
    if (Object.keys(errors).length > 0) return;
    if (!token) {
      setError('No autenticado.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const body: Record<string, unknown> = {
        username: form.username.trim(),
        full_name: form.full_name.trim(),
        email: form.email.trim() || null,
        phone: form.phone.trim() || null,
        role_id: form.role_id ? Number(form.role_id) : null,
        branch_id: form.branch_id ? Number(form.branch_id) : null,
      };

      if (!editing) {
        body.password = form.password;
        body.status = form.status;
      }

      const response = await apiRequest<UserSaveResponse>(
        editing ? `/users/${editing.id}` : '/users',
        { method: editing ? 'PUT' : 'POST', body: JSON.stringify(body) },
        token,
      );

      setNotice(response.message || 'Usuario guardado correctamente.');
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

  async function handleToggleStatus(item: ManagedUser) {
    if (!token) return;

    try {
      await apiRequest(
        `/users/${item.id}/status`,
        { method: 'PATCH', body: JSON.stringify({ status: item.status !== 1 }) },
        token,
      );
      await loadItems();
    } catch (toggleError) {
      // El backend responde 422 si intentas desactivarte a ti mismo o si
      // dejarias la empresa sin ningun administrador activo.
      setError(getErrorMessage(toggleError));
    }
  }

  function openGeneratePin(item: ManagedUser) {
    setPinTarget(item);
    setPinResult(null);
    setError('');
  }

  async function confirmGeneratePin() {
    if (!token || !pinTarget) return;

    setPinBusy(true);
    try {
      const response = await apiRequest<GeneratePinResponse>(`/users/${pinTarget.id}/pin`, { method: 'POST' }, token);
      setPinResult(response.pin);
      await loadItems();
    } catch (pinError) {
      setError(getErrorMessage(pinError));
      setPinTarget(null);
    } finally {
      setPinBusy(false);
    }
  }

  async function handleRevokePin(item: ManagedUser) {
    if (!token) return;
    const confirmed = window.confirm(`¿Revocar el PIN de ${item.full_name}? Ya no podra usarlo para ventas rapidas.`);
    if (!confirmed) return;

    try {
      await apiRequest(`/users/${item.id}/pin`, { method: 'DELETE' }, token);
      setNotice('PIN revocado correctamente.');
      await loadItems();
    } catch (revokeError) {
      setError(getErrorMessage(revokeError));
    }
  }

  function openResetPassword(item: ManagedUser) {
    setPasswordTarget(item);
    setNewPassword('');
    setError('');
  }

  async function confirmResetPassword(event: FormEvent) {
    event.preventDefault();
    if (!token || !passwordTarget) return;

    if (newPassword.trim().length < 8) {
      setError('La nueva contrasena debe tener al menos 8 caracteres.');
      return;
    }

    setPasswordBusy(true);
    try {
      await apiRequest(
        `/users/${passwordTarget.id}/reset-password`,
        { method: 'POST', body: JSON.stringify({ password: newPassword }) },
        token,
      );
      setNotice(`Contrasena de ${passwordTarget.full_name} restablecida correctamente.`);
      setPasswordTarget(null);
    } catch (resetError) {
      setError(getErrorMessage(resetError));
    } finally {
      setPasswordBusy(false);
    }
  }

  const filteredItems = useMemo(() => {
    const normalized = normalizeText(search);
    return items.filter((item) =>
      normalizeText(`${item.username} ${item.full_name} ${item.email ?? ''}`).includes(normalized),
    );
  }, [items, search]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Usuarios</h2>
          <p className="mt-1 text-sm text-slate-500">Administra las cuentas de acceso al sistema y su rol.</p>
        </div>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
          onClick={openCreate}
        >
          <FiPlus className="h-4 w-4" /> Nuevo usuario
        </button>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}
      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

      <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <label className="flex items-center gap-2 rounded-lg border border-stroke bg-white px-3 py-2 dark:border-strokedark dark:bg-boxdark">
          <FiSearch className="text-slate-400" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="w-full bg-transparent text-sm outline-none"
            placeholder="Buscar por usuario, nombre o correo..."
          />
        </label>
        <div className="text-sm text-slate-500">{filteredItems.length} usuarios</div>
      </div>

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Usuario</th>
                <th className="px-3 py-3">Nombre</th>
                <th className="px-3 py-3">Rol</th>
                <th className="px-3 py-3">Sucursal</th>
                <th className="px-3 py-3">PIN</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.username}</td>
                  <td className="px-3 py-3">
                    <div className="font-medium text-black dark:text-white">{item.full_name}</div>
                    <div className="text-xs text-slate-500">{item.email ?? ''}</div>
                    {item.employee && (
                      <div className="text-xs font-semibold text-primary">
                        Empleado: {item.employee.code} — {item.employee.full_name}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-3">
                    {item.role ? (
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                        <FiShield className="h-3 w-3" /> {item.role.name}
                      </span>
                    ) : (
                      <span className="text-xs text-slate-400">Sin rol</span>
                    )}
                  </td>
                  <td className="px-3 py-3">{branches.find((b) => b.id === item.branch_id)?.name ?? '-'}</td>
                  <td className="px-3 py-3">
                    {item.can_generate_pin ? (
                      item.has_pin ? (
                        <div className="flex items-center gap-2">
                          <span className="text-xs font-semibold text-green-600">Activo</span>
                          <button
                            type="button"
                            onClick={() => void handleRevokePin(item)}
                            className="text-xs font-semibold text-red-500 hover:underline"
                          >
                            Revocar
                          </button>
                          <button
                            type="button"
                            onClick={() => openGeneratePin(item)}
                            className="text-xs font-semibold text-primary hover:underline"
                          >
                            Regenerar
                          </button>
                        </div>
                      ) : (
                        <button
                          type="button"
                          onClick={() => openGeneratePin(item)}
                          className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                        >
                          <FiKey className="h-3 w-3" /> Generar PIN
                        </button>
                      )
                    ) : (
                      <span className="text-xs text-slate-400">No aplica</span>
                    )}
                  </td>
                  <td className="px-3 py-3">
                    <SwitchField checked={item.status === 1} onChange={() => void handleToggleStatus(item)} label="Estado" />
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de ${item.username}`}
                        items={[
                          { label: 'Editar', icon: FiEdit2, onClick: () => openEdit(item) },
                          { label: 'Restablecer contrasena', icon: FiLock, onClick: () => openResetPassword(item) },
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-slate-500">
                    No hay usuarios registrados todavia.
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
            className="max-h-full w-full max-w-3xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar usuario' : 'Nuevo usuario'}</p>
                <p className="mt-1 text-sm text-slate-500">Cuenta de acceso, rol y sucursal asignada.</p>
              </div>
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500"
              >
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
              <Field label="Usuario" error={formErrors.username}>
                <input value={form.username} onChange={(event) => updateForm('username', event.target.value)} className={inputClass} placeholder="jgomez" />
              </Field>
              <Field label="Nombre completo" error={formErrors.full_name}>
                <input value={form.full_name} onChange={(event) => updateForm('full_name', event.target.value)} className={inputClass} placeholder="Juan Gomez" />
              </Field>
              <Field label="Correo" error={formErrors.email}>
                <input value={form.email} onChange={(event) => updateForm('email', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Telefono" error={formErrors.phone}>
                <input value={form.phone} onChange={(event) => updateForm('phone', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Rol" error={formErrors.role_id}>
                <select value={form.role_id} onChange={(event) => updateForm('role_id', event.target.value)} className={inputClass}>
                  <option value="">Sin rol asignado</option>
                  {roles.map((role) => (
                    <option key={role.id} value={role.id}>
                      {role.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Sucursal" error={formErrors.branch_id}>
                <select value={form.branch_id} onChange={(event) => updateForm('branch_id', event.target.value)} className={inputClass}>
                  <option value="">Sin sucursal asignada</option>
                  {branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                    </option>
                  ))}
                </select>
              </Field>
              {!editing && (
                <Field label="Contrasena" error={formErrors.password}>
                  <input type="password" value={form.password} onChange={(event) => updateForm('password', event.target.value)} className={inputClass} placeholder="Minimo 8 caracteres" />
                </Field>
              )}
              {!editing && (
                <Field label="Estado">
                  <SwitchField checked={form.status} onChange={(checked) => updateForm('status', checked)} label="Estado" />
                </Field>
              )}
            </div>

            {editing && editing.id === currentUser?.id && (
              <p className="mt-4 text-xs text-slate-500">
                Estas editando tu propia cuenta. El estado se cambia desde la lista, no desde este formulario.
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

      {pinTarget && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <div className="w-full max-w-md rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-4 flex items-center gap-3">
              <span className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                <FiKey />
              </span>
              <div>
                <p className="text-lg font-black text-black dark:text-white">PIN de venta rapida</p>
                <p className="text-sm text-slate-500">{pinTarget.full_name}</p>
              </div>
            </div>

            {pinResult ? (
              <>
                <div className="mb-2 rounded-lg border-2 border-dashed border-primary bg-primary/5 px-4 py-6 text-center">
                  <p className="text-3xl font-black tracking-[0.5em] text-primary">{pinResult}</p>
                </div>
                <p className="mb-5 text-xs font-semibold text-amber-600">
                  Guarda este PIN ahora: no se volvera a mostrar. Si se pierde, tendras que generar uno nuevo.
                </p>
                <div className="flex justify-end gap-3">
                  <button
                    type="button"
                    onClick={() => {
                      void navigator.clipboard?.writeText(pinResult);
                      setNotice('PIN copiado al portapapeles.');
                    }}
                    className="inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2.5 text-sm font-bold text-black hover:border-primary hover:text-primary"
                  >
                    <FiCopy /> Copiar
                  </button>
                  <button
                    type="button"
                    onClick={() => setPinTarget(null)}
                    className="inline-flex items-center justify-center rounded-lg bg-primary px-6 py-2.5 text-sm font-bold text-white"
                  >
                    Listo
                  </button>
                </div>
              </>
            ) : (
              <>
                <p className="mb-5 text-sm text-slate-500">
                  Se generara un PIN nuevo de 4 digitos{pinTarget.has_pin ? ', reemplazando el anterior' : ''}. Se
                  mostrara una sola vez.
                </p>
                <div className="flex justify-end gap-3">
                  <button type="button" onClick={() => setPinTarget(null)} className="inline-flex items-center justify-center rounded-lg border border-stroke px-4 py-2.5 text-sm font-bold text-black hover:border-primary hover:text-primary">
                    Cancelar
                  </button>
                  <button
                    type="button"
                    disabled={pinBusy}
                    onClick={() => void confirmGeneratePin()}
                    className="inline-flex items-center justify-center rounded-lg bg-primary px-6 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                  >
                    {pinBusy ? 'Generando...' : 'Generar PIN'}
                  </button>
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {passwordTarget && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form
            onSubmit={confirmResetPassword}
            className="w-full max-w-md rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <p className="mb-1 text-lg font-black text-black dark:text-white">Restablecer contrasena</p>
            <p className="mb-5 text-sm text-slate-500">{passwordTarget.full_name}</p>
            <Field label="Nueva contrasena">
              <input
                type="password"
                value={newPassword}
                onChange={(event) => setNewPassword(event.target.value)}
                className={inputClass}
                placeholder="Minimo 8 caracteres"
                autoFocus
              />
            </Field>
            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={() => setPasswordTarget(null)} className="inline-flex items-center justify-center rounded-lg border border-stroke px-4 py-2.5 text-sm font-bold text-black hover:border-primary hover:text-primary">
                Cancelar
              </button>
              <button type="submit" disabled={passwordBusy} className="inline-flex items-center justify-center rounded-lg bg-primary px-6 py-2.5 text-sm font-bold text-white disabled:opacity-60">
                {passwordBusy ? 'Guardando...' : 'Restablecer'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
