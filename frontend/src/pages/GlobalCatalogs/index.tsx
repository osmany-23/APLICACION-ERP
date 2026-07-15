import { FormEvent, ReactNode, useCallback, useEffect, useMemo, useState } from 'react';
import { FiEdit2, FiPlus, FiRefreshCw, FiSearch, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';

type GlobalCatalogType = 'currencies' | 'payment-terms' | 'payment-methods';

type GlobalCatalogItem = {
  id: number;
  code?: string | null;
  name: string;
  symbol?: string | null;
  decimal_places?: number | string | null;
  description?: string | null;
  days?: number | string | null;
  discount_percent?: number | string | null;
  discount_days?: number | string | null;
  is_active?: boolean | null;
};

type CatalogResponse = {
  data: GlobalCatalogItem[];
};

type CatalogMutationResponse = {
  message?: string;
  item: GlobalCatalogItem;
};

type CatalogDeleteResponse = {
  message: string;
  deleted: boolean;
};

type CatalogDraft = {
  code: string;
  name: string;
  symbol: string;
  decimalPlaces: string;
  description: string;
  days: string;
  discountPercent: string;
  discountDays: string;
  isActive: boolean;
};

type CatalogConfig = {
  title: string;
  singular: string;
  newLabel: string;
  searchPlaceholder: string;
  isCurrency: boolean;
};

const configs: Record<GlobalCatalogType, CatalogConfig> = {
  currencies: {
    title: 'Monedas',
    singular: 'moneda',
    newLabel: 'Nueva moneda',
    searchPlaceholder: 'Buscar monedas...',
    isCurrency: true,
  },
  'payment-terms': {
    title: 'Términos de Pago',
    singular: 'término de pago',
    newLabel: 'Nuevo término',
    searchPlaceholder: 'Buscar términos...',
    isCurrency: false,
  },
  'payment-methods': {
    title: 'Métodos de Pago',
    singular: 'método de pago',
    newLabel: 'Nuevo método',
    searchPlaceholder: 'Buscar métodos...',
    isCurrency: false,
  },
};

const emptyDraft: CatalogDraft = {
  code: '',
  name: '',
  symbol: '',
  decimalPlaces: '2',
  description: '',
  days: '0',
  discountPercent: '0',
  discountDays: '0',
  isActive: true,
};

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const selectClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const textareaClass =
  'min-h-28 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function readNumber(value: unknown) {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function normalizeText(value: string) {
  return value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }
  return 'La solicitud no pudo completarse.';
}

function Field({ children, label }: { children: ReactNode; label: string }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">{label}</span>
      {children}
    </label>
  );
}

