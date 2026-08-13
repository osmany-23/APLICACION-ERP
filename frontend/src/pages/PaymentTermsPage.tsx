import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { FiEdit2, FiPlus, FiSearch, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';
import { SwitchField } from '../components/SwitchField';
import ActionsMenu from '../components/ActionsMenu';

type PaymentTerm = {
  id: number;
  name: string;
  days: number;
  discount_percent: number;
  discount_days: number | null;
  is_active: boolean;
};

type ListResponse = {
  data: PaymentTerm[];
};

type SaveResponse = {
  message?: string;
  item: PaymentTerm;
};

type DeleteResponse = {
  message: string;
  deleted: boolean;
};

type FormState = {
  name: string;
  days: string;
  discountPercent: string;
  discountDays: string;
  is_active: boolean;
};

const emptyForm: FormState = {
  name: '',
  days: '0',
  discountPercent: '0',
  discountDays: '',
  is_active: true,
};

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const selectClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

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

export default function PaymentTermsPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<PaymentTerm[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<PaymentTerm | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formErrors, setFormErrors] = useState<Partial<Record<keyof FormState, string>>>({});
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
      const response = await apiRequest<ListResponse>('/settings/payment-terms', {}, token);
      setItems(response.data);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
  }, [loadItems]);

  function validateForm(state: FormState) {
    const errors: Partial<Record<keyof FormState, string>> = {};

    if (!state.name.trim()) {
      errors.name = 'El nombre es obligatorio.';
    } else if (state.name.trim().length > 80) {
      errors.name = 'El nombre no puede superar los 80 caracteres.';
    }

    if (!state.days.trim() || Number.isNaN(Number(state.days)) || Number(state.days) < 0) {
      errors.days = 'Los días deben ser un número válido mayor o igual a 0.';
    }

    if (state.discountPercent.trim() && (Number.isNaN(Number(state.discountPercent)) || Number(state.discountPercent) < 0 || Number(state.discountPercent) > 100)) {
      errors.discountPercent = 'El descuento debe estar entre 0 y 100.';
    }

    if (state.discountDays.trim() && (Number.isNaN(Number(state.discountDays)) || Number(state.discountDays) < 0)) {
      errors.discountDays = 'Los días para descuento deben ser un número mayor o igual a 0.';
    }

    return errors;
  }

  function updateForm(field: keyof FormState, value: string | boolean) {
    setForm((current) => ({ ...current, [field]: value }));
    setFormErrors((current) => ({ ...current, [field]: undefined }));
  }

  function openCreate() {
    setForm(emptyForm);
    setEditing(null);
    setFormErrors({});
    setError('');
    setNotice('');
    setDialogOpen(true);
  }

  function openEdit(item: PaymentTerm) {
    setForm({
      name: item.name,
      days: String(item.days),
      discountPercent: String(item.discount_percent),
      discountDays: item.discount_days !== null ? String(item.discount_days) : '',
      is_active: item.is_active,
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
    setError('');
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const errors = validateForm(form);
    setFormErrors(errors);
    if (Object.keys(errors).length > 0) {
      return;
    }

    if (!token) {
      setError('No autenticado.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<SaveResponse>(
        editing ? `/settings/payment-terms/${editing.id}` : '/settings/payment-terms',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify({
            name: form.name.trim(),
            days: Number(form.days),
            discount_percent: Number(form.discountPercent),
            discount_days: form.discountDays.trim() ? Number(form.discountDays) : null,
            is_active: form.is_active,
          }),
        },
        token,
      );

      setNotice(response.message || 'Registro guardado correctamente.');
      closeDialog();
      await loadItems();
    } catch (saveError) {
      if (saveError instanceof ApiError && saveError.errors) {
        setFormErrors(
          Object.keys(saveError.errors).reduce((acc, field) => {
            const key = field as keyof FormState;
            return { ...acc, [key]: saveError.errors?.[field]?.[0] };
          }, {} as Partial<Record<keyof FormState, string>>),
        );
      }
      setError(getErrorMessage(saveError));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(item: PaymentTerm) {
    if (!token) return;

    const confirmed = window.confirm(`¿Eliminar ${item.name}?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<DeleteResponse>(`/settings/payment-terms/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (deleteError) {
      setError(getErrorMessage(deleteError));
    }
  }

  const filteredItems = useMemo(() => {
    const normalized = normalizeText(search);
    return items.filter((item) => normalizeText(`${item.name} ${item.days} ${item.discount_percent} ${item.discount_days ?? ''}`).includes(normalized));
  }, [items, search]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Términos de Pago</h2>
          <p className="mt-1 text-sm text-slate-500">Catálogo global de condiciones de pago.</p>
        </div>
        <button type="button" className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white" onClick={openCreate}>
          <FiPlus className="h-4 w-4" /> Nuevo término
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
            placeholder="Buscar términos..."
          />
        </label>
        <div className="text-sm text-slate-500">{filteredItems.length} registros</div>
      </div>

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Nombre</th>
                <th className="px-3 py-3">Días</th>
                <th className="px-3 py-3">Descuento (%)</th>
                <th className="px-3 py-3">Días para descuento</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.name}</td>
                  <td className="px-3 py-3">{item.days}</td>
                  <td className="px-3 py-3">{item.discount_percent}%</td>
                  <td className="px-3 py-3">{item.discount_days ?? '-'}</td>
                  <td className="px-3 py-3">{item.is_active ? 'Activo' : 'Inactivo'}</td>
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
            </tbody>
          </table>
        </div>
      )}

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleSubmit} className="max-h-full w-full max-w-2xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar término' : 'Nuevo término'}</p>
                <p className="mt-1 text-sm text-slate-500">Los cambios se aplican directamente en la base de datos.</p>
              </div>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {Object.values(formErrors).some(Boolean) && (
              <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">
                Revisa los campos del formulario.
              </div>
            )}

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <Field label="Nombre" error={formErrors.name}>
                <input
                  value={form.name}
                  onChange={(event) => updateForm('name', event.target.value)}
                  className={inputClass}
                  placeholder="30 días"
                />
              </Field>
              <Field label="Días" error={formErrors.days}>
                <input
                  type="number"
                  min="0"
                  value={form.days}
                  onChange={(event) => updateForm('days', event.target.value)}
                  className={inputClass}
                  placeholder="30"
                />
              </Field>
              <Field label="Descuento (%)" error={formErrors.discountPercent}>
                <input
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  value={form.discountPercent}
                  onChange={(event) => updateForm('discountPercent', event.target.value)}
                  className={inputClass}
                  placeholder="0"
                />
              </Field>
              <Field label="Días para descuento" error={formErrors.discountDays}>
                <input
                  type="number"
                  min="0"
                  value={form.discountDays}
                  onChange={(event) => updateForm('discountDays', event.target.value)}
                  className={inputClass}
                  placeholder="10"
                />
              </Field>
              <Field label="Estado">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(event) => updateForm('is_active', event.target.checked)}
                  className="h-5 w-5 rounded border border-stroke text-primary focus:ring-primary"
                />
              </Field>
            </div>

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
    </div>
  );
}
