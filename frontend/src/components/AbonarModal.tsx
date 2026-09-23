import { useEffect, useState } from 'react';
import { FiCheckCircle, FiCreditCard, FiDollarSign, FiLayers, FiRefreshCw, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../services/api';
import { Sale, SaleSaveResponse } from '../types/sale';

type PaymentMethodOption = {
  id: number;
  name: string;
  cash: boolean;
  card: boolean;
  bank: boolean;
  is_active: boolean;
};

function methodIcon(method: PaymentMethodOption) {
  if (method.cash) return FiDollarSign;
  if (method.card) return FiCreditCard;
  if (method.bank) return FiRefreshCw;
  return FiLayers;
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

/**
 * Registra un abono (parcial o por el saldo completo) sobre una venta
 * "Pendiente de pago" (contra entrega). Reemplaza al viejo "Confirmar pago
 * recibido": abonar el saldo completo en un solo abono logra exactamente
 * lo mismo, pero ahora tambien se puede abonar de a partes — la factura
 * queda con saldo pendiente hasta completarse (ver
 * SalesService::registerPendingPayment en el backend). Si el abono es en
 * efectivo y hay una caja abierta, ademas afecta el corte de caja del dia.
 */
export default function AbonarModal({
  sale,
  token,
  onClose,
  onRegistered,
}: {
  sale: Sale;
  token: string;
  onClose: () => void;
  onRegistered: (message: string) => void;
}) {
  const balanceDue = Number(sale.balance_due) || 0;
  const [methods, setMethods] = useState<PaymentMethodOption[]>([]);
  const [loadingMethods, setLoadingMethods] = useState(true);
  const [paymentMethodId, setPaymentMethodId] = useState<number | null>(null);
  const [amount, setAmount] = useState(balanceDue > 0 ? String(balanceDue) : '');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function loadMethods() {
      setLoadingMethods(true);
      try {
        const response = await apiRequest<{ data: PaymentMethodOption[] }>('/settings/payment-methods', {}, token);
        if (!cancelled) {
          const active = response.data.filter((method) => method.is_active);
          setMethods(active);
          setPaymentMethodId(active.find((method) => method.cash)?.id ?? active[0]?.id ?? null);
        }
      } catch (loadError) {
        if (!cancelled) setError(getErrorMessage(loadError));
      } finally {
        if (!cancelled) setLoadingMethods(false);
      }
    }

    void loadMethods();
    return () => {
      cancelled = true;
    };
  }, [token]);

  const parsedAmount = Number(amount) || 0;
  const remainingAfter = Math.max(0, Math.round((balanceDue - parsedAmount) * 100) / 100);

  async function handleSubmit() {
    if (!paymentMethodId) {
      setError('Selecciona el metodo de pago recibido.');
      return;
    }

    if (parsedAmount <= 0) {
      setError('Ingresa un monto valido.');
      return;
    }

    if (parsedAmount > balanceDue + 0.0001) {
      setError(`El monto no puede superar el saldo pendiente (${formatCurrency(balanceDue)}).`);
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<SaleSaveResponse & { message: string }>(
        `/sales/${sale.id}/payments`,
        { method: 'POST', body: JSON.stringify({ amount: parsedAmount, payment_method_id: paymentMethodId }) },
        token,
      );
      onRegistered(response.message || 'Abono registrado correctamente.');
    } catch (submitError) {
      setError(getErrorMessage(submitError));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="w-full max-w-md overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-indigo-500 to-indigo-600 px-6 py-5 text-white">
          <span className="flex items-center gap-2.5 text-base font-black uppercase tracking-wide">
            <FiCheckCircle className="h-5 w-5" /> Abonar factura
          </span>
          <button type="button" onClick={onClose} className="rounded-full p-1.5 transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6">
          <p className="mb-4 text-sm text-slate-500">
            Factura <span className="font-semibold text-black dark:text-white">{sale.sale_number}</span> — saldo
            pendiente <span className="font-semibold text-amber-600">{formatCurrency(balanceDue)}</span>.
          </p>

          <label className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Monto a abonar</label>
          <input
            type="number"
            min={0}
            max={balanceDue}
            step="0.01"
            value={amount}
            onChange={(event) => setAmount(event.target.value)}
            className="mb-1 h-12 w-full rounded-lg border border-stroke px-3 text-lg font-bold outline-none dark:border-strokedark dark:bg-boxdark"
          />
          <p className="mb-4 text-xs text-slate-400">
            {parsedAmount > 0 && parsedAmount < balanceDue
              ? `Quedaria un saldo de ${formatCurrency(remainingAfter)} despues de este abono.`
              : 'Este abono completa el saldo de la factura.'}
          </p>

          {loadingMethods ? (
            <p className="text-sm text-slate-400">Cargando metodos de pago...</p>
          ) : (
            <div className="grid grid-cols-3 gap-3">
              {methods.map((method) => {
                const Icon = methodIcon(method);
                return (
                  <button
                    key={method.id}
                    type="button"
                    onClick={() => setPaymentMethodId(method.id)}
                    className={`flex h-20 flex-col items-center justify-center gap-2 rounded-xl border-2 px-2 text-center text-xs font-bold leading-tight transition ${
                      paymentMethodId === method.id
                        ? 'border-primary bg-primary/10 text-primary shadow-sm'
                        : 'border-stroke text-slate-500 hover:border-primary/50 hover:bg-primary/5 dark:border-strokedark'
                    }`}
                  >
                    <Icon className="h-5 w-5" /> {method.name}
                  </button>
                );
              })}
              {methods.length === 0 && (
                <p className="col-span-3 text-sm text-slate-400">No hay metodos de pago activos configurados.</p>
              )}
            </div>
          )}

          {error && <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">{error}</div>}

          <button
            type="button"
            disabled={submitting || !paymentMethodId || parsedAmount <= 0}
            onClick={() => void handleSubmit()}
            className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-indigo-500 text-sm font-bold text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <FiCheckCircle className="h-5 w-5" /> {submitting ? 'Registrando...' : 'Registrar abono'}
          </button>
        </div>
      </div>
    </div>
  );
}
