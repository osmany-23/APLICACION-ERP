import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { FiArrowLeft, FiDollarSign, FiMinusCircle, FiPlusCircle, FiPrinter, FiSlash } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { Sale, SaleSaveResponse, SaleStatus, TransactionStatus } from '../../types/sale';
import { useReceiptConfig } from '../../hooks/useReceiptConfig';
import ReceiptTicket from '../../components/receipt/ReceiptTicket';
import ActionsMenu from '../../components/ActionsMenu';
import CancelSaleModal from '../../components/CancelSaleModal';
import AbonarModal from '../../components/AbonarModal';
import CreditNoteModal from './components/CreditNoteModal';
import DebitNoteModal from './components/DebitNoteModal';

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

function formatDateTime(value: string | null | undefined) {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';

  return new Intl.DateTimeFormat('es-NI', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true,
  }).format(parsed);
}

function formatDateOnly(value: string | null | undefined) {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';

  return new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }).format(parsed);
}

const statusLabels: Record<string, string> = {
  DRAFT: 'Borrador',
  PENDING: 'Pendiente de pago',
  COMPLETED: 'Confirmada',
  CANCELLED: 'Anulada',
};

// "Estado de factura" pedido por el usuario (Emitida/Anulada): distinto del
// workflow status crudo de arriba. Una proforma se queda en DRAFT y no es
// "una factura" todavia, por eso no cae en ninguna de las dos etiquetas.
function invoiceStatusLabel(status: SaleStatus): string | null {
  if (status === 'CANCELLED') return 'Anulada';
  if (status === 'DRAFT') return null;
  return 'Emitida';
}

const TRANSACTION_STATUS_LABEL: Record<TransactionStatus, string> = {
  PENDING_PAYMENT: 'Pendiente de pago (contra entrega)',
  CREDIT: 'Credito (cuenta por cobrar)',
  PAID: 'Cancelado (pagado)',
};

