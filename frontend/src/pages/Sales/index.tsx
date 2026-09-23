import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  FiEye,
  FiPlus,
  FiPrinter,
  FiSearch,
  FiSlash,
  FiCheckCircle,
  FiClock,
  FiDollarSign,
  FiMinusCircle,
  FiPlusCircle,
} from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { Sale, SaleListResponse } from '../../types/sale';
import { ManagedUser, UserListResponse } from '../../types/user';
import ActionsMenu from '../../components/ActionsMenu';
import CancelSaleModal from '../../components/CancelSaleModal';
import AbonarModal from '../../components/AbonarModal';

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

const statusStyles: Record<string, string> = {
  DRAFT: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  PENDING: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
  COMPLETED: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  CANCELLED: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

const statusLabels: Record<string, string> = {
  DRAFT: 'Borrador',
  PENDING: 'Pendiente de pago',
  COMPLETED: 'Confirmada',
  CANCELLED: 'Anulada',
};

export default function SalesPage() {
  const { token } = useAuth();
  const navigate = useNavigate();
  const [items, setItems] = useState<Sale[]>([]);
  const [meta, setMeta] = useState({ total: 0, total_amount: 0, balance_due: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [salespersonId, setSalespersonId] = useState('');
  const [users, setUsers] = useState<ManagedUser[]>([]);
  const [abonarSale, setAbonarSale] = useState<Sale | null>(null);
  const [cancelSale, setCancelSale] = useState<Sale | null>(null);

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setLoading(false);
      return;
    }

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
      setItems(response.data);
      setMeta(response.meta);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token, statusFilter, dateFrom, dateTo, salespersonId]);

  useEffect(() => {
    void loadItems();
  }, [loadItems]);

  useEffect(() => {
    if (!token) return;

    apiRequest<UserListResponse>('/users', {}, token)
      .then((response) => setUsers(response.data))
      .catch(() => setUsers([]));
  }, [token]);

  async function handleConfirm(sale: Sale) {
    if (!token) return;

    try {
      const response = await apiRequest<{ message: string }>(`/sales/${sale.id}/confirm`, { method: 'POST' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (confirmError) {
      setError(getErrorMessage(confirmError));
    }
  }

  const filteredItems = useMemo(() => {
    const normalized = search.trim().toLowerCase();
    if (!normalized) return items;

    return items.filter((item) =>
      `${item.sale_number} ${item.customer_name ?? ''} ${item.customer_code ?? ''}`.toLowerCase().includes(normalized),
    );
  }, [items, search]);

  return (
    <>
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Facturación / Ventas</h2>
          <p className="mt-1 text-sm text-slate-500">Historial de facturas emitidas y su estado de cobro.</p>
        </div>
        <Link
          to="/sales/new"
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
        >
          <FiPlus className="h-4 w-4" /> Nueva factura
        </Link>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}
      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

      <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
          <p className="text-xs font-semibold text-slate-500">Facturas listadas</p>
          <p className="mt-1 text-lg font-bold text-black dark:text-white">{meta.total}</p>
        </div>
        <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
          <p className="text-xs font-semibold text-slate-500">Monto total</p>
          <p className="mt-1 text-lg font-bold text-black dark:text-white">{formatCurrency(meta.total_amount)}</p>
        </div>
        <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
          <p className="text-xs font-semibold text-slate-500">Saldo por cobrar</p>
          <p className="mt-1 text-lg font-bold text-amber-600">{formatCurrency(meta.balance_due)}</p>
        </div>
      </div>

      <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <label className="flex items-center gap-2 rounded-lg border border-stroke bg-white px-3 py-2 dark:border-strokedark dark:bg-boxdark">
          <FiSearch className="text-slate-400" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="w-full bg-transparent text-sm outline-none"
            placeholder="Buscar por numero o cliente..."
          />
        </label>
        <select
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value)}
          className="h-11 rounded-lg border border-stroke bg-white px-3 text-sm outline-none dark:border-strokedark dark:bg-boxdark"
        >
          <option value="">Todos los estados</option>
          <option value="DRAFT">Borrador</option>
          <option value="PENDING">Pendiente de pago</option>
          <option value="COMPLETED">Confirmada</option>
          <option value="CANCELLED">Anulada</option>
        </select>
        <select
          value={salespersonId}
          onChange={(event) => setSalespersonId(event.target.value)}
          className="h-11 rounded-lg border border-stroke bg-white px-3 text-sm outline-none dark:border-strokedark dark:bg-boxdark"
        >
          <option value="">Todos los vendedores</option>
          {users.map((user) => (
            <option key={user.id} value={user.id}>{user.full_name}</option>
          ))}
        </select>
        <label className="flex h-11 items-center gap-2 rounded-lg border border-stroke bg-white px-3 dark:border-strokedark dark:bg-boxdark">
          <span className="text-xs font-semibold text-slate-400">Desde</span>
          <input
            type="date"
            value={dateFrom}
            onChange={(event) => setDateFrom(event.target.value)}
            className="w-full bg-transparent text-sm outline-none"
          />
        </label>
        <label className="flex h-11 items-center gap-2 rounded-lg border border-stroke bg-white px-3 dark:border-strokedark dark:bg-boxdark">
          <span className="text-xs font-semibold text-slate-400">Hasta</span>
          <input
            type="date"
            value={dateTo}
            onChange={(event) => setDateTo(event.target.value)}
            className="w-full bg-transparent text-sm outline-none"
          />
        </label>
      </div>

      <div className="mb-5 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={() => setStatusFilter(statusFilter === 'PENDING' ? '' : 'PENDING')}
          className={`inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold transition ${
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

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Numero</th>
                <th className="px-3 py-3">Fecha</th>
                <th className="px-3 py-3">Cliente</th>
                <th className="px-3 py-3 text-right">Total</th>
                <th className="px-3 py-3 text-right">Saldo</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.sale_number}</td>
                  <td className="px-3 py-3">{item.sale_date ?? '-'}</td>
                  <td className="px-3 py-3">
                    <div>{item.customer_name ?? 'Cliente'}</div>
                    <div className="text-xs text-slate-500">{item.customer_code}</div>
                  </td>
                  <td className="px-3 py-3 text-right font-semibold">{formatCurrency(item.total)}</td>
                  <td className="px-3 py-3 text-right">
                    {item.balance_due > 0 ? (
                      <span className="font-semibold text-amber-600">{formatCurrency(item.balance_due)}</span>
                    ) : (
                      <span className="text-green-600">Al dia</span>
                    )}
                  </td>
                  <td className="px-3 py-3">
                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusStyles[item.status] ?? ''}`}>
                      {statusLabels[item.status] ?? item.status}
                    </span>
                  </td>
                  <td className="px-3 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de la factura ${item.sale_number}`}
                        items={[
                          { label: 'Ver detalle de factura', icon: FiEye, onClick: () => navigate(`/sales/${item.id}`) },
                          { label: 'Imprimir factura', icon: FiPrinter, onClick: () => window.open(`/sales/${item.id}?print=1`, '_blank', 'noopener,noreferrer') },
                          ...(item.status === 'DRAFT'
                            ? [{ label: 'Confirmar factura', icon: FiCheckCircle, onClick: () => void handleConfirm(item) }]
                            : []),
                          ...(item.status === 'PENDING'
                            ? [{ label: 'Abonar', icon: FiDollarSign, onClick: () => setAbonarSale(item) }]
                            : []),
                          ...(item.status === 'COMPLETED'
                            ? [
                                { label: 'Nota de credito', icon: FiMinusCircle, onClick: () => navigate(`/sales/${item.id}`) },
                                { label: 'Nota de debito', icon: FiPlusCircle, onClick: () => navigate(`/sales/${item.id}`) },
                              ]
                            : []),
                          ...(item.status !== 'CANCELLED'
                            ? [{ label: 'Anular factura', icon: FiSlash, variant: 'danger' as const, onClick: () => setCancelSale(item) }]
                            : []),
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-slate-500">
                    No hay facturas registradas todavia.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>

    {abonarSale && token && (
      <AbonarModal
        sale={abonarSale}
        token={token}
        onClose={() => setAbonarSale(null)}
        onRegistered={(message) => {
          setAbonarSale(null);
          setNotice(message);
          void loadItems();
        }}
      />
    )}

    {cancelSale && token && (
      <CancelSaleModal
        sale={cancelSale}
        token={token}
        onClose={() => setCancelSale(null)}
        onCancelled={(message) => {
          setCancelSale(null);
          setNotice(message);
          void loadItems();
        }}
      />
    )}
    </>
  );
}
