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
  FiBriefcase,
  FiEdit2,
  FiHome,
  FiMapPin,
  FiPlus,
  FiRefreshCw,
  FiSave,
  FiSearch,
  FiTrash2,
  FiTruck,
  FiX,
} from 'react-icons/fi';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';
import { SwitchField } from '../components/SwitchField';

type RelationType = 'suppliers' | 'warehouses' | 'branches' | 'companies';

type RelationItem = {
  id: number;
  uuid?: string | null;
  code?: string | null;
  name: string;
  legal_name?: string | null;
  tax_id?: string | null;
  phone?: string | null;
  email?: string | null;
  website?: string | null;
  address?: string | null;
  logo?: string | null;
  contact_person?: string | null;
  contact_phone?: string | null;
  contact_email?: string | null;
  payment_term_id?: number | string | null;
  credit_limit?: number | string | null;
  credit_days?: number | string | null;
  notes?: string | null;
  payment_days?: number | string | null;
  status?: number | string | null;
  currency_id?: number | string | null;
  branch_id?: number | string | null;
  branch_name?: string | null;
  manager_name?: string | null;
  products_count?: number | string | null;
  purchases_count?: number | string | null;
  payable_balance?: number | string | null;
  warehouses_count?: number | string | null;
  branches_count?: number | string | null;
  suppliers_count?: number | string | null;
  users_count?: number | string | null;
  stock_units?: number | string | null;
};

type RelationResponse = {
  data: RelationItem[];
};

type RelationMutationResponse = {
  message?: string;
  item: RelationItem;
};

type RelationDeleteResponse = {
  message: string;
  deleted: boolean;
};

type RelationDraft = {
  code: string;
  name: string;
  legalName: string;
  taxId: string;
  phone: string;
  email: string;
  website: string;
  address: string;
  contactPerson: string;
  contactPhone: string;
  contactEmail: string;
  paymentTermId: string;
  bankName: string;
  bankAccount: string;
  creditLimit: string;
  creditDays: string;
  notes: string;
  status: boolean;
  currencyId: string;
  branchId: string;
  managerName: string;
};

type RelationConfig = {
  title: string;
  singular: string;
  subtitle: string;
  newLabel: string;
  searchPlaceholder: string;
  allowCreate?: boolean;
  allowDelete?: boolean;
  icon: ReactNode;
};

const configs: Record<RelationType, RelationConfig> = {
  suppliers: {
    title: 'Proveedores',
    singular: 'proveedor',
    subtitle: 'Contactos, credito y cuentas de abastecimiento',
    newLabel: 'Nuevo proveedor',
    searchPlaceholder: 'Buscar proveedores...',
    icon: <FiTruck className="h-6 w-6" />,
  },
  warehouses: {
    title: 'Almacenes',
    singular: 'almacen',
    subtitle: 'Bodegas asociadas a sucursales y stock',
    newLabel: 'Nuevo almacen',
    searchPlaceholder: 'Buscar almacenes...',
    icon: <FiMapPin className="h-6 w-6" />,
  },
  branches: {
    title: 'Tiendas / Sucursales',
    singular: 'sucursal',
    subtitle: 'Puntos de venta y operacion por empresa',
    newLabel: 'Nueva sucursal',
    searchPlaceholder: 'Buscar sucursales...',
    icon: <FiHome className="h-6 w-6" />,
  },
  companies: {
    title: 'Empresa',
    singular: 'empresa',
    subtitle: 'Datos legales y operativos de la empresa actual',
    newLabel: 'Editar empresa',
    searchPlaceholder: 'Buscar empresa...',
    allowCreate: false,
    allowDelete: false,
    icon: <FiBriefcase className="h-6 w-6" />,
  },
};

