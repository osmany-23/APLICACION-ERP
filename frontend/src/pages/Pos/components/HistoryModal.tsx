import { useCallback, useEffect, useState } from 'react';
import {
  FiCheckCircle,
  FiClock,
  FiDollarSign,
  FiEye,
  FiList,
  FiMinusCircle,
  FiPlusCircle,
  FiPrinter,
  FiSlash,
  FiX,
} from 'react-icons/fi';
import { apiRequest } from '../../../services/api';
import { Sale, SaleListResponse, SaleStatus, TransactionStatus } from '../../../types/sale';
import { PosExchangeRate } from '../../../types/pos';
import { ManagedUser, UserListResponse } from '../../../types/user';
import ActionsMenu from '../../../components/ActionsMenu';
import CancelSaleModal from '../../../components/CancelSaleModal';
import AbonarModal from '../../../components/AbonarModal';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function formatForeign(value: number, symbol: string) {
  return `${symbol}${new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0)}`;
}

// sale_date es DATE puro (sin hora); created_at si trae hora real, por eso
// se usa aca para mostrar "fecha con AM/PM" tal como se pidio.
function formatDateTime(value: string | null | undefined, fallback: string | null): string {
  if (!value) return fallback ?? '-';

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return fallback ?? '-';

  return new Intl.DateTimeFormat('es-NI', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(parsed);
}

// "Estado de factura" (Emitida/Anulada): distinto del workflow status crudo
// de abajo. Una proforma se queda en DRAFT, todavia no es "una factura".
function invoiceStatusLabel(status: SaleStatus): string {
  if (status === 'CANCELLED') return 'Anulada';
  if (status === 'DRAFT') return 'Borrador';
  return 'Emitida';
}

const INVOICE_STATUS_TONE: Record<string, string> = {
  Emitida: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  Anulada: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  Borrador: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
};

const TRANSACTION_STATUS_LABEL: Record<TransactionStatus, string> = {
  PENDING_PAYMENT: 'Pendiente de pago',
  CREDIT: 'Credito',
  PAID: 'Contado',
};

const TRANSACTION_STATUS_TONE: Record<TransactionStatus, string> = {
  PENDING_PAYMENT: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
  CREDIT: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  PAID: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
};

export default function HistoryModal({
  token,
  exchangeRate,
  onClose,
}: {
  token: string;
  exchangeRate: PosExchangeRate | null;
  onClose: () => void;
}) {
  const [sales, setSales] = useState<Sale[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [abonarSale, setAbonarSale] = useState<Sale | null>(null);
  const [cancelSale, setCancelSale] = useState<Sale | null>(null);
  const [statusFilter, setStatusFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [salespersonId, setSalespersonId] = useState('');
  const [users, setUsers] = useState<ManagedUser[]>([]);

  const loadSales = useCallback(async () => {
    setLoading(true);
    setError('');

    try {
      const params = new URLSearchParams();
      if (statusFilter) params.set('status', statusFilter);
      if (dateFrom) params.set('date_from', dateFrom);
      if (dateTo) params.set('date_to', dateTo);
      if (salespersonId) params.set('salesperson_id', salespersonId);
      const query = params.toString() ? `?${params.toString()}` : '';

      const response = await apiRequest<SaleListResponse>(`/sales${query}`, {}, token);
      setSales(response.data.slice(0, 30));
    } catch {
      setError('No se pudo cargar el historial de facturas.');
    } finally {
      setLoading(false);
    }
  }, [token, statusFilter, dateFrom, dateTo, salespersonId]);

  useEffect(() => {
    void loadSales();
  }, [loadSales]);

  useEffect(() => {
    apiRequest<UserListResponse>('/users', {}, token)
      .then((response) => setUsers(response.data))
      .catch(() => setUsers([]));
  }, [token]);

  async function handleConfirmDraft(sale: Sale) {
    try {
      const response = await apiRequest<{ message: string }>(`/sales/${sale.id}/confirm`, { method: 'POST' }, token);
      setNotice(response.message || 'Factura confirmada.');
      await loadSales();
    } catch {
      setError('No se pudo confirmar la factura.');
    }
  }

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="flex max-h-[90vh] w-[96vw] max-w-[1680px] flex-col overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-primary to-primary-dark px-5 py-4 text-white">
          <span className="flex items-center gap-2 text-sm font-black uppercase tracking-wide"><FiList /> Historial de facturas</span>
          <button type="button" onClick={onClose} className="rounded-full p-1 hover:bg-white/10">
            <FiX />
          </button>
        </div>

        {notice && (
          <div className="mx-5 mt-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2.5 text-xs font-semibold text-green-600">
            {notice}
          </div>
        )}
        {error && (
          <div className="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-semibold text-red-500">
            {error}
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2 border-b border-stroke px-5 py-3 dark:border-strokedark">
          <select
            value={salespersonId}
            onChange={(event) => setSalespersonId(event.target.value)}
            className="h-9 rounded-lg border border-stroke bg-white px-3 text-xs outline-none dark:border-strokedark dark:bg-boxdark"
          >
            <option value="">Todos los vendedores</option>
            {users.map((user) => (
              <option key={user.id} value={user.id}>{user.full_name}</option>
            ))}
          </select>
          <label className="flex h-9 items-center gap-2 rounded-lg border border-stroke bg-white px-3 dark:border-strokedark dark:bg-boxdark">
            <span className="text-xs font-semibold text-slate-400">Desde</span>
            <input
              type="date"
              value={dateFrom}
              onChange={(event) => setDateFrom(event.target.value)}
              className="bg-transparent text-xs outline-none"
            />
          </label>
          <label className="flex h-9 items-center gap-2 rounded-lg border border-stroke bg-white px-3 dark:border-strokedark dark:bg-boxdark">
            <span className="text-xs font-semibold text-slate-400">Hasta</span>
            <input
              type="date"
              value={dateTo}
              onChange={(event) => setDateTo(event.target.value)}
              className="bg-transparent text-xs outline-none"
            />
          </label>
          <button
            type="button"
            onClick={() => setStatusFilter(statusFilter === 'PENDING' ? '' : 'PENDING')}
            className={`inline-flex h-9 items-center gap-2 rounded-full px-3 text-xs font-bold transition ${
              statusFilter === 'PENDING'
                ? 'bg-indigo-500 text-white'
                : 'border border-indigo-200 text-indigo-600 hover:bg-indigo-50 dark:border-indigo-900 dark:text-indigo-300 dark:hover:bg-indigo-900/20'
            }`}
          >
            <FiClock className="h-3.5 w-3.5" /> Pendientes de pago
          </button>
          {(statusFilter || dateFrom || dateTo || salespersonId) && (
            <button
              type="button"
              onClick={() => { setStatusFilter(''); setDateFrom(''); setDateTo(''); setSalespersonId(''); }}
              className="text-xs font-semibold text-slate-400 hover:text-primary"
            >
              Quitar filtros
            </button>
          )}
        </div>

        <div className="overflow-y-auto">
          {loading && <p className="py-10 text-center text-sm text-slate-500">Cargando...</p>}
          {!loading && sales.length === 0 && !error && (
            <p className="py-10 text-center text-sm text-slate-500">
              {statusFilter || dateFrom || dateTo || salespersonId
                ? 'Ninguna factura coincide con los filtros seleccionados.'
                : 'Todavia no hay facturas registradas.'}
            </p>
          )}

          {!loading && sales.length > 0 && (
            <div className="overflow-x-auto">
              <table className="min-w-full text-left">
                <thead className="sticky top-0 bg-white dark:bg-boxdark">
                  <tr className="border-b border-stroke text-xs font-bold uppercase text-slate-500 dark:border-strokedark">
                    <th className="px-4 py-3">Numero</th>
                    <th className="px-4 py-3">Cliente</th>
                    <th className="px-4 py-3">Tipo</th>
                    <th className="px-4 py-3 text-right">Subtotal</th>
                    <th className="px-4 py-3 text-right">Descuento</th>
                    <th className="px-4 py-3 text-right">IVA</th>
                    <th className="px-4 py-3 text-right">Total</th>
                    <th className="px-4 py-3 text-right">Total USD</th>
                    <th className="px-4 py-3">Estado</th>
                    <th className="px-4 py-3">Fecha</th>
                    <th className="px-4 py-3" />
                  </tr>
                </thead>
                <tbody className="uppercase">
                  {sales.map((sale) => {
                    const invoiceStatus = invoiceStatusLabel(sale.status);
                    const totalUsd = exchangeRate && exchangeRate.rate > 0 ? sale.total / exchangeRate.rate : null;

                    return (
                      <tr key={sale.id} className="border-b border-stroke text-sm dark:border-strokedark">
                          <td className="px-4 py-3 font-semibold text-black dark:text-white">{sale.sale_number}</td>
                          <td className="px-4 py-3">
                            <div>{sale.customer_name ?? '-'}</div>
                            <div className="text-xs text-slate-400">{sale.customer_code}</div>
                          </td>
                          <td className="px-4 py-3">
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${TRANSACTION_STATUS_TONE[sale.transaction_status]}`}>
                              {TRANSACTION_STATUS_LABEL[sale.transaction_status]}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-right text-slate-500">{formatCurrency(sale.subtotal)}</td>
                          <td className="px-4 py-3 text-right text-slate-500">{formatCurrency(sale.discount)}</td>
                          <td className="px-4 py-3 text-right text-slate-500">{formatCurrency(sale.tax)}</td>
                          <td className="px-4 py-3 text-right font-semibold">{formatCurrency(sale.total)}</td>
                          <td className="px-4 py-3 text-right text-slate-500">
                            {totalUsd !== null && exchangeRate ? formatForeign(totalUsd, exchangeRate.foreign_currency.symbol) : '-'}
                          </td>
                          <td className="px-4 py-3">
                            <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${INVOICE_STATUS_TONE[invoiceStatus] ?? ''}`}>
                              {invoiceStatus}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-xs text-slate-500">{formatDateTime(sale.created_at, sale.sale_date)}</td>
                          <td className="px-4 py-3">
                            <ActionsMenu
                              ariaLabel={`Mas opciones de la factura ${sale.sale_number}`}
                              items={[
                                { label: 'Ver factura', icon: FiEye, onClick: () => window.open(`/sales/${sale.id}`, '_blank', 'noopener,noreferrer') },
                                { label: 'Imprimir factura', icon: FiPrinter, onClick: () => window.open(`/sales/${sale.id}?print=1`, '_blank', 'noopener,noreferrer') },
                                ...(sale.status === 'DRAFT'
                                  ? [{ label: 'Confirmar factura', icon: FiCheckCircle, onClick: () => void handleConfirmDraft(sale) }]
                                  : []),
                                ...(sale.status === 'PENDING'
                                  ? [{ label: 'Abonar', icon: FiDollarSign, onClick: () => setAbonarSale(sale) }]
                                  : []),
                                ...(sale.status === 'COMPLETED'
                                  ? [
                                      { label: 'Nota de credito', icon: FiMinusCircle, onClick: () => window.open(`/sales/${sale.id}`, '_blank', 'noopener,noreferrer') },
                                      { label: 'Nota de debito', icon: FiPlusCircle, onClick: () => window.open(`/sales/${sale.id}`, '_blank', 'noopener,noreferrer') },
                                    ]
                                  : []),
                                ...(sale.status !== 'CANCELLED'
                                  ? [{ label: 'Anular factura', icon: FiSlash, variant: 'danger' as const, onClick: () => setCancelSale(sale) }]
                                  : []),
                              ]}
                            />
                          </td>
                        </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>

      {abonarSale && (
        <AbonarModal
          sale={abonarSale}
          token={token}
          onClose={() => setAbonarSale(null)}
          onRegistered={(message) => {
            setAbonarSale(null);
            setNotice(message);
            void loadSales();
          }}
        />
      )}

      {cancelSale && (
        <CancelSaleModal
          sale={cancelSale}
          token={token}
          onClose={() => setCancelSale(null)}
          onCancelled={(message) => {
            setCancelSale(null);
            setNotice(message);
            void loadSales();
          }}
        />
      )}
    </div>
  );
}