function GlobalCatalogsPage({ catalog }: { catalog?: GlobalCatalogType }) {
  const { token } = useAuth();
  const config = configs[catalog];
  const [items, setItems] = useState<GlobalCatalogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState('');
  const [notice, setNotice] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<GlobalCatalogItem | null>(null);
  const [draft, setDraft] = useState<CatalogDraft>(emptyDraft);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setPageError('');

    try {
      const response = await apiRequest<CatalogResponse>(`/settings/${catalog}`, {}, token);
      setItems(response.data.map((item) => ({ ...item, id: Number(item.id) })));
    } catch (error) {
      setPageError(getErrorMessage(error));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [catalog, token]);

  useEffect(() => {
    setSearchTerm('');
    setDialogOpen(false);
    setEditingItem(null);
    setDraft(emptyDraft);
    setNotice('');
    void loadItems();
  }, [loadItems]);

  const filteredItems = useMemo(() => {
    const search = normalizeText(searchTerm);
    return items.filter((item) => {
      const haystack = normalizeText(`${item.name} ${item.code || ''} ${item.symbol || ''}`);
      return haystack.includes(search);
    });
  }, [items, searchTerm]);

  function updateDraft(field: keyof CatalogDraft, value: string | boolean) {
    setDraft((current) => ({ ...current, [field]: value }));
  }

  function openCreateDialog() {
    setDraft(emptyDraft);
    setEditingItem(null);
    setFormError('');
    setDialogOpen(true);
  }

  function openEditDialog(item: GlobalCatalogItem) {
    setDraft({
      code: item.code || '',
      name: item.name || '',
      symbol: item.symbol || '',
      decimalPlaces: item.decimal_places ? String(item.decimal_places) : '2',
      description: item.description || '',
      days: item.days ? String(item.days) : '0',
      discountPercent: item.discount_percent ? String(item.discount_percent) : '0',
      discountDays: item.discount_days ? String(item.discount_days) : '0',
      isActive: Boolean(item.is_active ?? true),
    });
    setEditingItem(item);
    setFormError('');
    setDialogOpen(true);
  }

  function closeDialog() {
    setDialogOpen(false);
    setEditingItem(null);
    setDraft(emptyDraft);
    setFormError('');
  }

  function relationPayload() {
    if (config.isCurrency) {
      return {
        code: draft.code.trim().toUpperCase(),
        name: draft.name.trim(),
        symbol: draft.symbol.trim(),
        decimal_places: Number(draft.decimalPlaces) || 2,
        is_active: draft.isActive,
      };
    }

    if (catalog === 'payment-methods') {
      return {
        code: draft.code.trim().toUpperCase(),
        name: draft.name.trim(),
        description: draft.description.trim(),
        is_active: draft.isActive,
      };
    }

    return {
      name: draft.name.trim(),
      days: Number(draft.days) || 0,
      discount_percent: Number(draft.discountPercent) || 0,
      discount_days: Number(draft.discountDays) || 0,
      is_active: draft.isActive,
    };
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!draft.name.trim()) {
      setFormError('Ingresa el nombre.');
      return;
    }

    if (config.isCurrency) {
      if (!draft.code.trim()) {
        setFormError('Ingresa el código de la moneda.');
        return;
      }
      if (!draft.symbol.trim()) {
        setFormError('Ingresa el símbolo.');
        return;
      }
      const decimalPlaces = Number(draft.decimalPlaces);
      if (!Number.isInteger(decimalPlaces) || decimalPlaces < 0 || decimalPlaces > 6) {
        setFormError('Los decimales deben estar entre 0 y 6.');
        return;
      }
    } else if (catalog === 'payment-methods') {
      if (!draft.code.trim()) {
        setFormError('Ingresa el código del método.');
        return;
      }
      if (!draft.name.trim()) {
        setFormError('Ingresa el nombre del método.');
        return;
      }
    } else {
      const days = Number(draft.days);
      const discountPercent = Number(draft.discountPercent);
      if (!Number.isInteger(days) || days < 0) {
        setFormError('Los días deben ser un número mayor o igual a 0.');
        return;
      }
      if (discountPercent < 0 || discountPercent > 100) {
        setFormError('El descuento debe estar entre 0 y 100.');
        return;
      }
    }

    if (!token) {
      setFormError('No autenticado.');
      return;
    }

    setSubmitting(true);
    setFormError('');

    try {
      const response = await apiRequest<CatalogMutationResponse>(
        editingItem ? `/settings/${catalog}/${editingItem.id}` : `/settings/${catalog}`,
        {
          method: editingItem ? 'PUT' : 'POST',
          body: JSON.stringify(relationPayload()),
        },
        token,
      );
      setNotice(response.message || 'Registro guardado correctamente.');
      closeDialog();
      await loadItems();
    } catch (error) {
      setFormError(getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(item: GlobalCatalogItem) {
    if (!token) return;
    const accepted = window.confirm(`Eliminar ${item.name}?`);
    if (!accepted) return;

    try {
      const response = await apiRequest<CatalogDeleteResponse>(`/settings/${catalog}/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (error) {
      setPageError(getErrorMessage(error));
    }
  }

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">{config.title}</h2>
          <p className="mt-1 text-sm text-slate-500">Catálogo global compartido por todas las empresas.</p>
        </div>
        <button type="button" className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white" onClick={openCreateDialog}>
          <FiPlus className="h-4 w-4" /> {config.newLabel}
        </button>
      </div>

      {pageError && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{pageError}</div>}
      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

      <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <label className="flex items-center gap-2 rounded-lg border border-stroke bg-white px-3 py-2 dark:border-strokedark dark:bg-boxdark">
          <FiSearch className="text-slate-400" />
          <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} className="w-full bg-transparent text-sm outline-none" placeholder={config.searchPlaceholder} />
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
                {config.isCurrency || catalog === 'payment-methods' ? <th className="px-3 py-3">Código</th> : <th className="px-3 py-3">Nombre</th>}
                <th className="px-3 py-3">Nombre</th>
                {config.isCurrency ? <th className="px-3 py-3">Símbolo</th> : catalog === 'payment-methods' ? <th className="px-3 py-3">Descripción</th> : <th className="px-3 py-3">Días</th>}
                {config.isCurrency ? <th className="px-3 py-3">Decimales</th> : catalog === 'payment-methods' ? <th className="px-3 py-3">Estado</th> : <th className="px-3 py-3">Descuento</th>}
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{config.isCurrency || catalog === 'payment-methods' ? item.code : item.name}</td>
                  <td className="px-3 py-3">{item.name}</td>
                  <td className="px-3 py-3">{config.isCurrency ? item.symbol : catalog === 'payment-methods' ? item.description : `${item.days ?? 0} días`}</td>
                  <td className="px-3 py-3">{config.isCurrency ? `${item.decimal_places ?? 0}` : catalog === 'payment-methods' ? (item.is_active ? 'Activo' : 'Inactivo') : `${item.discount_percent ?? 0}%`}</td>
                  <td className="px-3 py-3">{item.is_active ? 'Activo' : 'Inactivo'}</td>
                  <td className="px-3 py-3">
                    <div className="flex justify-end gap-2">
                      <button type="button" onClick={() => openEditDialog(item)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#0F9F37] text-[#0F9F37] hover:bg-[#0F9F37] hover:text-white">
                        <FiEdit2 />
                      </button>
                      <button type="button" onClick={() => void handleDelete(item)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-500 text-red-500 hover:bg-red-500 hover:text-white">
                        <FiTrash2 />
                      </button>
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
                <p className="text-xl font-black text-black dark:text-white">{editingItem ? `Editar ${config.singular}` : config.newLabel}</p>
                <p className="mt-1 text-sm text-slate-500">Los cambios se aplican directamente en la base de datos.</p>
              </div>
              <button type="button" onClick={closeDialog} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {formError && <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">{formError}</div>}

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              {config.isCurrency ? (
                <>
                  <Field label="Código">
                    <input value={draft.code} onChange={(event) => updateDraft('code', event.target.value)} className={inputClass} placeholder="USD" />
                  </Field>
                  <Field label="Nombre">
                    <input value={draft.name} onChange={(event) => updateDraft('name', event.target.value)} className={inputClass} placeholder="Dólar" />
                  </Field>
                  <Field label="Símbolo">
                    <input value={draft.symbol} onChange={(event) => updateDraft('symbol', event.target.value)} className={inputClass} placeholder="$" />
                  </Field>
                  <Field label="Decimales">
                    <input type="number" min="0" max="6" value={draft.decimalPlaces} onChange={(event) => updateDraft('decimalPlaces', event.target.value)} className={inputClass} placeholder="2" />
                  </Field>
                </>
              ) : catalog === 'payment-methods' ? (
                <>
                  <Field label="Código">
                    <input value={draft.code} onChange={(event) => updateDraft('code', event.target.value)} className={inputClass} placeholder="TRF" />
                  </Field>
                  <Field label="Nombre">
                    <input value={draft.name} onChange={(event) => updateDraft('name', event.target.value)} className={inputClass} placeholder="Transferencia" />
                  </Field>
                  <Field label="Descripción">
                    <textarea value={draft.description} onChange={(event) => updateDraft('description', event.target.value)} className={textareaClass} placeholder="Detalles del método" />
                  </Field>
                </>
              ) : (
                <>
                  <Field label="Nombre">
                    <input value={draft.name} onChange={(event) => updateDraft('name', event.target.value)} className={inputClass} placeholder="30 días" />
                  </Field>
                  <Field label="Días">
                    <input type="number" min="0" value={draft.days} onChange={(event) => updateDraft('days', event.target.value)} className={inputClass} placeholder="30" />
                  </Field>
                  <Field label="Descuento (%)">
                    <input type="number" min="0" max="100" step="0.01" value={draft.discountPercent} onChange={(event) => updateDraft('discountPercent', event.target.value)} className={inputClass} placeholder="0" />
                  </Field>
                  <Field label="Días para descuento">
                    <input type="number" min="0" value={draft.discountDays} onChange={(event) => updateDraft('discountDays', event.target.value)} className={inputClass} placeholder="0" />
                  </Field>
                </>
              )}

              <Field label="Estado">
                <SwitchField
                  label="Estado"
                  checked={draft.isActive}
                  onChange={(checked) => updateDraft('isActive', checked)}
                />
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

export default function GlobalCatalogsRouter({ catalog }: { catalog?: GlobalCatalogType }) {
  const resolvedCatalog = catalog ?? 'currencies';
  return <GlobalCatalogsPage catalog={resolvedCatalog} />;
}
