import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { FiEdit2, FiPlus, FiSearch, FiTrash2, FiUserCheck, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { Department, Employee, EmployeeListResponse, EmployeeSaveResponse, Position } from '../../types/employee';
import { ManagedUser } from '../../types/user';
import ActionsMenu from '../../components/ActionsMenu';

type BranchOption = { id: number; name: string };

type FormState = {
  first_name: string;
  last_name: string;
  phone: string;
  email: string;
  branch_id: string;
  department_id: string;
  position_id: string;
  salary: string;
  hire_date: string;
  termination_date: string;
  status: string;
  user_id: string;
};

type FormErrors = Partial<Record<keyof FormState, string>>;

const PHONE_PATTERN = /^[0-9]{1,4}([-\s][0-9]{1,4})*$/;

const emptyForm: FormState = {
  first_name: '',
  last_name: '',
  phone: '',
  email: '',
  branch_id: '',
  department_id: '',
  position_id: '',
  salary: '',
  hire_date: '',
  termination_date: '',
  status: 'ACTIVE',
  user_id: '',
};

const STATUS_STYLES: Record<string, string> = {
  ACTIVE: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  INACTIVE: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  TERMINATED: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
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

function formatCurrency(value: number | null) {
  if (value === null) return '-';
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value);
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

export default function EmployeesPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<Employee[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [positions, setPositions] = useState<Position[]>([]);
  const [branches, setBranches] = useState<BranchOption[]>([]);
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Employee | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formErrors, setFormErrors] = useState<FormErrors>({});
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setLoading(false);
      return;
    }
    setLoading(true);
    setError('');
    try {
      const response = await apiRequest<EmployeeListResponse>('/employees', {}, token);
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
      const [deptRes, posRes, branchRes, userRes] = await Promise.all([
        apiRequest<{ data: Department[] }>('/departments', {}, token),
        apiRequest<{ data: Position[] }>('/positions', {}, token),
        apiRequest<{ data: BranchOption[] }>('/relations/branches', {}, token),
        apiRequest<{ data: ManagedUser[] }>('/users', {}, token),
      ]);
      setDepartments(deptRes.data);
      setPositions(posRes.data);
      setBranches(branchRes.data);
      setUsers(userRes.data);
    } catch {
      setDepartments([]);
      setPositions([]);
      setBranches([]);
      setUsers([]);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadCatalogs();
  }, [loadItems, loadCatalogs]);

  // Solo se ofrecen usuarios que no esten ya vinculados a OTRO empleado (el
  // backend lo rechazaria de todas formas, pero es mejor no ni mostrarlo).
  const availableUsers = useMemo(
    () => users.filter((user) => !user.employee || user.employee.id === editing?.id),
    [users, editing],
  );

  function updateForm<T extends keyof FormState>(field: T, value: FormState[T]) {
    setForm((current) => ({ ...current, [field]: value }));
    setFormErrors((current) => ({ ...current, [field]: undefined }));
  }

  function validateForm(state: FormState): FormErrors {
    const validation: FormErrors = {};
    if (!state.first_name.trim()) validation.first_name = 'Ingresa el nombre.';
    if (!state.last_name.trim()) validation.last_name = 'Ingresa el apellido.';
    if (state.phone.trim() && !PHONE_PATTERN.test(state.phone.trim())) {
      validation.phone = 'Solo numeros, espacios y guiones (ej. 8888-8888).';
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

  function openEdit(item: Employee) {
    setForm({
      first_name: item.first_name,
      last_name: item.last_name,
      phone: item.phone ?? '',
      email: item.email ?? '',
      branch_id: item.branch_id ? String(item.branch_id) : '',
      department_id: item.department_id ? String(item.department_id) : '',
      position_id: item.position_id ? String(item.position_id) : '',
      salary: item.salary !== null ? String(item.salary) : '',
      hire_date: item.hire_date ?? '',
      termination_date: item.termination_date ?? '',
      status: item.status,
      user_id: item.user_id ? String(item.user_id) : '',
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
    if (!token) return;

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<EmployeeSaveResponse>(
        editing ? `/employees/${editing.id}` : '/employees',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify({
            first_name: form.first_name.trim(),
            last_name: form.last_name.trim(),
            phone: form.phone.trim() || null,
            email: form.email.trim() || null,
            branch_id: form.branch_id ? Number(form.branch_id) : null,
            department_id: form.department_id ? Number(form.department_id) : null,
            position_id: form.position_id ? Number(form.position_id) : null,
            salary: form.salary ? Number(form.salary) : null,
            hire_date: form.hire_date || null,
            termination_date: form.status === 'TERMINATED' ? form.termination_date || null : null,
            status: form.status,
            user_id: form.user_id ? Number(form.user_id) : null,
          }),
        },
        token,
      );
      setNotice(response.message || 'Empleado guardado correctamente.');
      closeDialog();
      await loadItems();
      await loadCatalogs();
    } catch (saveError) {
      if (saveError instanceof ApiError && saveError.errors) {
        setFormErrors(
          Object.keys(saveError.errors).reduce<FormErrors>((acc, field) => {
            const key = field as keyof FormErrors;
            return { ...acc, [key]: saveError.errors?.[field]?.[0] };
          }, {}),
        );
      }
      setError(getErrorMessage(saveError));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(item: Employee) {
    if (!token) return;
    if (!window.confirm(`¿Eliminar al empleado ${item.full_name}?`)) return;

    try {
      const response = await apiRequest<{ message: string }>(`/employees/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (deleteError) {
      setError(getErrorMessage(deleteError));
    }
  }

  const filteredItems = useMemo(() => {
    const normalized = normalizeText(search);
    return items.filter((item) =>
      normalizeText(`${item.code} ${item.full_name} ${item.email ?? ''}`).includes(normalized),
    );
  }, [items, search]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Empleados</h2>
          <p className="mt-1 text-sm text-slate-500">Personal de la empresa, su cargo y su acceso al sistema.</p>
        </div>
        <button type="button" onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white">
          <FiPlus className="h-4 w-4" /> Nuevo empleado
        </button>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}
      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

      <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <label className="flex items-center gap-2 rounded-lg border border-stroke bg-white px-3 py-2 dark:border-strokedark dark:bg-boxdark">
          <FiSearch className="text-slate-400" />
          <input value={search} onChange={(event) => setSearch(event.target.value)} className="w-full bg-transparent text-sm outline-none" placeholder="Buscar por nombre, codigo o correo..." />
        </label>
        <div className="text-sm text-slate-500">{filteredItems.length} empleados</div>
      </div>

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Codigo</th>
                <th className="px-3 py-3">Nombre</th>
                <th className="px-3 py-3">Cargo</th>
                <th className="px-3 py-3">Departamento</th>
                <th className="px-3 py-3">Usuario del sistema</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.code}</td>
                  <td className="px-3 py-3">
                    <div className="font-medium text-black dark:text-white">{item.full_name}</div>
                    <div className="text-xs text-slate-500">{item.email ?? item.phone ?? ''}</div>
                  </td>
                  <td className="px-3 py-3">{item.position_name ?? <span className="text-slate-400">Sin asignar</span>}</td>
                  <td className="px-3 py-3">{item.department_name ?? <span className="text-slate-400">Sin asignar</span>}</td>
                  <td className="px-3 py-3">
                    {item.user ? (
                      <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                        <FiUserCheck className="h-3 w-3" /> {item.user.username}
                      </span>
                    ) : (
                      <span className="text-xs text-slate-400">Sin usuario vinculado</span>
                    )}
                  </td>
                  <td className="px-3 py-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${STATUS_STYLES[item.status] ?? ''}`}>
                      {item.status_label}
                    </span>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de ${item.full_name}`}
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
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-slate-500">No hay empleados registrados todavia.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleSubmit} className="max-h-full w-full max-w-3xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar empleado' : 'Nuevo empleado'}</p>
                <p className="mt-1 text-sm text-slate-500">{editing ? `Codigo: ${editing.code}` : 'El codigo se genera automaticamente al guardar.'}</p>
              </div>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <Field label="Nombre" error={formErrors.first_name}>
                <input value={form.first_name} onChange={(event) => updateForm('first_name', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Apellido" error={formErrors.last_name}>
                <input value={form.last_name} onChange={(event) => updateForm('last_name', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Telefono" error={formErrors.phone}>
                <input value={form.phone} onChange={(event) => updateForm('phone', event.target.value)} className={inputClass} placeholder="8888-8888" />
              </Field>
              <Field label="Correo" error={formErrors.email}>
                <input value={form.email} onChange={(event) => updateForm('email', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Sucursal">
                <select value={form.branch_id} onChange={(event) => updateForm('branch_id', event.target.value)} className={inputClass}>
                  <option value="">Sin asignar</option>
                  {branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>{branch.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Departamento">
                <select value={form.department_id} onChange={(event) => updateForm('department_id', event.target.value)} className={inputClass}>
                  <option value="">Sin asignar</option>
                  {departments.map((department) => (
                    <option key={department.id} value={department.id}>{department.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Cargo">
                <select value={form.position_id} onChange={(event) => updateForm('position_id', event.target.value)} className={inputClass}>
                  <option value="">Sin asignar</option>
                  {positions.map((position) => (
                    <option key={position.id} value={position.id}>{position.name}</option>
                  ))}
                </select>
              </Field>
              <Field label="Salario">
                <input type="number" min="0" step="0.01" value={form.salary} onChange={(event) => updateForm('salary', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Fecha de contratacion">
                <input type="date" value={form.hire_date} onChange={(event) => updateForm('hire_date', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Estado">
                <select value={form.status} onChange={(event) => updateForm('status', event.target.value)} className={inputClass}>
                  <option value="ACTIVE">Activo</option>
                  <option value="INACTIVE">Inactivo</option>
                  <option value="TERMINATED">Terminado</option>
                </select>
              </Field>
              {form.status === 'TERMINATED' && (
                <Field label="Fecha de baja">
                  <input type="date" value={form.termination_date} onChange={(event) => updateForm('termination_date', event.target.value)} className={inputClass} />
                </Field>
              )}

              <div className="col-span-full rounded-lg border border-stroke p-4 dark:border-strokedark">
                <Field label="Usuario del sistema vinculado">
                  <select value={form.user_id} onChange={(event) => updateForm('user_id', event.target.value)} className={inputClass}>
                    <option value="">Sin vincular</option>
                    {availableUsers.map((user) => (
                      <option key={user.id} value={user.id}>{user.username} — {user.full_name}</option>
                    ))}
                  </select>
                </Field>
                <p className="mt-2 text-xs text-slate-500">
                  Si marcas a este empleado como "Terminado" y tiene un usuario vinculado, su acceso al sistema se
                  desactiva automaticamente (salvo que sea el unico administrador activo).
                </p>
              </div>
            </div>

            <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button type="button" onClick={closeDialog} className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black hover:border-primary hover:text-primary">Cancelar</button>
              <button type="submit" disabled={submitting} className="inline-flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white disabled:opacity-60">
                {submitting ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
