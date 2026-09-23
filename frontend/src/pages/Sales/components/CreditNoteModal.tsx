import { useState } from 'react';
import { FiMinusCircle, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../../../services/api';
import { CreditNoteSaveResponse, Sale } from '../../../types/sale';

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
 * Nota de Credito: devuelve cantidades especificas de los productos de una
 * factura ya emitida. El backend recalcula subtotal/impuesto/total desde
 * la linea original y es la fuente de verdad (ver CreditNoteService); esta
 * vista solo muestra un preview proporcional para que el usuario vea antes
 * de enviar cuanto va a quedar acreditado.
 */
export default function CreditNoteModal({
  sale,
  token,
  onClose,
  onCreated,
}: {
  sale: Sale;
  token: string;
  onClose: () => void;
  onCreated: (message: string) => void;
}) {
  const [quantities, setQuantities] = useState<Record<number, string>>({});
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const items = sale.items ?? [];

  const lines = items
    .map((item) => {
      const requested = Number(quantities[item.id] || 0);
      if (requested <= 0) return null;

      const ratio = Math.min(requested, item.quantity) / item.quantity;
      const lineSubtotal = Math.round((item.subtotal * ratio + Number.EPSILON) * 100) / 100;
      const lineTax = Math.round((item.tax * ratio + Number.EPSILON) * 100) / 100;

      return {
        sale_item_id: item.id,
        product_name: item.product_name,
        quantity: requested,
        subtotal: lineSubtotal,
        tax: lineTax,
        total: Math.round((lineSubtotal + lineTax + Number.EPSILON) * 100) / 100,
      };
    })
    .filter((line): line is NonNullable<typeof line> => line !== null);

  const previewTotal = lines.reduce((sum, line) => sum + line.total, 0);

  async function handleSubmit() {
    if (lines.length === 0) {
      setError('Indica la cantidad a acreditar de al menos un producto.');
      return;
    }

    if (reason.trim() === '') {
      setError('Indica la razon de la nota de credito.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<CreditNoteSaveResponse>(
        `/sales/${sale.id}/credit-notes`,
        {
          method: 'POST',
          body: JSON.stringify({
            reason: reason.trim(),
            items: lines.map((line) => ({ sale_item_id: line.sale_item_id, quantity: line.quantity })),
          }),
        },
        token,
      );
      onCreated(response.message || 'Nota de credito generada correctamente.');
    } catch (submitError) {
      setError(getErrorMessage(submitError));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="flex max-h-[85vh] w-full max-w-2xl flex-col overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-primary to-primary-dark px-6 py-5 text-white">
          <span className="flex items-center gap-2.5 text-base font-black uppercase tracking-wide">
            <FiMinusCircle className="h-5 w-5" /> Nota de credito
          </span>
          <button type="button" onClick={onClose} className="rounded-full p-1.5 transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="overflow-y-auto p-6">
          <p className="mb-4 text-sm text-slate-500">
            Factura <span className="font-semibold text-black dark:text-white">{sale.sale_number}</span> — indica la
            cantidad a acreditar por producto. El inventario devuelto vuelve a bodega automaticamente.
          </p>

          <div className="overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
            <table className="min-w-full text-left text-sm">
              <thead className="bg-slate-50 dark:bg-white/5">
                <tr className="text-xs font-bold uppercase text-slate-500">
                  <th className="px-3 py-2">Producto</th>
                  <th className="px-3 py-2 text-right">Vendido</th>
                  <th className="px-3 py-2 text-right">A acreditar</th>
                </tr>
              </thead>
              <tbody>
                {items.map((item) => (
                  <tr key={item.id} className="border-t border-stroke dark:border-strokedark">
                    <td className="px-3 py-2">{item.product_name}</td>
                    <td className="px-3 py-2 text-right text-slate-500">{item.quantity}</td>
                    <td className="px-3 py-2 text-right">
                      <input
                        type="number"
                        min={0}
                        max={item.quantity}
                        step="0.01"
                        value={quantities[item.id] ?? ''}
                        onChange={(event) => setQuantities((prev) => ({ ...prev, [item.id]: event.target.value }))}
                        className="h-9 w-24 rounded-lg border border-stroke px-2 text-right outline-none dark:border-strokedark dark:bg-boxdark"
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <label className="mb-1.5 mt-4 block text-xs font-semibold uppercase text-slate-500">Razon</label>
          <textarea
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            rows={2}
            placeholder="Producto danado, devolucion del cliente, correccion de precio..."
            className="w-full rounded-lg border border-stroke px-3 py-2 text-sm outline-none dark:border-strokedark dark:bg-boxdark"
          />

          {lines.length > 0 && (
            <div className="mt-4 flex justify-between rounded-lg border border-stroke bg-slate-50 px-4 py-3 text-sm dark:border-strokedark dark:bg-white/5">
              <span className="font-semibold text-slate-500">Total estimado de la nota</span>
              <span className="font-bold text-black dark:text-white">{formatCurrency(previewTotal)}</span>
            </div>
          )}

          {error && <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">{error}</div>}

          <button
            type="button"
            disabled={submitting || lines.length === 0}
            onClick={() => void handleSubmit()}
            className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary text-sm font-bold text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:opacity-60"
          >
            <FiMinusCircle className="h-5 w-5" /> {submitting ? 'Generando...' : 'Generar nota de credito'}
          </button>
        </div>
      </div>
    </div>
  );
}