const emptyDraft: RelationDraft = {
  code: '',
  name: '',
  legalName: '',
  taxId: '',
  phone: '',
  email: '',
  website: '',
  address: '',
  contactPerson: '',
  contactPhone: '',
  contactEmail: '',
  paymentTermId: '',
  bankName: '',
  bankAccount: '',
  creditLimit: '',
  creditDays: '',
  notes: '',
  status: true,
  currencyId: '',
  branchId: '',
  managerName: '',
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
  return value
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function normalizeStatus(value: unknown): 'Activo' | 'Inactivo' {
  return value === 'Inactivo' || value === 0 || value === '0'
    ? 'Inactivo'
    : 'Activo';
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

function mapItem(item: RelationItem): RelationItem {
  return {
    ...item,
    id: Number(item.id),
    branch_id: item.branch_id ? Number(item.branch_id) : null,
    payment_days: readNumber(item.payment_days),
    products_count: readNumber(item.products_count),
    purchases_count: readNumber(item.purchases_count),
    payable_balance: readNumber(item.payable_balance),
    warehouses_count: readNumber(item.warehouses_count),
    branches_count: readNumber(item.branches_count),
    suppliers_count: readNumber(item.suppliers_count),
    users_count: readNumber(item.users_count),
    stock_units: readNumber(item.stock_units),
    status: item.status === undefined ? undefined : readNumber(item.status),
  };
}

function formatQuantity(value: number) {
  return value.toLocaleString('es-NI', {
    maximumFractionDigits: Number.isInteger(value) ? 0 : 2,
  });
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

function StatusBadge({ status }: { status?: number | string | null }) {
  const label = normalizeStatus(status ?? 1);

  return (
    <span
      className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-black uppercase ${
        label === 'Activo'
          ? 'bg-[#DFF6E7] text-[#0F9F37]'
          : 'bg-red-50 text-red-500'
      }`}
    >
      <span
        className={`h-2.5 w-2.5 rounded-full ${
          label === 'Activo' ? 'bg-[#03A323]' : 'bg-red-500'
        }`}
      />
      {label}
    </span>
  );
}

function draftFromItem(item: RelationItem): RelationDraft {
  return {
    code: item.code || '',
    name: item.name || '',
    legalName: item.legal_name || '',
    taxId: item.tax_id || '',
    phone: item.phone || '',
    email: item.email || '',
    website: item.website || '',
    address: item.address || '',
    contactPerson: item.contact_person || '',
    contactPhone: item.contact_phone || '',
    contactEmail: item.contact_email || '',
    paymentTermId: item.payment_term_id ? String(item.payment_term_id) : '',
    bankName: item.bank_name || '',
    bankAccount: item.bank_account || '',
    creditLimit: item.credit_limit ? String(item.credit_limit) : '',
    creditDays: item.credit_days ? String(item.credit_days) : '',
    notes: item.notes || '',
    status: (item.status === 1 || item.status === '1' || item.status === true || item.status === 'Activo'),
    currencyId: item.currency_id ? String(item.currency_id) : '',
    branchId: item.branch_id ? String(item.branch_id) : '',
    managerName: item.manager_name || '',
  };
}

function RelationCatalogs({ relation }: { relation: RelationType }) {
  const { token } = useAuth();
  const config = configs[relation];
  const canCreate = config.allowCreate !== false;
  const canDelete = config.allowDelete !== false;
  const [items, setItems] = useState<RelationItem[]>([]);
  const [branches, setBranches] = useState<RelationItem[]>([]);
  const [paymentTerms, setPaymentTerms] = useState<{ id: number; name: string; days: number | null }[]>([]);
  const [currencies, setCurrencies] = useState<{ id: number; name: string; code: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState('');
  const [notice, setNotice] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<RelationItem | null>(null);
  const [draft, setDraft] = useState<RelationDraft>(emptyDraft);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setBranches([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setPageError('');

    try {
      const [itemsResponse, branchesResponse, paymentTermsResponse, currenciesResponse] = await Promise.all([
        apiRequest<RelationResponse>(`/relations/${relation}`, {}, token),
        relation === 'warehouses'
          ? apiRequest<RelationResponse>('/relations/branches', {}, token)
          : Promise.resolve({ data: [] }),
        relation === 'suppliers'
          ? apiRequest<{ data: { id: number; name: string; days: number | null }[] }>('/relations/suppliers/payment-terms', {}, token)
          : Promise.resolve({ data: [] }),
        relation === 'companies'
          ? apiRequest<{ data: { id: number; name: string; code: string }[] }>('/settings/currencies', {}, token)
          : Promise.resolve({ data: [] }),
      ]);

      setItems(itemsResponse.data.map(mapItem));
      setBranches(branchesResponse.data.map(mapItem));
      setPaymentTerms(paymentTermsResponse.data);
      setCurrencies(currenciesResponse.data);
    } catch (error) {
      setPageError(getErrorMessage(error));
      setItems([]);
      setBranches([]);
      setCurrencies([]);
    } finally {
      setLoading(false);
    }
  }, [relation, token]);

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
        item.code || '',
        item.legal_name || '',
        item.tax_id || '',
        item.phone || '',
        item.email || '',
        item.address || '',
        item.branch_name || '',
        item.manager_name || '',
        item.bank_name || '',
        item.bank_account || '',
      ]
        .map(normalizeText)
        .some((value) => value.includes(search)),
    );
  }, [items, searchTerm]);

  const activeCount = items.filter(
    (item) => normalizeStatus(item.status ?? 1) === 'Activo',
  ).length;

  const relatedCount = items.reduce((total, item) => {
    if (relation === 'suppliers') {
      return total + readNumber(item.products_count);
    }

    if (relation === 'warehouses') {
      return total + readNumber(item.products_count);
    }

    if (relation === 'branches') {
      return total + readNumber(item.warehouses_count);
    }

    return total + readNumber(item.products_count);
  }, 0);

  const tableColumns =
    relation === 'suppliers'
      ? 7
      : relation === 'warehouses'
        ? 6
        : relation === 'branches'
          ? 6
          : 6;

  function updateDraft(field: keyof RelationDraft, value: string | boolean) {
    setDraft((currentDraft) => ({
      ...currentDraft,
      [field]: field === 'status' ? (typeof value === 'boolean' ? value : value === 'true') : value,
    }));
  }

  function openCreateDialog() {
    if (!canCreate) {
      const currentCompany = items[0];

      if (currentCompany) {
        openEditDialog(currentCompany);
      }

      return;
    }

    setDraft(emptyDraft);
    setEditingItem(null);
    setFormError('');
    setDialogOpen(true);
  }

  function openEditDialog(item: RelationItem) {
    setDraft(draftFromItem(item));
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
    if (relation === 'suppliers') {
      return {
        name: draft.name.trim(),
        tax_id: draft.taxId.trim(),
        phone: draft.phone.trim(),
        email: draft.email.trim(),
        website: draft.website.trim(),
        address: draft.address.trim(),
        contact_person: draft.contactPerson.trim(),
        contact_phone: draft.contactPhone.trim(),
        contact_email: draft.contactEmail.trim(),
        payment_term_id: draft.paymentTermId ? Number(draft.paymentTermId) : null,
        bank_name: draft.bankName.trim(),
        bank_account: draft.bankAccount.trim(),
        credit_limit: draft.creditLimit ? Number(draft.creditLimit) : null,
        credit_days: draft.creditDays ? Number(draft.creditDays) : null,
        notes: draft.notes.trim(),
        status: draft.status,
      };
    }

    if (relation === 'warehouses') {
      return {
        name: draft.name.trim(),
        branch_id: Number(draft.branchId) || null,
        address: draft.address.trim(),
        manager_name: draft.managerName.trim(),
      };
    }

    if (relation === 'branches') {
      return {
        name: draft.name.trim(),
        phone: draft.phone.trim(),
        address: draft.address.trim(),
        status: draft.status,
      };
    }

    return {
      name: draft.name.trim(),
      legal_name: draft.legalName.trim(),
      tax_id: draft.taxId.trim(),
      phone: draft.phone.trim(),
      email: draft.email.trim(),
      address: draft.address.trim(),
      currency_id: Number(draft.currencyId) || 1,
      status: draft.status,
    };
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!draft.name.trim()) {
      setFormError('Ingresa el nombre.');
      return;
    }

    if (relation === 'warehouses' && !draft.branchId) {
      setFormError('Selecciona la sucursal del almacen.');
      return;
    }

    if (!token) {
      setFormError('No autenticado.');
      return;
    }

    setSubmitting(true);
    setFormError('');

    try {
      const itemId = editingItem?.id || items[0]?.id;
      const response = await apiRequest<RelationMutationResponse>(
        editingItem
          ? `/relations/${relation}/${editingItem.id}`
          : `/relations/${relation}`,
        {
          method: editingItem ? 'PUT' : 'POST',
          body: JSON.stringify(relationPayload()),
        },
        token,
      );

      setNotice(response.message || 'Registro guardado correctamente.');
      closeDialog();

      if (relation === 'companies' && itemId) {
        setEditingItem(null);
      }

      await loadItems();
    } catch (error) {
      setFormError(getErrorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(item: RelationItem) {
    if (!token || !canDelete) {
      return;
    }

    const accepted = window.confirm(
      `Eliminar ${item.name}? Esta accion se aplicara en la base de datos.`,
    );

    if (!accepted) {
      return;
    }

    try {
      const response = await apiRequest<RelationDeleteResponse>(
        `/relations/${relation}/${item.id}`,
        { method: 'DELETE' },
        token,
      );
      setNotice(response.message);
      await loadItems();
    } catch (error) {
      setPageError(getErrorMessage(error));
    }
  }

  function renderActions(item: RelationItem) {
    return (
      <div className="flex items-center justify-center gap-3">
        <button
          type="button"
          onClick={() => openEditDialog(item)}
          className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#0F9F37] text-[#0F9F37] transition hover:bg-[#0F9F37] hover:text-white"
          aria-label={`Editar ${item.name}`}
        >
          <FiEdit2 className="h-4.5 w-4.5" />
        </button>
        {canDelete && (
          <button
            type="button"
            onClick={() => void handleDelete(item)}
            className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-500 text-red-500 transition hover:bg-red-500 hover:text-white"
            aria-label={`Eliminar ${item.name}`}
          >
            <FiTrash2 className="h-4.5 w-4.5" />
          </button>
        )}
      </div>
    );
  }

  const statCards = [
    {
      label: 'Registros',
      value: loading ? '...' : items.length.toString(),
      caption: config.title,
      icon: config.icon,
      iconClass: 'bg-[#E8F0FF] text-primary',
    },
    {
      label: 'Activos',
      value: loading ? '...' : activeCount.toString(),
      caption: 'Disponibles para operar',
      icon: <FiRefreshCw className="h-6 w-6" />,
      iconClass: 'bg-[#E7F8ED] text-[#0F9F37]',
    },
    {
      label: relation === 'branches' ? 'Almacenes' : 'Relacionados',
      value: loading ? '...' : relatedCount.toString(),
      caption:
        relation === 'suppliers'
          ? 'Productos con proveedor'
          : relation === 'warehouses'
            ? 'Productos en stock'
            : relation === 'branches'
              ? 'Bodegas por sucursal'
              : 'Productos de la empresa',
      icon: <FiBriefcase className="h-6 w-6" />,
      iconClass: 'bg-[#FFF4DF] text-[#F59E0B]',
    },
  ];

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-title-md2 font-black uppercase tracking-normal text-black dark:text-white">
            {config.title}
          </h1>
          <p className="mt-1 text-sm font-semibold text-slate-500">
            {config.subtitle}
          </p>
        </div>
        <button
          type="button"
          onClick={openCreateDialog}
          disabled={loading || (!canCreate && items.length === 0)}
          className="inline-flex h-12 items-center justify-center gap-3 rounded-lg bg-primary px-5 text-sm font-bold text-white shadow-4 transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {canCreate ? <FiPlus className="h-5 w-5" /> : <FiEdit2 className="h-5 w-5" />}
          {canCreate ? config.newLabel : 'Editar empresa'}
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
        {statCards.map((card) => (
          <div
            key={card.label}
            className="rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="flex items-center gap-4">
              <span
                className={`inline-flex h-12 w-12 items-center justify-center rounded-lg ${card.iconClass}`}
              >
                {card.icon}
              </span>
              <div className="min-w-0">
                <p className="text-sm font-semibold text-slate-500">
                  {card.label}
                </p>
                <p className="mt-1 truncate text-title-sm font-black text-black dark:text-white">
                  {card.value}
                </p>
                <p className="mt-1 text-sm font-medium text-slate-500">
                  {card.caption}
                </p>
              </div>
            </div>
          </div>
        ))}
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
          <table className="w-full min-w-[920px] table-auto">
            <thead>
              <tr className="bg-gray-50 text-left dark:bg-meta-4">
                {relation === 'suppliers' ? (
                  <>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Imagen
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Nombre
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Código
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Banco
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Contacto
                    </th>
                  </>
                ) : (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    {relation === 'companies' ? 'Empresa' : 'Nombre'}
                  </th>
                )}
                {relation === 'warehouses' && (
                  <>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Sucursal
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Encargado
                    </th>
                  </>
                )}
                {relation === 'branches' && (
                  <>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Contacto
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Almacenes
                    </th>
                  </>
                )}
                {relation === 'companies' && (
                  <>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Contacto
                    </th>
                    <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                      Estructura
                    </th>
                  </>
                )}
                {relation !== 'suppliers' && (
                  <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                    Productos
                  </th>
                )}
                <th className="px-4 py-4 text-xs font-black uppercase text-black dark:text-white">
                  Estado
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
                    {relation === 'suppliers' ? (
                      <>
                        <td className="px-4 py-5">
                          <div className="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-stroke bg-slate-100">
                            {item.image_url ? (
                              <img
                                src={item.image_url}
                                alt={item.name}
                                className="h-full w-full object-cover"
                              />
                            ) : (
                              <span className="text-xs font-semibold uppercase text-slate-500">
                                Sin imagen
                              </span>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-5">
                          <p className="text-base font-black text-black dark:text-white">
                            {item.name}
                          </p>
                          <p className="mt-1 text-sm font-semibold text-slate-500">
                            {item.tax_id || item.email || 'Sin info'}
                          </p>
                        </td>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          {item.code || 'Sin código'}
                        </td>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          <p>{item.bank_name || 'Sin banco'}</p>
                          <p className="mt-1 text-slate-500">
                            {item.bank_account || 'Sin cuenta'}
                          </p>
                        </td>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          <p>{item.contact_person || item.phone || 'Sin contacto'}</p>
                          <p className="mt-1 text-slate-500">
                            {item.contact_phone || item.email || 'Sin contacto'}</p>
                        </td>
                      </>
                    ) : (
                      <td className="px-4 py-5">
                        <p className="text-base font-black text-black dark:text-white">
                          {item.name}
                        </p>
                        <p className="mt-1 text-sm font-semibold text-slate-500">
                          {relation === 'companies'
                            ? item.legal_name || item.tax_id || 'Datos legales pendientes'
                            : item.address || 'Sin direccion'}
                        </p>
                      </td>
                    )}

                    {relation === 'warehouses' && (
                      <>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          {item.branch_name || 'Sin sucursal'}
                        </td>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          {item.manager_name || 'Sin encargado'}
                        </td>
                      </>
                    )}

                    {relation === 'branches' && (
                      <>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          <p>{item.phone || 'Sin telefono'}</p>
                          <p className="mt-1 text-slate-500">
                            {item.address || 'Sin direccion'}
                          </p>
                        </td>
                        <td className="px-4 py-5 text-sm font-black text-primary">
                          {readNumber(item.warehouses_count)}
                        </td>
                      </>
                    )}

                    {relation === 'companies' && (
                      <>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          <p>{item.phone || 'Sin telefono'}</p>
                          <p className="mt-1 text-slate-500">
                            {item.email || 'Sin correo'}
                          </p>
                        </td>
                        <td className="px-4 py-5 text-sm font-semibold text-black dark:text-white">
                          <p>{readNumber(item.branches_count)} sucursales</p>
                          <p className="mt-1 text-slate-500">
                            {readNumber(item.warehouses_count)} almacenes
                          </p>
                        </td>
                      </>
                    )}

                    <td className="px-4 py-5 text-sm font-black text-primary">
                      {relation === 'warehouses'
                        ? `${readNumber(item.products_count)} / ${formatQuantity(
                            readNumber(item.stock_units),
                          )}`
                        : readNumber(item.products_count)}
                    </td>
                    <td className="px-4 py-5">
                      <StatusBadge status={item.status} />
                    </td>
                    <td className="px-4 py-5">{renderActions(item)}</td>
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
            className="max-h-full w-full max-w-3xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">
                  {editingItem
                    ? `Editar ${config.singular}`
                    : config.newLabel}
                </p>
                <p className="mt-1 text-sm text-slate-500">
                  Los cambios se aplican directamente en la base de datos.
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

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <Field label={relation === 'companies' ? 'Nombre comercial' : 'Nombre'}>
                <input
                  value={draft.name}
                  onChange={(event) => updateDraft('name', event.target.value)}
                  className={inputClass}
                  placeholder={config.title}
                />
              </Field>

              {relation === 'companies' && (
                <Field label="Razon social">
                  <input
                    value={draft.legalName}
                    onChange={(event) =>
                      updateDraft('legalName', event.target.value)
                    }
                    className={inputClass}
                    placeholder="Auto Repuestos Bryan"
                  />
                </Field>
              )}

              {(relation === 'suppliers' || relation === 'companies') && (
                <Field label="Identificacion fiscal">
                  <input
                    value={draft.taxId}
                    onChange={(event) => updateDraft('taxId', event.target.value)}
                    className={inputClass}
                    placeholder="RUC"
                  />
                </Field>
              )}

              {relation === 'companies' && (
                <Field label="Moneda base">
                  <select
                    value={draft.currencyId}
                    onChange={(event) => updateDraft('currencyId', event.target.value)}
                    className={selectClass}
                  >
                    <option value="">Selecciona una moneda</option>
                    {currencies.map((currency) => (
                      <option key={currency.id} value={currency.id}>
                        {currency.name} ({currency.code})
                      </option>
                    ))}
                  </select>
                </Field>
              )}

              {(relation === 'suppliers' ||
                relation === 'branches' ||
                relation === 'companies') && (
                <Field label="Telefono">
                  <input
                    value={draft.phone}
                    onChange={(event) => updateDraft('phone', event.target.value)}
                    className={inputClass}
                    placeholder="8888-8888"
                  />
                </Field>
              )}

              {(relation === 'suppliers' || relation === 'companies') && (
                <Field label="Correo">
                  <input
                    type="email"
                    value={draft.email}
                    onChange={(event) => updateDraft('email', event.target.value)}
                    className={inputClass}
                    placeholder="correo@empresa.com"
                  />
                </Field>
              )}

              {relation === 'warehouses' && (
                <>
                  <Field label="Sucursal">
                    <select
                      value={draft.branchId}
                      onChange={(event) =>
                        updateDraft('branchId', event.target.value)
                      }
                      className={selectClass}
                    >
                      <option value="">Selecciona una sucursal</option>
                      {branches.map((branch) => (
                        <option key={branch.id} value={branch.id}>
                          {branch.name}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Encargado">
                    <input
                      value={draft.managerName}
                      onChange={(event) =>
                        updateDraft('managerName', event.target.value)
                      }
                      className={inputClass}
                      placeholder="Responsable del almacen"
                    />
                  </Field>
                </>
              )}

              {relation === 'suppliers' && (
                <>
                  <Field label="Sitio web">
                    <input
                      value={draft.website}
                      onChange={(event) =>
                        updateDraft('website', event.target.value)
                      }
                      className={inputClass}
                      placeholder="https://proveedor.com"
                    />
                  </Field>
                  <Field label="Persona de contacto">
                    <input
                      value={draft.contactPerson}
                      onChange={(event) =>
                        updateDraft('contactPerson', event.target.value)
                      }
                      className={inputClass}
                      placeholder="Nombre del contacto"
                    />
                  </Field>
                  <Field label="Telefono de contacto">
                    <input
                      value={draft.contactPhone}
                      onChange={(event) =>
                        updateDraft('contactPhone', event.target.value)
                      }
                      className={inputClass}
                      placeholder="8888-8888"
                    />
                  </Field>
                  <Field label="Correo de contacto">
                    <input
                      type="email"
                      value={draft.contactEmail}
                      onChange={(event) =>
                        updateDraft('contactEmail', event.target.value)
                      }
                      className={inputClass}
                      placeholder="contacto@proveedor.com"
                    />
                  </Field>
                  <Field label="Plazo de pago">
                    <select
                      value={draft.paymentTermId}
                      onChange={(event) =>
                        updateDraft('paymentTermId', event.target.value)
                      }
                      className={selectClass}
                    >
                      <option value="">Selecciona un plazo</option>
                      {paymentTerms.map((term) => (
                        <option key={term.id} value={term.id}>
                          {term.name}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Limite de credito">
                    <input
                      type="number"
                      step="0.01"
                      min="0"
                      value={draft.creditLimit}
                      onChange={(event) =>
                        updateDraft('creditLimit', event.target.value)
                      }
                      className={inputClass}
                      placeholder="0.00"
                    />
                  </Field>
                  <Field label="Dias de credito">
                    <input
                      type="number"
                      min="0"
                      value={draft.creditDays}
                      onChange={(event) =>
                        updateDraft('creditDays', event.target.value)
                      }
                      className={inputClass}
                      placeholder="0"
                    />
                  </Field>
                  <Field label="Notas">
                    <textarea
                      value={draft.notes}
                      onChange={(event) =>
                        updateDraft('notes', event.target.value)
                      }
                      className={textareaClass}
                      placeholder="Comentarios adicionales"
                    />
                  </Field>
                </>
              )}

              {(relation === 'suppliers' ||
                relation === 'branches' ||
                relation === 'companies') && (
                <SwitchField
                  label="Estado"
                  checked={draft.status}
                  onChange={(checked) => updateDraft('status', checked)}
                />
              )}

              <div className="md:col-span-2">
                <Field label="Direccion">
                  <textarea
                    value={draft.address}
                    onChange={(event) =>
                      updateDraft('address', event.target.value)
                    }
                    className={textareaClass}
                    placeholder="Direccion fisica"
                  />
                </Field>
              </div>
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

export default RelationCatalogs;
