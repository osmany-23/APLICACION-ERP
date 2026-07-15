import {
  FormEvent,
  ReactNode,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from 'react';
import {
  FiAlertCircle,
  FiEdit2,
  FiLayers,
  FiPlus,
  FiRefreshCw,
  FiSave,
  FiSearch,
  FiTag,
  FiTrash2,
  FiX,
} from 'react-icons/fi';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';
import { SwitchField } from '../components/SwitchField';

type CatalogType = 'brands' | 'categories' | 'subcategories' | 'units';

type CatalogItem = {
  id: number;
  code?: string | null;
  name: string;
  is_active?: boolean | null;
  short_name?: string | null;
  parent_id?: number | null;
  parent_name?: string | null;
  margin_percent?: number | string | null;
  products_count?: number | string | null;
  subcategories_count?: number | string | null;
  image_url?: string | null;
  description?: string | null;
  level?: number | string | null;
  category?: string | null;
  decimal_places?: number | string | null;
};

type CatalogResponse = {
  data: CatalogItem[];
};

type CatalogMutationResponse = {
  message?: string;
  item: CatalogItem;
};

type CatalogDeleteResponse = {
  message: string;
  deleted: boolean;
};

type CatalogDraft = {
  name: string;
  shortName: string;
  parentId: string;
  marginPercent: string;
  imageUrl: string;
  description: string;
  level: string;
  category: string;
  decimalPlaces: string;
  isActive: boolean;
};

type CatalogConfig = {
  title: string;
  singular: string;
  newLabel: string;
  searchPlaceholder: string;
  hasMargin?: boolean;
  hasParent?: boolean;
  hasShortName?: boolean;
  hasDescription?: boolean;
  hasImage?: boolean;
  hasLevel?: boolean;
  hasCategory?: boolean;
  hasDecimalPlaces?: boolean;
};

const configs: Record<CatalogType, CatalogConfig> = {
  brands: {
    title: 'Marcas',
    singular: 'marca',
    newLabel: 'Nueva marca',
    searchPlaceholder: 'Buscar marcas...',
    hasDescription: true,
    hasImage: true,
  },
  categories: {
    title: 'Categorias',
    singular: 'categoria',
    newLabel: 'Nueva categoria',
    searchPlaceholder: 'Buscar categorias...',
    hasMargin: true,
    hasDescription: true,
    hasImage: true,
    hasLevel: true,
  },
  subcategories: {
    title: 'Subcategorias',
    singular: 'subcategoria',
    newLabel: 'Nueva subcategoria',
    searchPlaceholder: 'Buscar subcategorias...',
    hasMargin: true,
    hasParent: true,
    hasDescription: true,
    hasImage: true,
    hasLevel: true,
  },
  units: {
    title: 'Unidades de medida',
    singular: 'unidad',
    newLabel: 'Nueva unidad',
    searchPlaceholder: 'Buscar unidades...',
    hasShortName: true,
    hasCategory: true,
    hasDecimalPlaces: true,
  },
};

const emptyDraft: CatalogDraft = {
  name: '',
  shortName: '',
  parentId: '',
  marginPercent: '0',
  imageUrl: '',
  description: '',
  level: '1',
  category: 'UNIDAD',
  decimalPlaces: '0',
  isActive: true,
};

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

const selectClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function readNumber(value: unknown) {
  const parsed = Number(value ?? 0);

  return Number.isFinite(parsed) ? parsed : 0;
}

function normalizeText(value: string) {
  return value
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors
      ? Object.values(error.errors).flat()[0]
      : null;

    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function mapItem(item: CatalogItem): CatalogItem {
  return {
    ...item,
    id: Number(item.id),
    name: item.name || '',
    margin_percent: readNumber(item.margin_percent),
    products_count: readNumber(item.products_count),
    subcategories_count: readNumber(item.subcategories_count),
    parent_id: item.parent_id ? Number(item.parent_id) : null,
    description: item.description || '',
    level: readNumber(item.level),
    category: item.category || 'UNIDAD',
    decimal_places: readNumber(item.decimal_places),
    is_active: Boolean(item.is_active ?? true),
  };
}

function Field({
  children,
  label,
}: {
  children: ReactNode;
  label: string;
}) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">
        {label}
      </span>
      {children}
    </label>
  );
}

