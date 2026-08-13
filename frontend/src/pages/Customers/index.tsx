import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiEdit2, FiFileText, FiPlus, FiSearch, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';
import ActionsMenu from '../../components/ActionsMenu';
import {
  Customer,
  CustomerDeleteResponse,
  CustomerListResponse,
  CustomerSaveResponse,
} from '../../types/customer';
import { Country, CountryListResponse } from '../../types/country';

type PaymentTerm = {
  id: number;
  name: string;
};

type FormState = {
  full_name: string;
  business_name: string;
  tax_id: string;
  residency_type: string;
  gender: string;
  sales_type: string;
  phone: string;
  phone_country_id: string;
  has_landline: boolean;
  landline_phone: string;
  email: string;
  address: string;
  country_id: string;
  city: string;
  contact_person: string;
  payment_term_id: string;
  credit_limit: string;
  credit_days: string;
  applies_late_fee: boolean;
  late_fee_percentage: string;
  late_fee_period_unit: string;
  discount_rate: string;
  notes: string;
  status: boolean;
};

type FormErrors = Partial<Record<keyof FormState, string>>;

// Solo numeros, espacios y guiones (ej. "8888-8888"), igual que valida el
// backend — se revisa aqui tambien para dar feedback antes del submit.
const PHONE_PATTERN = /^[0-9]{1,4}([-\s][0-9]{1,4})*$/;

const emptyForm: FormState = {
  full_name: '',
  business_name: '',
  tax_id: '',
  residency_type: 'NACIONAL',
  gender: '',
  sales_type: 'CREDITO',
  phone: '',
  phone_country_id: '',
  has_landline: false,
  landline_phone: '',
  email: '',
  address: '',
  country_id: '',
  city: '',
  contact_person: '',
  payment_term_id: '',
  credit_limit: '0',
  credit_days: '',
  applies_late_fee: false,
  late_fee_percentage: '',
  late_fee_period_unit: '',
  discount_rate: '0',
  notes: '',
  status: true,
};

const GENDER_LABELS: Record<string, string> = {
  FEMENINO: 'Femenino',
  MASCULINO: 'Masculino',
  EMPRESA: 'Empresa',
  OTRO: 'Otro',
};