export default function SaleDetail() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const autoPrint = searchParams.get('print') === '1';
  const { token } = useAuth();
  const [sale, setSale] = useState<Sale | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const receiptConfig = useReceiptConfig(token);
  const hasAutoPrinted = useRef(false);

  const [showCancel, setShowCancel] = useState(false);
  const [showAbonar, setShowAbonar] = useState(false);
  const [showCreditNote, setShowCreditNote] = useState(false);
  const [showDebitNote, setShowDebitNote] = useState(false);

  const loadSale = useCallback(async () => {
    if (!token || !id) return;

    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<SaleSaveResponse>(`/sales/${id}`, {}, token);
      setSale(response.item);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
    } finally {
      setLoading(false);
    }
  }, [token, id]);

  useEffect(() => {
    void loadSale();
  }, [loadSale]);

  // "Imprimir factura" desde el historial (POS o Ventas) abre esta vista con
  // ?print=1 en una pestana nueva para no interrumpir el flujo: en cuanto la
  // factura termina de cargar se dispara el dialogo de impresion solo, sin
  // que el usuario tenga que darle click de nuevo al boton de aca abajo.
  // Booleano derivado (no el objeto "sale" en si) a proposito: en
  // desarrollo, StrictMode duplica el efecto que llama loadSale(), asi que
  // la factura se pide dos veces y "sale" termina apuntando a dos objetos
  // distintos (mismo contenido, distinta referencia). Si el efecto de abajo
  // dependiera del objeto "sale", esa segunda referencia lo volveria a
  // disparar, y su limpieza cancelaria el setTimeout antes de que llegue a
  // ejecutarse. Dependiendo solo de "hay factura si o no" el efecto no se
  // reinicia por esa causa.
  // Tambien espera receiptConfig (no solo "sale"): el bloque que se
  // imprime es el ReceiptTicket, y si el print se dispara antes de que
  // receiptConfig termine de cargar, saldria el mensaje "Cargando
  // comprobante..." en vez del ticket real.
  const readyToPrint = Boolean(sale) && Boolean(receiptConfig);

  useEffect(() => {
    if (autoPrint && readyToPrint && !hasAutoPrinted.current) {
      hasAutoPrinted.current = true;
      const timer = window.setTimeout(() => window.print(), 300);
      return () => window.clearTimeout(timer);
    }
  }, [autoPrint, readyToPrint]);

  if (loading) {
    return (
      <div className="rounded-[10px] border border-stroke bg-white p-8 text-center text-sm text-slate-500 shadow-default dark:border-strokedark dark:bg-boxdark">
        Cargando factura...
      </div>
    );
  }

  if (error || !sale) {
    return (
      <div className="rounded-[10px] border border-stroke bg-white p-8 shadow-default dark:border-strokedark dark:bg-boxdark">
        <p className="text-sm text-red-500">{error || 'Factura no encontrada.'}</p>
        <Link to="/sales" className="mt-4 inline-block text-sm font-semibold text-primary">
          Volver al listado
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-4 print:hidden sm:flex-row sm:items-center sm:justify-between">
        <Link to="/sales" className="inline-flex items-center gap-2 text-sm font-semibold text-primary">
          <FiArrowLeft /> Volver al listado
        </Link>
        <div className="flex items-center gap-2">
          <ActionsMenu
            ariaLabel={`Mas opciones de la factura ${sale.sale_number}`}
            items={[
              ...(sale.status === 'PENDING'
                ? [{ label: 'Abonar', icon: FiDollarSign, onClick: () => setShowAbonar(true) }]
                : []),
              ...(sale.status === 'COMPLETED'
                ? [
                    { label: 'Nota de credito', icon: FiMinusCircle, onClick: () => setShowCreditNote(true) },
                    { label: 'Nota de debito', icon: FiPlusCircle, onClick: () => setShowDebitNote(true) },
                  ]
                : []),
              ...(sale.status !== 'CANCELLED'
                ? [{ label: 'Anular factura', icon: FiSlash, variant: 'danger' as const, onClick: () => setShowCancel(true) }]
                : []),
            ]}
          />
          <button
            type="button"
            onClick={() => window.print()}
            className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
          >
            <FiPrinter /> Imprimir
          </button>
        </div>
      </div>

      {notice && (
        <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600 print:hidden">{notice}</div>
      )}

      {/* Vista en pantalla: detalle completo de la factura, para revisarla
          y gestionarla (abonar, anular, notas). El voucher/ticket (igual al
          que se imprime desde el POS) solo aparece al imprimir — ver el
          bloque "print:block" mas abajo — para no mezclar dos disenos muy
          distintos en la misma pantalla. */}
      <div className="rounded-[10px] border border-stroke bg-white p-8 shadow-default dark:border-strokedark dark:bg-boxdark print:hidden">
        <div className="mb-6 flex flex-col justify-between gap-4 border-b border-stroke pb-6 dark:border-strokedark sm:flex-row">
          <div>
            <h2 className="text-2xl font-black text-black dark:text-white">Factura de venta</h2>
            <p className="text-sm text-slate-500">{sale.branch?.name ?? 'Sucursal no asignada'}</p>
          </div>
          <div className="text-left sm:text-right">
            <p className="text-lg font-bold text-black dark:text-white">{sale.sale_number}</p>
            <p className="text-sm text-slate-500">{formatDateTime(sale.created_at ?? sale.sale_date)}</p>
            <div className="mt-1 flex flex-wrap items-center gap-1.5 sm:justify-end">
              {invoiceStatusLabel(sale.status) && (
                <span
                  className={`inline-block rounded-full px-3 py-1 text-xs font-semibold ${
                    sale.status === 'CANCELLED'
                      ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                      : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                  }`}
                >
                  {invoiceStatusLabel(sale.status)}
                </span>
              )}
              <span className="inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                {statusLabels[sale.status] ?? sale.status}
              </span>
            </div>
          </div>
        </div>

        <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <p className="text-xs font-semibold uppercase text-slate-500">Cliente</p>
            <p className="font-semibold text-black dark:text-white">{sale.customer_business_name || sale.customer_name}</p>
            <p className="text-sm text-slate-500">{sale.customer_code}</p>
            {sale.customer_tax_id && <p className="text-sm text-slate-500">RUC: {sale.customer_tax_id}</p>}
            {sale.customer_phone && <p className="text-sm text-slate-500">Tel: {sale.customer_phone}</p>}
            {sale.customer_address && <p className="text-sm text-slate-500">{sale.customer_address}</p>}
          </div>
          <div className="sm:text-right">
            <p className="text-xs font-semibold uppercase text-slate-500">Condicion</p>
            <p className="font-semibold text-black dark:text-white">
              {TRANSACTION_STATUS_LABEL[sale.transaction_status]}
            </p>
            {sale.payment_method_name && (
              <p className="text-sm text-slate-500">Metodo: {sale.payment_method_name}</p>
            )}
            {sale.payment_reference && (
              <p className="text-sm text-slate-500">Referencia: {sale.payment_reference}</p>
            )}
          </div>
        </div>

        {(sale.salesperson_name || sale.created_by_name || sale.branch?.address || sale.branch?.phone) && (
          <div className="mb-6 grid grid-cols-1 gap-4 rounded-lg border border-primary/20 bg-primary/5 p-4 sm:grid-cols-2 print:hidden">
            <div>
              <p className="text-xs font-semibold uppercase text-slate-500">Vendedor</p>
              <p className="font-semibold text-primary">{sale.salesperson_name ?? sale.created_by_name}</p>
              <p className="mt-2 text-xs font-semibold uppercase text-slate-500">Registrado por</p>
              <p className="font-semibold text-black dark:text-white">{sale.created_by_name ?? '-'}</p>
            </div>
            <div className="sm:text-right">
              <p className="text-xs font-semibold uppercase text-slate-500">Sucursal</p>
              <p className="font-semibold text-black dark:text-white">{sale.branch?.name ?? '-'}</p>
              {sale.branch?.address && <p className="text-sm text-slate-500">{sale.branch.address}</p>}
              {sale.branch?.phone && <p className="text-sm text-slate-500">Tel: {sale.branch.phone}</p>}
            </div>
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Producto</th>
                <th className="px-3 py-3">Und</th>
                <th className="px-3 py-3 text-right">Cantidad</th>
                <th className="px-3 py-3 text-right">Precio</th>
                <th className="px-3 py-3 text-right">Descuento</th>
                <th className="px-3 py-3 text-right">Impuesto</th>
                <th className="px-3 py-3 text-right">Total</th>
                <th className="px-3 py-3">Garantia</th>
              </tr>
            </thead>
            <tbody>
              {(sale.items ?? []).map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3">{item.product_name}</td>
                  <td className="px-3 py-3">{item.unit ?? '-'}</td>
                  <td className="px-3 py-3 text-right">{item.quantity}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.unit_price)}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.discount)}</td>
                  <td className="px-3 py-3 text-right">{formatCurrency(item.tax)}</td>
                  <td className="px-3 py-3 text-right font-semibold">{formatCurrency(item.total)}</td>
                  <td className="px-3 py-3 text-xs text-slate-500">
                    {item.has_warranty ? (
                      <>
                        {item.warranty_type || 'Con garantia'}
                        {item.warranty_expires_at && (
                          <span className="block">Vence: {formatDateTime(item.warranty_expires_at)}</span>
                        )}
                      </>
                    ) : (
                      'Sin garantia'
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mt-6 flex justify-end">
          <div className="w-full max-w-sm space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-slate-500">Subtotal</span>
              <span className="font-semibold">{formatCurrency(sale.subtotal)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Descuento</span>
              <span className="font-semibold">-{formatCurrency(sale.discount)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Impuesto</span>
              <span className="font-semibold">{formatCurrency(sale.tax)}</span>
            </div>
            {sale.shipping > 0 && (
              <div className="flex justify-between">
                <span className="text-slate-500">Envio</span>
                <span className="font-semibold">{formatCurrency(sale.shipping)}</span>
              </div>
            )}
            <div className="flex justify-between border-t border-stroke pt-2 text-base dark:border-strokedark">
              <span className="font-bold text-black dark:text-white">Total</span>
              <span className="font-bold text-black dark:text-white">{formatCurrency(sale.total)}</span>
            </div>
            {!!sale.exchange_rate && sale.exchange_rate > 1 && (
              <div className="flex justify-between text-slate-500">
                <span>Tipo de cambio</span>
                <span className="font-semibold">{formatCurrency(sale.exchange_rate)}</span>
              </div>
            )}
            {sale.amount_tendered_base != null && (
              <div className="flex justify-between text-slate-500">
                <span>Recibido en C$</span>
                <span className="font-semibold">{formatCurrency(sale.amount_tendered_base)}</span>
              </div>
            )}
            {sale.amount_tendered_foreign != null && (
              <div className="flex justify-between text-slate-500">
                <span>Recibido en $</span>
                <span className="font-semibold">${sale.amount_tendered_foreign.toFixed(2)}</span>
              </div>
            )}
            {sale.change_amount != null && (
              <div className="flex justify-between text-slate-500">
                <span>Vuelto</span>
                <span className="font-semibold">{formatCurrency(sale.change_amount)}</span>
              </div>
            )}
            {sale.balance_due > 0 && (
              <div className="flex justify-between text-amber-600">
                <span className="font-semibold">Saldo pendiente</span>
                <span className="font-semibold">{formatCurrency(sale.balance_due)}</span>
              </div>
            )}
            {!!sale.ir_withholding_amount && sale.ir_withholding_amount > 0 && (
              <>
                <div className="flex justify-between text-slate-500">
                  <span>Retencion IR ({sale.ir_withholding_rate}%)</span>
                  <span className="font-semibold">-{formatCurrency(sale.ir_withholding_amount)}</span>
                </div>
                <div className="flex justify-between border-t border-stroke pt-2 dark:border-strokedark">
                  <span className="font-bold text-black dark:text-white">Efectivo/banco recibido</span>
                  <span className="font-bold text-black dark:text-white">
                    {formatCurrency(sale.total - sale.ir_withholding_amount)}
                  </span>
                </div>
              </>
            )}
          </div>
        </div>

        {sale.transaction_status === 'CREDIT' && (
          <div className="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
            <p className="mb-1 text-xs font-semibold uppercase text-amber-700 dark:text-amber-400">Politica de credito</p>
            {sale.due_date && (
              <p>
                Fecha de vencimiento: <span className="font-semibold">{formatDateOnly(sale.due_date)}</span>
              </p>
            )}
            {sale.customer_applies_late_fee && sale.customer_late_fee_percentage ? (
              <p>
                Pasada esa fecha se aplica un recargo por mora del{' '}
                <span className="font-semibold">{sale.customer_late_fee_percentage}%</span>{' '}
                ({sale.customer_late_fee_period_unit === 'DAYS' ? 'diario' : sale.customer_late_fee_period_unit === 'WEEKS' ? 'semanal' : 'mensual'}) sobre el saldo pendiente.
              </p>
            ) : (
              <p>Este cliente no tiene configurado un recargo por mora.</p>
            )}
          </div>
        )}

        {sale.notes && (
          <div className="mt-6 rounded-lg border border-stroke p-4 text-sm text-slate-600 dark:border-strokedark dark:text-slate-300">
            <p className="mb-1 text-xs font-semibold uppercase text-slate-500">Notas internas</p>
            {sale.notes}
          </div>
        )}

        {!!sale.payments?.length && (
          <div className="mt-6">
            <p className="mb-2 text-xs font-semibold uppercase text-slate-500">Historial de abonos</p>
            <div className="overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-slate-50 dark:bg-white/5">
                  <tr className="text-xs font-bold uppercase text-slate-500">
                    <th className="px-3 py-2">Fecha</th>
                    <th className="px-3 py-2">Metodo</th>
                    <th className="px-3 py-2 text-right">Monto</th>
                    <th className="px-3 py-2">Registrado por</th>
                  </tr>
                </thead>
                <tbody>
                  {sale.payments.map((payment) => (
                    <tr key={payment.id} className="border-t border-stroke dark:border-strokedark">
                      <td className="px-3 py-2 text-slate-500">{formatDateTime(payment.created_at)}</td>
                      <td className="px-3 py-2">{payment.payment_method_name ?? '-'}</td>
                      <td className="px-3 py-2 text-right font-semibold">{formatCurrency(payment.amount)}</td>
                      <td className="px-3 py-2 text-slate-500">{payment.created_by_name ?? '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {(!!sale.credit_notes?.length || !!sale.debit_notes?.length) && (
          <div className="mt-6 print:hidden">
            <p className="mb-2 text-xs font-semibold uppercase text-slate-500">Notas de credito / debito emitidas</p>
            <div className="overflow-x-auto rounded-lg border border-stroke dark:border-strokedark">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-slate-50 dark:bg-white/5">
                  <tr className="text-xs font-bold uppercase text-slate-500">
                    <th className="px-3 py-2">Numero</th>
                    <th className="px-3 py-2">Tipo</th>
                    <th className="px-3 py-2">Fecha</th>
                    <th className="px-3 py-2">Razon</th>
                    <th className="px-3 py-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {(sale.credit_notes ?? []).map((note) => (
                    <tr key={`nc-${note.id}`} className="border-t border-stroke dark:border-strokedark">
                      <td className="px-3 py-2 font-semibold text-black dark:text-white">{note.number}</td>
                      <td className="px-3 py-2">
                        <span className="rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">
                          Nota de credito
                        </span>
                      </td>
                      <td className="px-3 py-2 text-slate-500">{formatDateTime(note.created_at)}</td>
                      <td className="px-3 py-2 text-slate-500">{note.reason ?? '-'}</td>
                      <td className="px-3 py-2 text-right font-semibold">-{formatCurrency(note.total)}</td>
                    </tr>
                  ))}
                  {(sale.debit_notes ?? []).map((note) => (
                    <tr key={`nd-${note.id}`} className="border-t border-stroke dark:border-strokedark">
                      <td className="px-3 py-2 font-semibold text-black dark:text-white">{note.number}</td>
                      <td className="px-3 py-2">
                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                          Nota de debito
                        </span>
                      </td>
                      <td className="px-3 py-2 text-slate-500">{formatDateTime(note.created_at)}</td>
                      <td className="px-3 py-2 text-slate-500">{note.reason ?? '-'}</td>
                      <td className="px-3 py-2 text-right font-semibold">{formatCurrency(note.total)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </div>

      {/* Vista de impresion: oculta en pantalla, solo aparece al imprimir
          (mismo ticket que ya se usa al cobrar en el POS, para que
          "Imprimir" desde aca produzca el mismo voucher). */}
      <div className="hidden print:block">
        {receiptConfig ? (
          <ReceiptTicket sale={sale} config={receiptConfig} />
        ) : (
          <p className="py-8 text-center text-sm text-slate-500">Cargando comprobante...</p>
        )}
      </div>

      {showCancel && token && (
        <CancelSaleModal
          sale={sale}
          token={token}
          onClose={() => setShowCancel(false)}
          onCancelled={(message) => {
            setShowCancel(false);
            setNotice(message);
            void loadSale();
          }}
        />
      )}

      {showAbonar && token && (
        <AbonarModal
          sale={sale}
          token={token}
          onClose={() => setShowAbonar(false)}
          onRegistered={(message) => {
            setShowAbonar(false);
            setNotice(message);
            void loadSale();
          }}
        />
      )}

      {showCreditNote && token && (
        <CreditNoteModal
          sale={sale}
          token={token}
          onClose={() => setShowCreditNote(false)}
          onCreated={(message) => {
            setShowCreditNote(false);
            setNotice(message);
            void loadSale();
          }}
        />
      )}

      {showDebitNote && token && (
        <DebitNoteModal
          sale={sale}
          token={token}
          onClose={() => setShowDebitNote(false)}
          onCreated={(message) => {
            setShowDebitNote(false);
            setNotice(message);
            void loadSale();
          }}
        />
      )}
    </div>
  );
}