function ProductCatalogs({ catalog }: { catalog: CatalogType }) {
  const { token } = useAuth();
  const config = configs[catalog];
  const [items, setItems] = useState<CatalogItem[]>([]);
  const [categories, setCategories] = useState<CatalogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState('');
  const [notice, setNotice] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<CatalogItem | null>(null);
  const [draft, setDraft] = useState<CatalogDraft>(emptyDraft);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setCategories([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setPageError('');

    try {
      const [itemsResponse, categoriesResponse] = await Promise.all([
        apiRequest<CatalogResponse>(`/catalogs/${catalog}`, {}, token),
        config.hasParent
          ? apiRequest<CatalogResponse>('/catalogs/categories', {}, token)
          : Promise.resolve({ data: [] }),
      ]);

      setItems(itemsResponse.data.map(mapItem));
      setCategories(categoriesResponse.data.map(mapItem));
    } catch (error) {
      setPageError(getErrorMessage(error));
      setItems([]);
      setCategories([]);
    } finally {
      setLoading(false);
    }
  }, [catalog, config.hasParent, token]);

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

    if (!search) {
      return items;
    }

    return items.filter((item) =>
      [
        item.name,
        item.short_name || '',
        item.parent_name || '',
        item.description || '',
        item.category || '',
        String(item.level || ''),
      ]
        .map(normalizeText)
        .some((value) => value.includes(search)),
    );
  }, [items, searchTerm]);

  const usedCount = items.filter(
    (item) => readNumber(item.products_count) > 0,
  ).length;
  const relatedCount = items.reduce(
    (total, item) => total + readNumber(item.subcategories_count),
    0,
  );
  const tableColumns =
    catalog === 'brands'
      ? 6
      : catalog === 'units'
        ? 6
        : 7;

  function updateDraft(field: keyof CatalogDraft, value: string | boolean) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      [field]: value,
    }));
  }

  function openCreateDialog() {
    setDraft({
      ...emptyDraft,
      imageUrl: '',
      isActive: true,
    });
    setEditingItem(null);
    setFormError('');
    setDialogOpen(true);
  }

  function openEditDialog(item: CatalogItem) {
    setDraft({
      name: item.name,
      shortName: item.short_name || '',
      parentId: item.parent_id ? String(item.parent_id) : '',
      marginPercent: String(readNumber(item.margin_percent)),
      imageUrl: catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories'
        ? String(item.image_url || '')
        : '',
      description: catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories'
        ? String(item.description || '')
        : '',
      level: String(readNumber(item.level || 1)),
      category: String(item.category || 'UNIDAD'),
      decimalPlaces: String(readNumber(item.decimal_places || 0)),
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

  function catalogPayload() {
    const payload: Record<string, string | number> = {
      name: draft.name.trim(),
    };

    if (config.hasImage) {
      payload.image_url = draft.imageUrl.trim();
    }

    if (config.hasDescription) {
      payload.description = draft.description.trim();
    }

    if (config.hasShortName) {
      payload.short_name = draft.shortName.trim().toUpperCase();
    }

    if (config.hasParent) {
      payload.parent_id = Number(draft.parentId);
    }

    if (config.hasMargin) {
      payload.margin_percent = Number(draft.marginPercent || 0);
    }

    payload.is_active = draft.isActive;

    if (config.hasLevel) {
      payload.level = Number(draft.level || 1);
    }

    if (config.hasCategory) {
      payload.category = draft.category.trim().toUpperCase();
    }

    if (config.hasDecimalPlaces) {
      payload.decimal_places = Number(draft.decimalPlaces || 0);
    }

    return payload;
  }

  async function handleStatusToggle(item: CatalogItem, nextValue: boolean) {
    if (!token) {
      return;
    }

    setSubmitting(true);
    try {
      const payload: Record<string, string | number | boolean> = {
        name: item.name,
        is_active: nextValue,
      };

      if (catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') {
        payload.description = item.description || '';
        payload.image_url = item.image_url || '';
      }

      if (config.hasShortName) {
        payload.short_name = item.short_name || '';
      }

      if (config.hasParent) {
        payload.parent_id = item.parent_id ?? 0;
      }

      if (config.hasMargin) {
        payload.margin_percent = readNumber(item.margin_percent);
      }

      if (config.hasLevel) {
        payload.level = readNumber(item.level || 1);
      }

      if (config.hasCategory) {
        payload.category = item.category || 'UNIDAD';
      }

      if (config.hasDecimalPlaces) {
        payload.decimal_places = readNumber(item.decimal_places || 0);
      }

      const response = await apiRequest<CatalogMutationResponse>(
        `/catalogs/${catalog}/${item.id}`,
        {
          method: 'PUT',
          body: JSON.stringify(payload),
        },
        token,
      );

      setNotice(response.message || 'Estado actualizado correctamente.');
      await loadItems();
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!draft.name.trim()) {
      setFormError('Ingresa el nombre.');
      return;
    }

    if (config.hasShortName && !draft.shortName.trim()) {
      setFormError('Ingresa la abreviatura.');
      return;
    }

    if (config.hasParent && !draft.parentId) {
      setFormError('Selecciona una categoria.');
      return;
    }

    if (!token) {
      setFormError('No autenticado.');
      return;
    }

    setSubmitting(true);
    setFormError('');

    try {
      const response = await apiRequest<CatalogMutationResponse>(
        editingItem
          ? `/catalogs/${catalog}/${editingItem.id}`
          : `/catalogs/${catalog}`,
        {
          method: editingItem ? 'PUT' : 'POST',
          body: JSON.stringify(catalogPayload()),
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

  async function handleDelete(item: CatalogItem) {
    if (!token) {
      return;
    }

    const accepted = window.confirm(
      `Eliminar ${item.name}? Esta accion se aplicara en la base de datos.`,
    );

    if (!accepted) {
      return;
    }

    try {
      const response = await apiRequest<CatalogDeleteResponse>(
        `/catalogs/${catalog}/${item.id}`,
        { method: 'DELETE' },
        token,
      );
      setNotice(response.message);
      await loadItems();
    } catch (error) {
      setPageError(getErrorMessage(error));
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-title-md2 font-black uppercase tracking-normal text-black dark:text-white">
            {config.title}
          </h1>
          <p className="mt-1 text-sm font-semibold text-slate-500">
            Catalogo de productos
          </p>
        </div>
        <button
          type="button"
          onClick={openCreateDialog}
          disabled={loading}
          className="inline-flex h-12 items-center justify-center gap-3 rounded-lg bg-primary px-5 text-sm font-bold text-white shadow-4 transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          <FiPlus className="h-5 w-5" />
          {config.newLabel}
        </button>
      </div>

      {pageError && (
        <div className="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-600 sm:flex-row sm:items-center sm:justify-between">
          <span className="inline-flex items-center gap-2">
            <FiAlertCircle className="h-5 w-5" />
            {pageError}
          </span>
          <button
            type="button"
            onClick={() => void loadItems()}
            className="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-red-500 px-4 text-white"
          >
            <FiRefreshCw className="h-4 w-4" />
            Reintentar
          </button>
        </div>
      )}

      {notice && (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-[#BFE9CC] bg-[#EFFAF3] px-5 py-3 text-sm font-semibold text-[#0F8D34]">
          <span>{notice}</span>
          <button
            type="button"
            onClick={() => setNotice('')}
            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#0F8D34] hover:bg-white"
          >
            <FiX className="h-4 w-4" />
          </button>
        </div>
      )}

      <div className="grid grid-cols-1 gap-5 md:grid-cols-3">
        <div className="rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
          <div className="flex items-center gap-4">
            <span className="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#E8F0FF] text-primary">
              <FiTag className="h-6 w-6" />
            </span>
            <div>
              <p className="text-sm font-semibold text-slate-500">Registros</p>
              <p className="mt-1 text-title-sm font-black text-black dark:text-white">
                {loading ? '...' : items.length}
              </p>
            </div>
          </div>
        </div>
        <div className="rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
          <div className="flex items-center gap-4">
            <span className="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#E7F8ED] text-[#0F9F37]">
              <FiLayers className="h-6 w-6" />
            </span>
            <div>
              <p className="text-sm font-semibold text-slate-500">
                Con productos
              </p>
              <p className="mt-1 text-title-sm font-black text-black dark:text-white">
                {loading ? '...' : usedCount}
              </p>
            </div>
          </div>
        </div>
        <div className="rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark">
          <div className="flex items-center gap-4">
            <span className="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#FFF4DF] text-[#F59E0B]">
              <FiRefreshCw className="h-6 w-6" />
            </span>
            <div>
              <p className="text-sm font-semibold text-slate-500">
                Relacionados
              </p>
              <p className="mt-1 text-title-sm font-black text-black dark:text-white">
                {loading ? '...' : relatedCount || usedCount}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div className="rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark md:p-7">
        <div className="relative w-full md:max-w-md">
          <FiSearch className="absolute left-5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
          <input
            type="search"
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
            placeholder={config.searchPlaceholder}
            className="h-13 w-full rounded-lg border border-stroke bg-white pl-14 pr-4 text-base text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
          />
        </div>

        <div className="mt-7 overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
          <table className="w-full min-w-[760px] table-auto">
            <thead>
              <tr className="bg-gray-50 text-left dark:bg-meta-4">
                {config.hasParent && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Categoria
                  </th>
                )}
                {(catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Imagen
                  </th>
                )}
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Nombre
                </th>
                {(catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Descripción
                  </th>
                )}
                {config.hasShortName && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Abreviatura
                  </th>
                )}
                {config.hasCategory && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Categoría
                  </th>
                )}
                {config.hasLevel && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Nivel
                  </th>
                )}
                {config.hasDecimalPlaces && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Decimales
                  </th>
                )}
                {config.hasMargin && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Margen
                  </th>
                )}
                {catalog === 'categories' && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Subcategorias
                  </th>
                )}
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Estado
                </th>
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Productos
                </th>
                <th className="px-4 py-4 text-center text-xs font-black uppercase text-black dark:text-white">
                  Opciones
                </th>
              </tr>
            </thead>
            <tbody>
              {loading && (
                <tr>
                  <td
                    colSpan={tableColumns}
                    className="px-4 py-14 text-center text-sm font-semibold text-slate-500"
                  >
                    Cargando registros...
                  </td>
                </tr>
              )}

              {!loading &&
                filteredItems.map((item) => (
                  <tr
                    key={item.id}
                    className="border-t border-stroke bg-white dark:border-strokedark dark:bg-boxdark"
                  >
                    {config.hasParent && (
                      <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                        {item.parent_name || 'Sin categoria'}
                      </td>
                    )}
                    {(catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') && (
                      <td className="px-4 py-5">
                        {item.image_url ? (
                          <img
                            src={item.image_url}
                            alt={item.name}
                            className="h-12 w-12 rounded object-cover"
                          />
                        ) : (
                          <div className="flex h-12 w-12 items-center justify-center rounded bg-slate-100 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            IMG
                          </div>
                        )}
                      </td>
                    )}
                    <td className="px-4 py-5">
                      <p className="text-base font-black text-black dark:text-white">
                        {item.name}
                      </p>
                    </td>
                    {(catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') && (
                      <td className="px-4 py-5 text-sm text-slate-500 dark:text-slate-300">
                        {item.description
                          ? item.description.length > 80
                            ? `${item.description.slice(0, 80).trim()}...`
                            : item.description
                          : '—'}
                      </td>
                    )}
                    {config.hasShortName && (
                      <td className="px-4 py-5 text-sm font-black uppercase text-primary">
                        {item.short_name}
                      </td>
                    )}
                    {config.hasCategory && (
                      <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                        {item.category || '—'}
                      </td>
                    )}
                    {config.hasLevel && (
                      <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                        {readNumber(item.level)}
                      </td>
                    )}
                    {config.hasDecimalPlaces && (
                      <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                        {readNumber(item.decimal_places)}
                      </td>
                    )}
                    {config.hasMargin && (
                      <td className="px-4 py-5 text-sm font-black text-[#0F9F37]">
                        {readNumber(item.margin_percent)}%
                      </td>
                    )}
                    {catalog === 'categories' && (
                      <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                        {readNumber(item.subcategories_count)}
                      </td>
                    )}
                    <td className="px-4 py-5">
                      <label className="inline-flex cursor-pointer items-center">
                        <input
                          type="checkbox"
                          checked={Boolean(item.is_active ?? true)}
                          onChange={() => void handleStatusToggle(item, !Boolean(item.is_active ?? true))}
                          className="peer sr-only"
                        />
                        <span className="relative h-7 w-14 rounded-full bg-red-500 transition peer-checked:bg-green-500">
                          <span className="absolute left-1 top-1 h-5 w-5 rounded-full bg-white transition peer-checked:translate-x-7" />
                        </span>
                      </label>
                    </td>
                    <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                      {readNumber(item.products_count)}
                    </td>
                    <td className="px-4 py-5">
                      <div className="flex items-center justify-center gap-3">
                        <button
                          type="button"
                          onClick={() => openEditDialog(item)}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#0F9F37] text-[#0F9F37] transition hover:bg-[#0F9F37] hover:text-white"
                          aria-label={`Editar ${item.name}`}
                        >
                          <FiEdit2 className="h-4.5 w-4.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => void handleDelete(item)}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-500 text-red-500 transition hover:bg-red-500 hover:text-white"
                          aria-label={`Eliminar ${item.name}`}
                        >
                          <FiTrash2 className="h-4.5 w-4.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}

              {!loading && filteredItems.length === 0 && (
                <tr>
                  <td
                    colSpan={tableColumns}
                    className="px-4 py-14 text-center text-sm font-semibold text-slate-500"
                  >
                    No hay registros que coincidan con la busqueda.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form
            onSubmit={handleSubmit}
            className="max-h-full w-full max-w-2xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">
                  {editingItem ? `Editar ${config.singular}` : config.newLabel}
                </p>
                <p className="mt-1 text-sm text-slate-500">
                  Los cambios se aplican en la base de datos.
                </p>
              </div>
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black transition hover:border-red-500 hover:text-red-500 dark:border-strokedark dark:text-white"
              >
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {formError && (
              <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">
                {formError}
              </div>
            )}

            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
              {config.hasParent && (
                <Field label="Categoria">
                  <select
                    value={draft.parentId}
                    onChange={(event) => updateDraft('parentId', event.target.value)}
                    className={selectClass}
                  >
                    <option value="">Selecciona una categoria</option>
                    {categories.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.name}
                      </option>
                    ))}
                  </select>
                </Field>
              )}

              <Field label="Nombre">
                <input
                  value={draft.name}
                  onChange={(event) => updateDraft('name', event.target.value)}
                  className={inputClass}
                  placeholder={config.title}
                />
              </Field>

              {(catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') && config.hasImage && (
                <Field label="URL de imagen">
                  <input
                    value={draft.imageUrl}
                    onChange={(event) => updateDraft('imageUrl', event.target.value)}
                    className={inputClass}
                    placeholder="https://example.com/logo.png"
                  />
                </Field>
              )}

              {(catalog === 'brands' || catalog === 'categories' || catalog === 'subcategories') && config.hasDescription && (
                <div className="sm:col-span-2">
                  <Field label="Descripción">
                    <textarea
                      value={draft.description}
                      onChange={(event) => updateDraft('description', event.target.value)}
                      className={`${inputClass} min-h-[112px] resize-none py-3`}
                      placeholder={catalog === 'brands' ? 'Describe brevemente la marca' : 'Describe brevemente la categoría'}
                    />
                  </Field>
                </div>
              )}

              {config.hasShortName && (
                <Field label="Abreviatura">
                  <input
                    value={draft.shortName}
                    onChange={(event) =>
                      updateDraft('shortName', event.target.value)
                    }
                    className={inputClass}
                    placeholder="UND"
                  />
                </Field>
              )}

              {config.hasMargin && (
                <Field label="Margen">
                  <input
                    type="number"
                    min="0"
                    step="0.01"
                    value={draft.marginPercent}
                    onChange={(event) =>
                      updateDraft('marginPercent', event.target.value)
                    }
                    className={inputClass}
                  />
                </Field>
              )}

              <div className="sm:col-span-2">
                <SwitchField
                  label="Estado"
                  checked={draft.isActive}
                  onChange={() => updateDraft('isActive', !draft.isActive)}
                />
              </div>

              {config.hasLevel && (
                <Field label="Nivel">
                  <input
                    type="number"
                    min="1"
                    step="1"
                    value={draft.level}
                    onChange={(event) => updateDraft('level', event.target.value)}
                    className={inputClass}
                  />
                </Field>
              )}

              {config.hasCategory && (
                <Field label="Categoría">
                  <select
                    value={draft.category}
                    onChange={(event) => updateDraft('category', event.target.value)}
                    className={selectClass}
                  >
                    <option value="UNIDAD">Unidad</option>
                    <option value="PESO">Peso</option>
                    <option value="VOLUMEN">Volumen</option>
                    <option value="LONGITUD">Longitud</option>
                    <option value="OTRO">Otro</option>
                  </select>
                </Field>
              )}

              {config.hasDecimalPlaces && (
                <Field label="Decimales">
                  <input
                    type="number"
                    min="0"
                    max="6"
                    step="1"
                    value={draft.decimalPlaces}
                    onChange={(event) => updateDraft('decimalPlaces', event.target.value)}
                    className={inputClass}
                  />
                </Field>
              )}
            </div>

            <div className="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
              >
                Cancelar
              </button>
              <button
                type="submit"
                disabled={submitting}
                className="inline-flex h-12 items-center justify-center gap-3 rounded-lg bg-primary px-6 text-sm font-bold text-white transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
              >
                <FiSave className="h-5 w-5" />
                {submitting ? 'Guardando' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}

export default ProductCatalogs;
