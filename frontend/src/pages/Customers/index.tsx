import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { FiEdit2, FiFileText, FiPlus, FiSearch, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';
import {
  Customer,
  CustomerDeleteResponse,
  CustomerListResponse,
  CustomerSaveResponse,
} from '../../types/customer';

type PaymentTerm = {
  id: number;
  name: string;
};

type FormState = {
  full_name: string;
  business_name: string;
  tax_id: string;
  phone: string;
  email: string;
  address: string;
  contact_person: string;
  payment_term_id: string;
  credit_limit: string;
  credit_days: string;
  discount_rate: string;
  notes: string;
  status: boolean;
};

type FormErrors = Partial<Record<keyof FormState, string>>;

const emptyForm: FormState = {
  full_name: '',
  business_name: '',
  tax_id: '',
  phone: '',
  email: '',
  address: '',
  contact_person: '',
  payment_term_id: '',
  credit_limit: '0',
  credit_days: '',
  discount_rate: '0',
  notes: '',
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

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
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

export default function CustomersPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<Customer[]>([]);
  const [paymentTerms, setPaymentTerms] = useState<PaymentTerm[]>([]);
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

  const loadPaymentTerms = useCallback(async () => {
    if (!token) return;

    try {
      const response = await apiRequest<{ data: PaymentTerm[] }>('/settings/payment-terms', {}, token);
      setPaymentTerms(response.data);
    } catch {
      setPaymentTerms([]);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
    void loadPaymentTerms();
  }, [loadItems, loadPaymentTerms]);

  function updateForm(field: keyof FormState, value: string | boolean) {
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

    if (state.credit_limit.trim() && Number.isNaN(Number(state.credit_limit))) {
      validation.credit_limit = 'El limite de credito debe ser numerico.';
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
      phone: item.phone ?? '',
      email: item.email ?? '',
      address: item.address ?? '',
      contact_person: item.contact_person ?? '',
      payment_term_id: item.payment_term_id ? String(item.payment_term_id) : '',
      credit_limit: String(item.credit_limit ?? 0),
      credit_days: item.credit_days !== null ? String(item.credit_days) : '',
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
            phone: form.phone.trim() || null,
            email: form.email.trim() || null,
            address: form.address.trim() || null,
            contact_person: form.contact_person.trim() || null,
            payment_term_id: form.payment_term_id ? Number(form.payment_term_id) : null,
            credit_limit: form.credit_limit ? Number(form.credit_limit) : 0,
            credit_days: form.credit_days ? Number(form.credit_days) : null,
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
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.code}</td>
                  <td className="px-3 py-3">
                    <div className="font-medium text-black dark:text-white">{item.full_name}</div>
                    {item.business_name && <div className="text-xs text-slate-500">{item.business_name}</div>}
                  </td>
                  <td className="px-3 py-3">
                    <div>{item.phone ?? '-'}</div>
                    <div className="text-xs text-slate-500">{item.email ?? ''}</div>
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
                    <div className="flex justify-end gap-2">
                      <Link
                        to={`/sales/new?customer_id=${item.id}`}
                        title="Nueva factura para este cliente"
                        className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-primary text-primary hover:bg-primary hover:text-white"
                      >
                        <FiFileText />
                      </Link>
                      <button
                        type="button"
                        onClick={() => openEdit(item)}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#0F9F37] text-[#0F9F37] hover:bg-[#0F9F37] hover:text-white"
                      >
                        <FiEdit2 />
                      </button>
                      <button
                        type="button"
                        onClick={() => void handleDelete(item)}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                      >
                        <FiTrash2 />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-3 py-8 text-center text-sm text-slate-500">
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
            className="max-h-full w-full max-w-3xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <div>
                <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar cliente' : 'Nuevo cliente'}</p>
                <p className="mt-1 text-sm text-slate-500">Datos generales, fiscales y condiciones de credito.</p>
              </div>
              <button
                type="button"
                onClick={closeDialog}
                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500"
              >
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              <Field label="Nombre completo" error={formErrors.full_name}>
                <input value={form.full_name} onChange={(event) => updateForm('full_name', event.target.value)} className={inputClass} placeholder="Juan Perez" />
              </Field>
              <Field label="Razon social / empresa" error={formErrors.business_name}>
                <input value={form.business_name} onChange={(event) => updateForm('business_name', event.target.value)} className={inputClass} placeholder="Opcional" />
              </Field>
              <Field label="RUC / Cedula" error={formErrors.tax_id}>
                <input value={form.tax_id} onChange={(event) => updateForm('tax_id', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Telefono" error={formErrors.phone}>
                <input value={form.phone} onChange={(event) => updateForm('phone', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Correo" error={formErrors.email}>
                <input value={form.email} onChange={(event) => updateForm('email', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Persona de contacto" error={formErrors.contact_person}>
                <input value={form.contact_person} onChange={(event) => updateForm('contact_person', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Direccion" error={formErrors.address}>
                <textarea value={form.address} onChange={(event) => updateForm('address', event.target.value)} className="min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white" />
              </Field>
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
              <Field label="Descuento (%)" error={formErrors.discount_rate}>
                <input type="number" min="0" max="100" step="0.01" value={form.discount_rate} onChange={(event) => updateForm('discount_rate', event.target.value)} className={inputClass} />
              </Field>
              <Field label="Estado">
                <SwitchField checked={form.status} onChange={(checked) => updateForm('status', checked)} label="Estado" />
              </Field>
              <Field label="Notas" error={formErrors.notes}>
                <textarea value={form.notes} onChange={(event) => updateForm('notes', event.target.value)} className="min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white" />
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
