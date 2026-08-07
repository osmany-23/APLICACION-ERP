import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { FiEye, FiPlus, FiSearch, FiSlash, FiCheckCircle } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { Purchase, PurchaseListResponse } from '../../types/purchase';

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
  RECEIVED: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  CANCELLED: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

const statusLabels: Record<string, string> = {
  DRAFT: 'Borrador',
  PENDING: 'Pendiente',
  RECEIVED: 'Recibida',
  CANCELLED: 'Anulada',
};

export default function PurchasesPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<Purchase[]>([]);
  const [meta, setMeta] = useState({ total: 0, total_amount: 0, balance_due: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const loadItems = useCallback(async () => {
    if (!token) {
      setItems([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError('');

    try {
      const query = statusFilter ? `?status=${statusFilter}` : '';
      const response = await apiRequest<PurchaseListResponse>(`/purchases${query}`, {}, token);
      setItems(response.data);
      setMeta(response.meta);
    } catch (loadError) {
      setError(getErrorMessage(loadError));
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token, statusFilter]);

  useEffect(() => {
    void loadItems();
  }, [loadItems]);

  async function handleCancel(purchase: Purchase) {
    if (!token) return;

    const confirmed = window.confirm(`¿Anular la compra ${purchase.purchase_number}?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<{ message: string }>(`/purchases/${purchase.id}/cancel`, { method: 'POST' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (cancelError) {
      setError(getErrorMessage(cancelError));
    }
  }

  async function handleConfirm(purchase: Purchase) {
    if (!token) return;

    try {
      const response = await apiRequest<{ message: string }>(`/purchases/${purchase.id}/confirm`, { method: 'POST' }, token);
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
      `${item.purchase_number} ${item.supplier_name ?? ''} ${item.supplier_code ?? ''}`.toLowerCase().includes(normalized),
    );
  }, [items, search]);

  return (
    <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">Compras</h2>
          <p className="mt-1 text-sm text-slate-500">Ordenes de compra recibidas y su estado de pago a proveedores.</p>
        </div>
        <Link
          to="/purchases/new"
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white"
        >
          <FiPlus className="h-4 w-4" /> Nueva compra
        </Link>
      </div>

      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}
      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

      <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
          <p className="text-xs font-semibold text-slate-500">Compras listadas</p>
          <p className="mt-1 text-lg font-bold text-black dark:text-white">{meta.total}</p>
        </div>
        <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
          <p className="text-xs font-semibold text-slate-500">Monto total</p>
          <p className="mt-1 text-lg font-bold text-black dark:text-white">{formatCurrency(meta.total_amount)}</p>
        </div>
        <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
          <p className="text-xs font-semibold text-slate-500">Saldo por pagar</p>
          <p className="mt-1 text-lg font-bold text-amber-600">{formatCurrency(meta.balance_due)}</p>
        </div>
      </div>

      <div className="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <label className="flex items-center gap-2 rounded-lg border border-stroke bg-white px-3 py-2 dark:border-strokedark dark:bg-boxdark">
          <FiSearch className="text-slate-400" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            className="w-full bg-transparent text-sm outline-none"
            placeholder="Buscar por numero o proveedor..."
          />
        </label>
        <select
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value)}
          className="h-11 rounded-lg border border-stroke bg-white px-3 text-sm outline-none dark:border-strokedark dark:bg-boxdark"
        >
          <option value="">Todos los estados</option>
          <option value="DRAFT">Borrador</option>
          <option value="PENDING">Pendiente</option>
          <option value="RECEIVED">Recibida</option>
          <option value="CANCELLED">Anulada</option>
        </select>
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
                <th className="px-3 py-3">Proveedor</th>
                <th className="px-3 py-3 text-right">Total</th>
                <th className="px-3 py-3 text-right">Saldo</th>
                <th className="px-3 py-3">Estado</th>
                <th className="px-3 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {filteredItems.map((item) => (
                <tr key={item.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-3 py-3 font-semibold text-black dark:text-white">{item.purchase_number}</td>
                  <td className="px-3 py-3">{item.purchase_date ?? '-'}</td>
                  <td className="px-3 py-3">
                    <div>{item.supplier_name ?? 'Proveedor'}</div>
                    <div className="text-xs text-slate-500">{item.supplier_code}</div>
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
                    <div className="flex justify-end gap-2">
                      <Link
                        to={`/purchases/${item.id}`}
                        className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-primary text-primary hover:bg-primary hover:text-white"
                        title="Ver detalle"
                      >
                        <FiEye />
                      </Link>
                      {item.status === 'DRAFT' && (
                        <button
                          type="button"
                          onClick={() => void handleConfirm(item)}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-[#0F9F37] text-[#0F9F37] hover:bg-[#0F9F37] hover:text-white"
                          title="Confirmar recepcion"
                        >
                          <FiCheckCircle />
                        </button>
                      )}
                      {item.status !== 'CANCELLED' && (
                        <button
                          type="button"
                          onClick={() => void handleCancel(item)}
                          className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                          title="Anular compra"
                        >
                          <FiSlash />
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
              {filteredItems.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-slate-500">
                    No hay compras registradas todavia.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