const LATE_FEE_PERIOD_LABELS: Record<string, string> = {
  DAYS: 'dia',
  WEEKS: 'semana',
  MONTHS: 'mes',
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

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function formatDate(value: string | null) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium' }).format(new Date(value));
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

function SectionTitle({ children }: { children: React.ReactNode }) {
  return (
    <h3 className="col-span-full mt-3 border-b border-stroke pb-2 text-xs font-black uppercase tracking-wide text-primary first:mt-0 dark:border-strokedark">
      {children}
    </h3>
  );
}

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const textareaClass =
  'min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

export default function CustomersPage() {
  const { token } = useAuth();
  const navigate = useNavigate();
  const [items, setItems] = useState<Customer[]>([]);
  const [paymentTerms, setPaymentTerms] = useState<PaymentTerm[]>([]);
  const [countries, setCountries] = useState<Country[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);
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
      const response = await apiRequest<CustomerListResponse>('/customers', {}, token);
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
      const [termsRes, countriesRes] = await Promise.all([
        apiRequest<{ data: PaymentTerm[] }>('/settings/payment-terms', {}, token),
        apiRequest<CountryListResponse>('/countries', {}, token),
      ]);
      setPaymentTerms(termsRes.data);
      setCountries(countriesRes.data);
    } catch {
      setPaymentTerms([]);
      setCountries([]);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadCatalogs();
  }, [loadItems, loadCatalogs]);

  function updateForm<T extends keyof FormState>(field: T, value: FormState[T]) {
    setForm((current) => ({ ...current, [field]: value }));
    setFormErrors((current) => ({ ...current, [field]: undefined }));
  }

  function validateForm(state: FormState): FormErrors {
    const validation: FormErrors = {};

    if (!state.full_name.trim()) {
      validation.full_name = 'El nombre del cliente es obligatorio.';
    }

    if (state.email.trim() && !/^\S+@\S+\.\S+$/.test(state.email.trim())) {
      validation.email = 'Ingresa un correo valido.';
    }

    if (state.phone.trim() && !PHONE_PATTERN.test(state.phone.trim())) {
      validation.phone = 'Solo numeros, espacios y guiones (ej. 8888-8888).';
    }

    if (state.has_landline && !state.landline_phone.trim()) {
      validation.landline_phone = 'Ingresa el numero de telefono convencional.';
    } else if (state.landline_phone.trim() && !PHONE_PATTERN.test(state.landline_phone.trim())) {
      validation.landline_phone = 'Solo numeros, espacios y guiones (ej. 2222-3333).';
    }

    if (state.credit_limit.trim() && Number.isNaN(Number(state.credit_limit))) {
      validation.credit_limit = 'El limite de credito debe ser numerico.';
    }

    if (state.applies_late_fee) {
      if (!state.late_fee_percentage.trim() || Number.isNaN(Number(state.late_fee_percentage))) {
        validation.late_fee_percentage = 'Ingresa el porcentaje de mora.';
      }
      if (!state.late_fee_period_unit) {
        validation.late_fee_period_unit = 'Selecciona el periodo (dias, semanas o meses).';
      }
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

  function openEdit(item: Customer) {
    setForm({
      full_name: item.full_name,
      business_name: item.business_name ?? '',
      tax_id: item.tax_id ?? '',
      residency_type: item.residency_type,
      gender: item.gender ?? '',
      sales_type: item.sales_type,
      phone: item.phone ?? '',
      phone_country_id: item.phone_country_id ? String(item.phone_country_id) : '',
      has_landline: item.has_landline,
      landline_phone: item.landline_phone ?? '',
      email: item.email ?? '',
      address: item.address ?? '',
      country_id: item.country_id ? String(item.country_id) : '',
      city: item.city ?? '',
      contact_person: item.contact_person ?? '',
      payment_term_id: item.payment_term_id ? String(item.payment_term_id) : '',
      credit_limit: String(item.credit_limit ?? 0),
      credit_days: item.credit_days !== null ? String(item.credit_days) : '',
      applies_late_fee: item.applies_late_fee,
      late_fee_percentage: item.late_fee_percentage !== null ? String(item.late_fee_percentage) : '',
      late_fee_period_unit: item.late_fee_period_unit ?? '',
      discount_rate: String(item.discount_rate ?? 0),
      notes: item.notes ?? '',
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
      const response = await apiRequest<CustomerSaveResponse>(
        editing ? `/customers/${editing.id}` : '/customers',
        {
          method: editing ? 'PUT' : 'POST',
          body: JSON.stringify({
            full_name: form.full_name.trim(),
            business_name: form.business_name.trim() || null,
            tax_id: form.tax_id.trim() || null,
            residency_type: form.residency_type,
            gender: form.gender || null,
            sales_type: form.sales_type,
            phone: form.phone.trim() || null,
            phone_country_id: form.phone_country_id ? Number(form.phone_country_id) : null,
            has_landline: form.has_landline,
            landline_phone: form.has_landline ? form.landline_phone.trim() || null : null,
            email: form.email.trim() || null,
            address: form.address.trim() || null,
            country_id: form.country_id ? Number(form.country_id) : null,
            city: form.city.trim() || null,
            contact_person: form.contact_person.trim() || null,
            payment_term_id: form.payment_term_id ? Number(form.payment_term_id) : null,
            credit_limit: form.credit_limit ? Number(form.credit_limit) : 0,
            credit_days: form.credit_days ? Number(form.credit_days) : null,
            applies_late_fee: form.applies_late_fee,
            late_fee_percentage: form.applies_late_fee && form.late_fee_percentage ? Number(form.late_fee_percentage) : null,
            late_fee_period_unit: form.applies_late_fee ? form.late_fee_period_unit || null : null,
            discount_rate: form.discount_rate ? Number(form.discount_rate) : 0,
            notes: form.notes.trim() || null,
            status: form.status,
          }),
        },
        token,
      );

      setNotice(response.message || 'Cliente guardado correctamente.');
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

  async function handleDelete(item: Customer) {
    if (!token) return;

    const confirmed = window.confirm(`¿Eliminar al cliente ${item.full_name}?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<CustomerDeleteResponse>(`/customers/${item.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (deleteError) {
      setError(getErrorMessage(deleteError));
    }
  }

  async function handleToggleStatus(item: Customer) {
    if (!token) return;

    try {
      await apiRequest(
        `/customers/${item.id}/status`,
        { method: 'PATCH', body: JSON.stringify({ status: item.status !== 1 }) },
        token,
      );
      await loadItems();
    } catch (toggleError) {
      setError(getErrorMessage(toggleError));
    }
  }

  const filteredItems = useMemo(() => {
    const normalized = normalizeText(search);
    return items.filter((item) =>
      normalizeText(`${item.code} ${item.full_name} ${item.business_name ?? ''} ${item.tax_id ?? ''}`).includes(
        normalized,
      ),
    );
  }, [items, search]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Clientes</h2>
          <p className="mt-1 text-sm text-slate-500">Administra tu cartera de clientes y su credito disponible.</p>
        </div>
        <button
          type="button"
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
          onClick={openCreate}
        >
          <FiPlus className="h-4 w-4" /> Nuevo cliente
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
            placeholder="Buscar por nombre, codigo o RUC..."
          />
        </label>
        <div className="text-sm text-slate-500">{filteredItems.length} clientes</div>
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
                <th className="px-3 py-3">Tipo</th>
                <th className="px-3 py-3">Contacto</th>
                <th className="px-3 py-3 text-right">Limite de credito</th>
                <th className="px-3 py-3 text-right">Saldo actual</th>
                <th className="px-3 py-3 text-right">Credito disponible</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3">
                    <div className="font-semibold text-black dark:text-white">{item.code}</div>
                    <div className="text-xs text-slate-500">Registrado: {formatDate(item.registered_at)}</div>
                  </td>
                  <td className="px-3 py-3">
                    <div className="font-medium text-black dark:text-white">{item.full_name}</div>
                    {item.business_name && <div className="text-xs text-slate-500">{item.business_name}</div>}
                    {item.gender && <div className="text-xs text-slate-500">{GENDER_LABELS[item.gender]}</div>}
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex flex-col gap-1">
                      <span
                        className={`w-fit rounded-full px-2 py-0.5 text-xs font-semibold ${
                          item.residency_type === 'EXTRANJERO'
                            ? 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'
                            : 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'
                        }`}
                      >
                        {item.residency_type === 'EXTRANJERO' ? 'Extranjero' : 'Nacional'}
                      </span>
                      <span
                        className={`w-fit rounded-full px-2 py-0.5 text-xs font-semibold ${
                          item.sales_type === 'CONTADO'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                        }`}
                      >
                        {item.sales_type === 'CONTADO' ? 'Contado' : 'Credito'}
                      </span>
                    </div>
                  </td>
                  <td className="px-3 py-3">
                    <div>{item.phone_display ?? item.phone ?? '-'}</div>
                    {item.has_landline && item.landline_phone && (
                      <div className="text-xs text-slate-500">Conv: {item.landline_phone}</div>
                    )}
                    <div className="text-xs text-slate-500">{item.email ?? ''}</div>
                    {item.applies_late_fee && item.late_fee_percentage !== null && (
                      <div className="mt-1 text-xs font-semibold text-red-500">
                        Mora {item.late_fee_percentage}% / {LATE_FEE_PERIOD_LABELS[item.late_fee_period_unit ?? ''] ?? ''}
                      </div>
                    )}
                  </td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.credit_limit)}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.current_balance)}</td>
                  <td className="px-3 py-3 text-right font-semibold text-green-600">
                    {formatCurrency(item.credit_available)}
                  </td>
                  <td className="px-3 py-3">
                    <SwitchField checked={item.status === 1} onChange={() => void handleToggleStatus(item)} label="Estado" />
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de ${item.full_name}`}
                        items={[
                          {
                            label: 'Nueva factura',
                            icon: FiFileText,
                            onClick: () => navigate(`/sales/new?customer_id=${item.id}`),
                          },
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
                  <td colSpan={9} className="px-3 py-8 text-center text-sm text-slate-500">
                    No hay clientes registrados todavia.
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
            className="max-h-full w-full max-w-6xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar cliente' : 'Nuevo cliente'}</p>
                <p className="mt-1 text-sm text-slate-500">
                  {editing
                    ? `Registrado el ${formatDate(editing.registered_at)}`
                    : 'La fecha de registro se guarda automaticamente al crear el cliente.'}
                </p>
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
              <SectionTitle>Datos generales</SectionTitle>
              <Field label="Nombre completo" error={formErrors.full_name}>
                <input value={form.full_name} onChange={(event) => updateForm('full_name', event.target.value)} className={inputClass} placeholder="Juan Perez" />
              </Field>
              <Field label="Razon social / empresa" error={formErrors.business_name}>
                <input value={form.business_name} onChange={(event) => updateForm('business_name', event.target.value)} className={inputClass} placeholder="Opcional" />
              </Field>
              <Field label="RUC / Cedula" error={formErrors.tax_id}>
                <input value={form.tax_id} onChange={(event) => updateForm('tax_id', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Genero">
                <select value={form.gender} onChange={(event) => updateForm('gender', event.target.value)} className={inputClass}>
                  <option value="">Sin especificar</option>
                  <option value="FEMENINO">Femenino</option>
                  <option value="MASCULINO">Masculino</option>
                  <option value="EMPRESA">Empresa</option>
                  <option value="OTRO">Otro</option>
                </select>
              </Field>
              <Field label="Tipo de cliente">
                <select value={form.residency_type} onChange={(event) => updateForm('residency_type', event.target.value)} className={inputClass}>
                  <option value="NACIONAL">Nacional</option>
                  <option value="EXTRANJERO">Extranjero</option>
                </select>
              </Field>
              <SectionTitle>Contacto</SectionTitle>
              <Field label="Telefono" error={formErrors.phone}>
                <div className="flex gap-2">
                  <select
                    value={form.phone_country_id}
                    onChange={(event) => updateForm('phone_country_id', event.target.value)}
                    className="h-12 w-32 shrink-0 rounded-lg border border-stroke bg-white px-2 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                  >
                    <option value="" disabled hidden>Prefijo</option>
                    {countries.map((country) => (
                      <option key={country.id} value={country.id}>
                        {country.phone_code} {country.iso2}
                      </option>
                    ))}
                  </select>
                  <input
                    value={form.phone}
                    onChange={(event) => updateForm('phone', event.target.value)}
                    className={`${inputClass} flex-1`}
                    placeholder="8888-8888"
                  />
                </div>
              </Field>
              <Field label="Correo" error={formErrors.email}>
                <input value={form.email} onChange={(event) => updateForm('email', event.target.value)} className={inputClass} />
              </Field>
              <label className="col-span-full -mb-2 flex items-center gap-2 text-sm font-semibold text-black dark:text-white">
                <input
                  type="checkbox"
                  checked={form.has_landline}
                  onChange={(event) => updateForm('has_landline', event.target.checked)}
                  className="h-4 w-4 rounded border-stroke text-primary focus:ring-primary"
                />
                Tiene telefono convencional
              </label>
              {form.has_landline && (
                <Field label="Telefono convencional" error={formErrors.landline_phone}>
                  <input
                    value={form.landline_phone}
                    onChange={(event) => updateForm('landline_phone', event.target.value)}
                    className={inputClass}
                    placeholder="2222-3333 (sin prefijo)"
                  />
                </Field>
              )}
              <Field label="Persona de contacto" error={formErrors.contact_person}>
                <input value={form.contact_person} onChange={(event) => updateForm('contact_person', event.target.value)} className={inputClass} />
              </Field>

              <SectionTitle>Ubicacion</SectionTitle>
              <Field label="Pais">
                <select value={form.country_id} onChange={(event) => updateForm('country_id', event.target.value)} className={inputClass}>
                  <option value="">Sin especificar</option>
                  {countries.map((country) => (
                    <option key={country.id} value={country.id}>
                      {country.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Ciudad">
                <input value={form.city} onChange={(event) => updateForm('city', event.target.value)} className={inputClass} />
              </Field>
              <div className="col-span-full">
                <Field label="Direccion" error={formErrors.address}>
                  <textarea value={form.address} onChange={(event) => updateForm('address', event.target.value)} className={textareaClass} />
                </Field>
              </div>

              <SectionTitle>Condiciones comerciales</SectionTitle>
              <Field label="Tipo de venta">
                <select value={form.sales_type} onChange={(event) => updateForm('sales_type', event.target.value)} className={inputClass}>
                  <option value="CREDITO">Credito</option>
                  <option value="CONTADO">Contado</option>
                </select>
              </Field>
              <Field label="Descuento (%)" error={formErrors.discount_rate}>
                <input type="number" min="0" max="100" step="0.01" value={form.discount_rate} onChange={(event) => updateForm('discount_rate', event.target.value)} className={inputClass} />
              </Field>
              {form.sales_type === 'CREDITO' && (
                <>
                  <Field label="Condicion de pago" error={formErrors.payment_term_id}>
                    <select value={form.payment_term_id} onChange={(event) => updateForm('payment_term_id', event.target.value)} className={inputClass}>
                      <option value="">Sin condicion definida</option>
                      {paymentTerms.map((term) => (
                        <option key={term.id} value={term.id}>
                          {term.name}
                        </option>
                      ))}
                    </select>
                  </Field>
                  <Field label="Limite de credito" error={formErrors.credit_limit}>
                    <input type="number" min="0" step="0.01" value={form.credit_limit} onChange={(event) => updateForm('credit_limit', event.target.value)} className={inputClass} />
                  </Field>
                  <Field label="Dias de credito" error={formErrors.credit_days}>
                    <input type="number" min="0" value={form.credit_days} onChange={(event) => updateForm('credit_days', event.target.value)} className={inputClass} />
                  </Field>
                </>
              )}

              <div className="col-span-full flex items-center justify-between gap-4 rounded-lg border border-stroke p-4 dark:border-strokedark">
                <div>
                  <p className="text-sm font-semibold text-black dark:text-white">Aplica mora por atraso</p>
                  <p className="text-xs text-slate-500">
                    Si se activa, el saldo de este cliente se incrementa automaticamente cuando una factura vence
                    (ver "sales:apply-late-fees").
                  </p>
                </div>
                <SwitchField
                  checked={form.applies_late_fee}
                  onChange={(checked) => updateForm('applies_late_fee', checked)}
                  label="Aplica mora"
                />
              </div>
              {form.applies_late_fee && (
                <>
                  <Field label="Porcentaje de mora (%)" error={formErrors.late_fee_percentage}>
                    <input
                      type="number"
                      min="0"
                      max="100"
                      step="0.01"
                      value={form.late_fee_percentage}
                      onChange={(event) => updateForm('late_fee_percentage', event.target.value)}
                      className={inputClass}
                      placeholder="Ej. 2.00"
                    />
                  </Field>
                  <Field label="Periodo de mora" error={formErrors.late_fee_period_unit}>
                    <select
                      value={form.late_fee_period_unit}
                      onChange={(event) => updateForm('late_fee_period_unit', event.target.value)}
                      className={inputClass}
                    >
                      <option value="" disabled hidden>Selecciona...</option>
                      <option value="DAYS">Por dia</option>
                      <option value="WEEKS">Por semana</option>
                      <option value="MONTHS">Por mes</option>
                    </select>
                  </Field>
                </>
              )}

              <SectionTitle>Otros</SectionTitle>
              <Field label="Estado">
                <SwitchField checked={form.status} onChange={(checked) => updateForm('status', checked)} label="Estado" />
              </Field>
              <div className="col-span-full">
                <Field label="Notas" error={formErrors.notes}>
                  <textarea value={form.notes} onChange={(event) => updateForm('notes', event.target.value)} className={textareaClass} />
                </Field>
              </div>
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
