import { useEffect, useState } from 'react';
import { FiCheckCircle, FiClock, FiFileText, FiPrinter, FiX } from 'react-icons/fi';
import { apiRequest } from '../../../services/api';
import { Sale, SaleSaveResponse } from '../../../types/sale';
import { useReceiptConfig } from '../../../hooks/useReceiptConfig';
import ReceiptTicket from '../../../components/receipt/ReceiptTicket';

export default function ReceiptModal({
  saleId,
  variant = 'sale',
  isPendingPayment = false,
  token,
  onClose,
}: {
  saleId: number;
  variant?: 'sale' | 'proforma';
  isPendingPayment?: boolean;
  token?: string;
  onClose: () => void;
}) {
  const [sale, setSale] = useState<Sale | null>(null);
  const [error, setError] = useState('');
  const config = useReceiptConfig(token);

  useEffect(() => {
    if (!token) return;

    apiRequest<SaleSaveResponse>(`/sales/${saleId}`, {}, token)
      .then((response) => setSale(response.item))
      .catch(() => setError('No se pudo cargar el comprobante.'));
  }, [saleId, token]);

  const isProforma = variant === 'proforma';

  const headerTone = isProforma
    ? 'bg-gradient-to-r from-violet-500 to-indigo-500'
    : isPendingPayment
      ? 'bg-gradient-to-r from-indigo-500 to-indigo-600'
      : 'bg-gradient-to-r from-[#0F9F37] to-emerald-500';

  const HeaderIcon = isProforma ? FiFileText : isPendingPayment ? FiClock : FiCheckCircle;
  const headerTitle = isProforma ? 'Proforma generada' : isPendingPayment ? 'Pendiente de pago' : 'Venta registrada';

  return (
    // "overflow-y-auto" en el overlay (no "items-center" en un flex sin
    // scroll) es lo que deja al usuario bajar hasta el codigo de barra y
    // los botones Imprimir/Nueva venta cuando el ticket es mas alto que la
    // pantalla — con flex+items-center y sin scroll, ese contenido de abajo
    // quedaba cortado fuera de la ventana sin ninguna forma de alcanzarlo.
    <div className="fixed inset-0 z-[100000] overflow-y-auto bg-black/60 px-4 py-8 print:static print:overflow-visible print:bg-white">
      <style>{`
        @media print {
          body * { visibility: hidden; }
          #pos-print-receipt, #pos-print-receipt * { visibility: visible; }
          #pos-print-receipt { position: fixed; left: 0; top: 0; width: 100%; }
        }
      `}</style>
      <div className="mx-auto w-full max-w-sm overflow-hidden rounded-2xl border border-stroke bg-white text-center shadow-2xl dark:border-strokedark dark:bg-boxdark print:max-w-none print:rounded-none print:border-0 print:shadow-none">
        <div className={`px-5 py-8 text-white print:hidden ${headerTone}`}>
          <HeaderIcon className="mx-auto h-14 w-14" />
          <p className="mt-3 text-lg font-black">{headerTitle}</p>
          <p className="text-sm text-white/80">{isProforma ? 'Cotizacion' : 'Factura'} {sale?.sale_number ?? ''}</p>
        </div>

        <div id="pos-print-receipt" className="p-4">
          {error && <p className="p-6 text-sm text-red-500">{error}</p>}
          {!error && (!sale || !config) && <p className="p-6 text-sm text-slate-500">Cargando comprobante...</p>}
          {sale && config && (
            <>
              {isProforma && (
                <p className="mb-3 rounded-lg bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-600 dark:bg-violet-900/20 dark:text-violet-300 print:hidden">
                  Esta cotizacion no afecta inventario ni contabilidad hasta que se convierta en factura.
                </p>
              )}
              {isPendingPayment && (
                <p className="mb-3 rounded-lg bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-300 print:hidden">
                  El inventario ya se descuento. La venta entrara a caja y reportes cuando se confirme el cobro desde
                  Ventas.
                </p>
              )}
              <ReceiptTicket sale={sale} config={config} />
            </>
          )}
        </div>

        <div className="flex gap-3 p-5 pt-0 print:hidden">
          <button
            type="button"
            onClick={() => window.print()}
            className="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-lg border border-stroke text-sm font-bold text-black hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
          >
            <FiPrinter /> Imprimir
          </button>
          <button
            type="button"
            onClick={onClose}
            className={`inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-lg text-sm font-bold text-white hover:bg-opacity-90 ${isProforma ? 'bg-violet-500' : isPendingPayment ? 'bg-indigo-500' : 'bg-primary'}`}
          >
            <FiX /> Nueva venta
          </button>
        </div>
      </div>
    </div>
  );
}
