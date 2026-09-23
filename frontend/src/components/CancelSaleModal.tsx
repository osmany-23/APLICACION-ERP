import { useState } from 'react';
import { FiAlertTriangle, FiLock, FiSlash, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../services/api';
import { Sale } from '../types/sale';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

/**
 * Anular una factura exige el codigo (PIN) de un usuario con permiso para
 * administrar/eliminar facturacion — no basta con estar logueado en el POS
 * o en Ventas. Se usa desde el historial del POS (HistoryModal) y desde el
 * listado de Ventas, ambos apuntan al mismo endpoint
 * POST /sales/{id}/cancel (ver SalesService::resolveAuthorizedCanceller en
 * el backend), asi que este modal vive en components/ como pieza
 * compartida entre ambas pantallas.
 *
 * Al anular: el stock vuelve a bodega (queda en el kardex como
 * RETURN_IN), el asiento contable y la cuenta por cobrar (si existian) se
 * anulan, y la venta desaparece automaticamente de caja/reportes porque
 * esos calculos solo cuentan facturas en estado COMPLETED.
 */
export default function CancelSaleModal({
  sale,
  token,
  onClose,
  onCancelled,
}: {
  sale: Sale;
  token: string;
  onClose: () => void;
  onCancelled: (message: string) => void;
}) {
  const [pin, setPin] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  async function handleSubmit() {
    if (pin.length !== 4) {
      setError('Ingresa el codigo de 4 digitos del administrador.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<{ message: string }>(
        `/sales/${sale.id}/cancel`,
        { method: 'POST', body: JSON.stringify({ authorization_pin: pin }) },
        token,
      );
      onCancelled(response.message || 'Factura anulada correctamente.');
    } catch (submitError) {
      setError(getErrorMessage(submitError));
      setPin('');
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
        <div className="flex items-center justify-between bg-gradient-to-r from-red-500 to-red-600 px-6 py-5 text-white">
          <span className="flex items-center gap-2.5 text-base font-black uppercase tracking-wide">
            <FiSlash className="h-5 w-5" /> Anular factura
          </span>
          <button type="button" onClick={onClose} className="rounded-full p-1.5 transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="p-6">
          <p className="mb-4 text-sm text-slate-500">
            Factura <span className="font-semibold text-black dark:text-white">{sale.sale_number}</span> — esta accion
            devuelve el producto a bodega (afecta el kardex) y quita la venta de caja y reportes.
          </p>

          <div className="mb-5 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
            <FiAlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
            <span>Esta accion no se puede deshacer. Se requiere el codigo de un usuario con permiso de administrador para autorizarla.</span>
          </div>

          <label className="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Codigo de autorizacion</label>
          <div className="flex items-center gap-2 rounded-lg border border-stroke px-3 dark:border-strokedark">
            <FiLock className="h-4 w-4 text-slate-400" />
            <input
              type="password"
              inputMode="numeric"
              autoFocus
              maxLength={4}
              value={pin}
              onChange={(event) => setPin(event.target.value.replace(/\D/g, '').slice(0, 4))}
              onKeyDown={(event) => {
                if (event.key === 'Enter') void handleSubmit();
              }}
              placeholder="••••"
              className="h-12 w-full bg-transparent text-center text-xl font-black tracking-[0.5em] outline-none"
            />
          </div>

          {error && <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">{error}</div>}

          <button
            type="button"
            disabled={submitting || pin.length !== 4}
            onClick={() => void handleSubmit()}
            className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-red-500 text-sm font-bold text-white transition hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <FiSlash className="h-5 w-5" /> {submitting ? 'Anulando...' : 'Anular factura'}
          </button>
        </div>
      </div>
    </div>
  );
}
