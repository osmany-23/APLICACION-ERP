import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FiArrowLeft, FiPrinter } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { useBranding } from '../../context/BrandingContext';
import { ApiError, apiRequest } from '../../services/api';
import { Purchase, PurchaseSaveResponse } from '../../types/purchase';

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

const statusLabels: Record<string, string> = {
  DRAFT: 'Borrador',
  PENDING: 'Pendiente',
  RECEIVED: 'Recibida',
  CANCELLED: 'Anulada',
};

export default function PurchaseDetail() {
  const { id } = useParams();
  const { token } = useAuth();
  const { branding } = useBranding();
  const [purchase, setPurchase] = useState<Purchase | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const loadPurchase = useCallback(async () => {
    if (!token || !id) return;

    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<PurchaseSaveResponse>(`/purchases/${id}`, {}, token);
      setPurchase(response.item);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
    } finally {
      setLoading(false);
    }
  }, [token, id]);

  useEffect(() => {
    void loadPurchase();
  }, [loadPurchase]);

  if (loading) {
    return (
      <div className="rounded-[10px] border border-stroke bg-white p-8 text-center text-sm text-slate-500 shadow-default dark:border-strokedark dark:bg-boxdark">
        Cargando compra...
      </div>
    );
  }

  if (error || !purchase) {
    return (
      <div className="rounded-[10px] border border-stroke bg-white p-8 shadow-default dark:border-strokedark dark:bg-boxdark">
        <p className="text-sm text-red-500">{error || 'Compra no encontrada.'}</p>
        <Link to="/purchases" className="mt-4 inline-block text-sm font-semibold text-primary">
          Volver al listado
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <Link to="/purchases" className="inline-flex items-center gap-2 text-sm font-semibold text-primary">
          <FiArrowLeft /> Volver al listado
        </Link>
        <button
          type="button"
          onClick={() => window.print()}
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
        >
          <FiPrinter /> Imprimir
        </button>
      </div>

      <div className="rounded-[10px] border border-stroke bg-white p-8 shadow-default dark:border-strokedark dark:bg-boxdark print:border-0 print:shadow-none">
        <div className="mb-6 flex flex-col justify-between gap-4 border-b border-stroke pb-6 dark:border-strokedark sm:flex-row">
          <div>
            <h2 className="text-2xl font-black text-black dark:text-white">{branding.commercial_name}</h2>
            <p className="text-sm text-slate-500">Comprobante de compra</p>
          </div>
          <div className="text-left sm:text-right">
            <p className="text-lg font-bold text-black dark:text-white">{purchase.purchase_number}</p>
            <p className="text-sm text-slate-500">{purchase.purchase_date}</p>
            <span className="mt-1 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
              {statusLabels[purchase.status] ?? purchase.status}
            </span>
          </div>
        </div>

        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <p className="text-xs font-semibold uppercase text-slate-500">Proveedor</p>
            <p className="font-semibold text-black dark:text-white">{purchase.supplier_name}</p>
            <p className="text-sm text-slate-500">{purchase.supplier_code}</p>
          </div>
          <div className="sm:text-right">
            <p className="text-xs font-semibold uppercase text-slate-500">Condicion</p>
            <p className="font-semibold text-black dark:text-white">
              {purchase.is_credit ? 'Credito (cuenta por pagar)' : 'Pago inmediato'}
            </p>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Producto</th>
                <th className="px-3 py-3 text-right">Cantidad</th>
                <th className="px-3 py-3 text-right">Costo</th>
                <th className="px-3 py-3 text-right">Descuento</th>
                <th className="px-3 py-3 text-right">Impuesto</th>
                <th className="px-3 py-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              {(purchase.items ?? []).map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3">{item.product_name}</td>
                  <td className="px-3 py-3 text-right">{item.quantity}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.unit_cost)}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.discount)}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.tax)}</td>
                  <td className="px-3 py-3 text-right font-semibold">{formatCurrency(item.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mt-6 flex justify-end">
          <div className="w-full max-w-sm space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-slate-500">Subtotal</span>
              <span className="font-semibold">{formatCurrency(purchase.subtotal)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Descuento</span>
              <span className="font-semibold">-{formatCurrency(purchase.discount)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Impuesto</span>
              <span className="font-semibold">{formatCurrency(purchase.tax)}</span>
            </div>
            <div className="flex justify-between border-t border-stroke pt-2 text-base dark:border-strokedark">
              <span className="font-bold text-black dark:text-white">Total</span>
              <span className="font-bold text-black dark:text-white">{formatCurrency(purchase.total)}</span>
            </div>
            {purchase.is_credit && (
              <div className="flex justify-between text-amber-600">
                <span className="font-semibold">Saldo pendiente</span>
                <span className="font-semibold">{formatCurrency(purchase.balance_due)}</span>
              </div>
            )}
          </div>
        </div>

        {purchase.notes && (
          <div className="mt-6 rounded-lg border border-stroke p-4 text-sm text-slate-600 dark:border-strokedark dark:text-slate-300">
            <p className="mb-1 text-xs font-semibold uppercase text-slate-500">Notas</p>
            {purchase.notes}
          </div>
        )}
      </div>
    </div>
  );
}
