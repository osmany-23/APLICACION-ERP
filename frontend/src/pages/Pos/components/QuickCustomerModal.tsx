import { FormEvent, useState } from 'react';
import { FiUserPlus, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../../../services/api';
import { Customer, CustomerSaveResponse } from '../../../types/customer';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

const inputClass =
  'h-11 w-full rounded-lg border border-stroke bg-white px-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

export default function QuickCustomerModal({
  token,
  onClose,
  onCreated,
}: {
  token: string;
  onClose: () => void;
  onCreated: (customer: Customer) => void;
}) {
  const [fullName, setFullName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!fullName.trim()) {
      setError('Ingresa el nombre del cliente.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<CustomerSaveResponse>(
        '/customers',
        {
          method: 'POST',
          body: JSON.stringify({
            full_name: fullName.trim(),
            phone: phone.trim() || undefined,
            email: email.trim() || undefined,
            address: address.trim() || undefined,
            sales_type: 'CONTADO',
            residency_type: 'NACIONAL',
            status: 1,
          }),
        },
        token,
      );

      onCreated(response.item);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[100020] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <form
        onSubmit={handleSubmit}
        className="w-full max-w-lg rounded-2xl border border-stroke bg-white p-7 shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="mb-5 flex items-center justify-between">
          <span className="flex items-center gap-2 text-lg font-black text-black dark:text-white"><FiUserPlus className="text-primary" /> Cliente rapido</span>
          <button type="button" onClick={onClose} className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500 dark:border-strokedark dark:text-white">
            <FiX />
          </button>
        </div>

        {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">{error}</div>}

        <div className="space-y-4">
          <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-black dark:text-white">Nombre completo</span>
            <input value={fullName} onChange={(event) => setFullName(event.target.value)} className={inputClass} placeholder="Juan Perez" autoFocus />
          </label>
          <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-black dark:text-white">Telefono</span>
            <input value={phone} onChange={(event) => setPhone(event.target.value)} className={inputClass} placeholder="8888-8888" />
          </label>
          <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-black dark:text-white">Correo</span>
            <input value={email} onChange={(event) => setEmail(event.target.value)} className={inputClass} placeholder="Opcional" />
          </label>
          <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-black dark:text-white">Direccion</span>
            <input value={address} onChange={(event) => setAddress(event.target.value)} className={inputClass} placeholder="Opcional" />
          </label>
        </div>

        <button
          type="submit"
          disabled={submitting}
          className="mt-6 inline-flex h-11 w-full items-center justify-center rounded-lg bg-primary text-sm font-bold text-white disabled:opacity-60"
        >
          {submitting ? 'Guardando...' : 'Guardar y seleccionar'}
        </button>
      </form>
    </div>
  );
}
