import { useState } from 'react';
import { FiPlusCircle, FiTrash2, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../../../services/api';
import { DebitNoteSaveResponse, Sale } from '../../../types/sale';

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

type DebitLine = {
  key: string;
  description: string;
  quantity: string;
  unitPrice: string;
  taxRate: string;
};

function emptyLine(): DebitLine {
  return { key: crypto.randomUUID(), description: '', quantity: '1', unitPrice: '', taxRate: '15' };
}

/**
 * Nota de Debito: cargo adicional sobre una factura ya emitida (correccion
 * de precio, flete, recargo, etc.). A diferencia de la Nota de Credito no
 * esta ligada a productos vendidos especificos — cada linea es libre
 * (descripcion + cantidad + precio), asi que el formulario deja agregar/
 * quitar lineas en vez de listar los items de la factura original.
 */
export default function DebitNoteModal({
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
  const [lines, setLines] = useState<DebitLine[]>([emptyLine()]);
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  function updateLine(key: string, patch: Partial<DebitLine>) {
    setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  }

  function addLine() {
    setLines((prev) => [...prev, emptyLine()]);
  }

  function removeLine(key: string) {
    setLines((prev) => (prev.length > 1 ? prev.filter((line) => line.key !== key) : prev));
  }

  const computedLines = lines
    .map((line) => {
      const quantity = Number(line.quantity) || 0;
      const unitPrice = Number(line.unitPrice) || 0;
      const taxRate = Number(line.taxRate) || 0;
      const subtotal = Math.round((quantity * unitPrice + Number.EPSILON) * 100) / 100;
      const tax = Math.round((subtotal * (taxRate / 100) + Number.EPSILON) * 100) / 100;

      return { ...line, quantity, unitPrice, subtotal, tax, total: subtotal + tax };
    })
    .filter((line) => line.description.trim() !== '' && line.quantity > 0 && line.unitPrice > 0);

  const previewTotal = computedLines.reduce((sum, line) => sum + line.total, 0);

  async function handleSubmit() {
    if (computedLines.length === 0) {
      setError('Agrega al menos un cargo con descripcion, cantidad y precio.');
      return;
    }

    if (reason.trim() === '') {
      setError('Indica la razon de la nota de debito.');
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const response = await apiRequest<DebitNoteSaveResponse>(
        `/sales/${sale.id}/debit-notes`,
        {
          method: 'POST',
          body: JSON.stringify({
            reason: reason.trim(),
            items: computedLines.map((line) => ({
              description: line.description.trim(),
              quantity: line.quantity,
              unit_price: line.unitPrice,
              tax: line.tax,
            })),
          }),
        },
        token,
      );
      onCreated(response.message || 'Nota de debito generada correctamente.');
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
        <div className="flex items-center justify-between bg-gradient-to-r from-amber-500 to-amber-600 px-6 py-5 text-white">
          <span className="flex items-center gap-2.5 text-base font-black uppercase tracking-wide">
            <FiPlusCircle className="h-5 w-5" /> Nota de debito
          </span>
          <button type="button" onClick={onClose} className="rounded-full p-1.5 transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="overflow-y-auto p-6">
          <p className="mb-4 text-sm text-slate-500">
            Factura <span className="font-semibold text-black dark:text-white">{sale.sale_number}</span> — agrega los
            cargos adicionales que se le suman al saldo del cliente.
          </p>

          <div className="space-y-3">
            {lines.map((line) => (
              <div key={line.key} className="grid grid-cols-12 gap-2 rounded-lg border border-stroke p-3 dark:border-strokedark">
                <input
                  value={line.description}
                  onChange={(event) => updateLine(line.key, { description: event.target.value })}
                  placeholder="Descripcion (ej. flete adicional)"
                  className="col-span-12 h-9 rounded-lg border border-stroke px-2 text-sm outline-none dark:border-strokedark dark:bg-boxdark sm:col-span-5"
                />
                <input
                  type="number"
                  min={0}
                  step="0.01"
                  value={line.quantity}
                  onChange={(event) => updateLine(line.key, { quantity: event.target.value })}
                  placeholder="Cant."
                  className="col-span-4 h-9 rounded-lg border border-stroke px-2 text-sm outline-none dark:border-strokedark dark:bg-boxdark sm:col-span-2"
                />
                <input
                  type="number"
                  min={0}
                  step="0.01"
                  value={line.unitPrice}
                  onChange={(event) => updateLine(line.key, { unitPrice: event.target.value })}
                  placeholder="Precio"
                  className="col-span-4 h-9 rounded-lg border border-stroke px-2 text-sm outline-none dark:border-strokedark dark:bg-boxdark sm:col-span-2"
                />
                <input
                  type="number"
                  min={0}
                  step="0.01"
                  value={line.taxRate}
                  onChange={(event) => updateLine(line.key, { taxRate: event.target.value })}
                  placeholder="IVA %"
                  className="col-span-3 h-9 rounded-lg border border-stroke px-2 text-sm outline-none dark:border-strokedark dark:bg-boxdark sm:col-span-2"
                />
                <button
                  type="button"
                  onClick={() => removeLine(line.key)}
                  className="col-span-1 flex h-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-900/20"
                >
                  <FiTrash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
          </div>

          <button
            type="button"
            onClick={addLine}
            className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
          >
            <FiPlusCircle className="h-4 w-4" /> Agregar otra linea
          </button>

          <label className="mb-1.5 mt-4 block text-xs font-semibold uppercase text-slate-500">Razon</label>
          <textarea
            value={reason}
            onChange={(event) => setReason(event.target.value)}
            rows={2}
            placeholder="Correccion de precio, flete no facturado, recargo por mora..."
            className="w-full rounded-lg border border-stroke px-3 py-2 text-sm outline-none dark:border-strokedark dark:bg-boxdark"
          />

          {computedLines.length > 0 && (
            <div className="mt-4 flex justify-between rounded-lg border border-stroke bg-slate-50 px-4 py-3 text-sm dark:border-strokedark dark:bg-white/5">
              <span className="font-semibold text-slate-500">Total estimado de la nota</span>
              <span className="font-bold text-black dark:text-white">{formatCurrency(previewTotal)}</span>
            </div>
          )}

          {error && <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">{error}</div>}

          <button
            type="button"
            disabled={submitting || computedLines.length === 0}
            onClick={() => void handleSubmit()}
            className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-amber-500 text-sm font-bold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <FiPlusCircle className="h-5 w-5" /> {submitting ? 'Generando...' : 'Generar nota de debito'}
          </button>
        </div>
      </div>
    </div>
  );
}
