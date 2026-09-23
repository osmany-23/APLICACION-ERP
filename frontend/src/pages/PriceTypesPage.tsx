import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { FiEdit2, FiPlus, FiSearch, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';
import { SwitchField } from '../components/SwitchField';
import ActionsMenu from '../components/ActionsMenu';

type PriceType = {
  id: number;
  code: string;
  name: string;
  description?: string | null;
  is_default: boolean;
  is_active: boolean;
  products_count: number;
};

type ListResponse = {
  data: PriceType[];
};

type SaveResponse = {
  message?: string;
  item: PriceType;
};

type DeleteResponse = {
  message: string;
  deleted: boolean;
};

type FormErrors = Partial<Record<'name' | 'description' | 'is_active', string>>;

type FormState = {
  name: string;
  description: string;
  is_default: boolean;
  is_active: boolean;
};

const emptyForm: FormState = {
  name: '',
  description: '',
  is_default: false,
  is_active: true,
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

export default function PriceTypesPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<PriceType[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<PriceType | null>(null);
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
      const response = await apiRequest<ListResponse>('/settings/price-types', {}, token);
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

  function validateForm(state: FormState): FormErrors {
    const validation: FormErrors = {};

    if (!state.name.trim()) {
      validation.name = 'El nombre es obligatorio.';
    } else if (state.name.trim().length > 100) {
      validation.name = 'El nombre no puede superar los 100 caracteres.';
    }

    if (state.description.trim().length > 1000) {
      validation.description = 'La descripción es demasiado larga.';
    }

    return validation;
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

  function openEdit(item: PriceType) {
    setForm({
      name: item.name,
      description: item.description ?? '',
      is_default: item.is_default,
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
        editing ? `/settings/price-types/${editing.id}` : '/settings/price-types',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify({
            name: form.name.trim(),
            description: form.description.trim() || null,
            is_default: form.is_default,
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

  async function handleDelete(item: PriceType) {
    if (!token) return;

    const confirmed = window.confirm(`¿Eliminar el tipo de precio "${item.name}"?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<DeleteResponse>(`/settings/price-types/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (deleteError) {
      setError(getErrorMessage(deleteError));
    }
  }

  const filteredItems = useMemo(() => {
    const normalized = normalizeText(search);
    return items.filter((item) => normalizeText(`${item.code} ${item.name} ${item.description ?? ''}`).includes(normalized));
  }, [items, search]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Tipos de Precio</h2>
          <p className="mt-1 text-sm text-slate-500">
            Precios adicionales al precio base (Mayorista, Distribuidor, etc.). La cantidad minima a la que aplica
            cada uno se configura por producto, desde el formulario de Productos.
          </p>
        </div>
        <button type="button" className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white" onClick={openCreate}>
          <FiPlus className="h-4 w-4" /> Nuevo tipo de precio
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
            placeholder="Buscar tipos de precio..."
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
                <th className="px-3 py-3">Código</th>
                <th className="px-3 py-3">Nombre</th>
                <th className="px-3 py-3">Descripción</th>
                <th className="px-3 py-3">Predeterminado</th>
                <th className="px-3 py-3">Productos</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.code}</td>
                  <td className="px-3 py-3">{item.name}</td>
                  <td className="px-3 py-3">{item.description ?? '-'}</td>
                  <td className="px-3 py-3">{item.is_default ? 'Sí' : 'No'}</td>
                  <td className="px-3 py-3">{item.products_count}</td>
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
              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-slate-500">
                    No hay tipos de precio configurados todavía.
                  </td>
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
              <div>
                <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar tipo de precio' : 'Nuevo tipo de precio'}</p>
                <p className="mt-1 text-sm text-slate-500">El código se genera automáticamente a partir del nombre.</p>
              </div>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <Field label="Nombre" error={formErrors.name}>
                <input
                  value={form.name}
                  onChange={(event) => updateForm('name', event.target.value)}
                  className="h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                  placeholder="Mayorista"
                />
              </Field>
              <Field label="Estado" error={formErrors.is_active}>
                <SwitchField checked={form.is_active} onChange={(checked) => updateForm('is_active', checked)} label="Estado" />
              </Field>
              <div className="md:col-span-2">
                <Field label="Descripción" error={formErrors.description}>
                  <textarea
                    value={form.description}
                    onChange={(event) => updateForm('description', event.target.value)}
                    className="min-h-28 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                    placeholder="Descripción opcional"
                  />
                </Field>
              </div>
              <Field label="Predeterminado">
                <SwitchField checked={form.is_default} onChange={(checked) => updateForm('is_default', checked)} label="Predeterminado" />
              </Field>
            </div>

            <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button type="button" onClick={closeDialog} className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black transition hover:border-primary hover:text-primary">Cancelar</button>
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
