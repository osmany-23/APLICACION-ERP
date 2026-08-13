import { FormEvent, useCallback, useEffect, useState } from 'react';
import { FiEdit2, FiPlus, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';
import { Department, Position, PositionListResponse, PositionSaveResponse } from '../../types/employee';
import ActionsMenu from '../../components/ActionsMenu';

type FormState = { name: string; description: string; department_id: string; base_salary: string; status: boolean };
type FormErrors = Partial<Record<keyof FormState, string>>;

const emptyForm: FormState = { name: '', description: '', department_id: '', base_salary: '', status: true };

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

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

export default function PositionsPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<Position[]>([]);
  const [departments, setDepartments] = useState<Department[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Position | null>(null);
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
      const response = await apiRequest<PositionListResponse>('/positions', {}, token);
      setItems(response.data);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token]);

  const loadDepartments = useCallback(async () => {
    if (!token) return;
    try {
      const response = await apiRequest<{ data: Department[] }>('/departments', {}, token);
      setDepartments(response.data);
    } catch {
      setDepartments([]);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadDepartments();
  }, [loadItems, loadDepartments]);

  function updateForm<T extends keyof FormState>(field: T, value: FormState[T]) {
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

  function openEdit(item: Position) {
    setForm({
      name: item.name,
      description: item.description ?? '',
      department_id: item.department_id ? String(item.department_id) : '',
      base_salary: item.base_salary !== null ? String(item.base_salary) : '',
      status: item.status,
    });
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
      setFormErrors({ name: 'Ingresa el nombre del cargo.' });
      return;
    }
    if (!token) return;

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<PositionSaveResponse>(
        editing ? `/positions/${editing.id}` : '/positions',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify({
            name: form.name.trim(),
            description: form.description.trim() || null,
            department_id: form.department_id ? Number(form.department_id) : null,
            base_salary: form.base_salary ? Number(form.base_salary) : null,
            status: form.status,
          }),
        },
        token,
      );
      setNotice(response.message || 'Cargo guardado correctamente.');
      closeDialog();
      await loadItems();
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

  async function handleDelete(item: Position) {
    if (!token) return;
    if (!window.confirm(`¿Eliminar el cargo "${item.name}"?`)) return;

    try {
      const response = await apiRequest<{ message: string }>(`/positions/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (deleteError) {
      setError(getErrorMessage(deleteError));
    }
  }

  async function handleToggleStatus(item: Position) {
    if (!token) return;
    try {
      await apiRequest(`/positions/${item.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: !item.status }) }, token);
      await loadItems();
    } catch (toggleError) {
      setError(getErrorMessage(toggleError));
    }
  }

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Cargos</h2>
          <p className="mt-1 text-sm text-slate-500">Puestos de trabajo de la empresa y su salario de referencia.</p>
        </div>
        <button type="button" onClick={openCreate} className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white">
          <FiPlus className="h-4 w-4" /> Nuevo cargo
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
                <th className="px-3 py-3">Cargo</th>
                <th className="px-3 py-3">Departamento</th>
                <th className="px-3 py-3 text-right">Salario de referencia</th>
                <th className="px-3 py-3 text-center">Empleados</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3">
                    <div className="font-semibold text-black dark:text-white">{item.name}</div>
                    {item.description && <div className="text-xs text-slate-500">{item.description}</div>}
                  </td>
                  <td className="px-3 py-3">{item.department_name ?? <span className="text-slate-400">Sin asignar</span>}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.base_salary)}</td>
                  <td className="px-3 py-3 text-center">{item.employees_count}</td>
                  <td className="px-3 py-3">
                    <SwitchField checked={item.status} onChange={() => void handleToggleStatus(item)} label="Estado" />
                  </td>
                  <td className="px-3 py-3">
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
              {items.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-3 py-8 text-center text-sm text-slate-500">No hay cargos registrados todavia.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleSubmit} className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar cargo' : 'Nuevo cargo'}</p>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Nombre</span>
                <input value={form.name} onChange={(event) => updateForm('name', event.target.value)} className={inputClass} placeholder="Ej. Vendedor" />
                {formErrors.name && <span className="mt-2 block text-xs font-semibold text-red-500">{formErrors.name}</span>}
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Departamento</span>
                <select value={form.department_id} onChange={(event) => updateForm('department_id', event.target.value)} className={inputClass}>
                  <option value="">Sin asignar</option>
                  {departments.map((department) => (
                    <option key={department.id} value={department.id}>{department.name}</option>
                  ))}
                </select>
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Salario de referencia</span>
                <input type="number" min="0" step="0.01" value={form.base_salary} onChange={(event) => updateForm('base_salary', event.target.value)} className={inputClass} placeholder="Opcional" />
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Descripcion</span>
                <textarea value={form.description} onChange={(event) => updateForm('description', event.target.value)} className="min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white" />
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Estado</span>
                <SwitchField checked={form.status} onChange={(checked) => updateForm('status', checked)} label="Estado" />
              </label>
            </div>

            <div className="mt-7 flex justify-end gap-3">
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
