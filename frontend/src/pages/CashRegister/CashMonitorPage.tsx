import { useCallback, useEffect, useState } from 'react';
import { FiActivity, FiDollarSign, FiRefreshCw, FiTrendingUp } from 'react-icons/fi';
import { Link } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { CashMonitorResponse, CashMonitorRow } from '../../types/cashRegister';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function formatCurrency(value: number | null | undefined) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value ?? 0);
}

function formatDateTime(value: string | null) {
  if (!value) return '-';
  return new Date(value).toLocaleString('es-NI', { dateStyle: 'medium', timeStyle: 'short' });
}

const statusStyles: Record<string, string> = {
  ABIERTA: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  EN_ARQUEO: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  CERRADA: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  CANCELADA: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

export default function CashMonitorPage() {
  const { token } = useAuth();
  const [rows, setRows] = useState<CashMonitorRow[]>([]);
  const [totals, setTotals] = useState<CashMonitorResponse['totals'] | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setError('');

    try {
      const query = statusFilter ? `?status=${statusFilter}` : '';
      const response = await apiRequest<CashMonitorResponse>(`/cash-sessions/monitor${query}`, {}, token);
      setRows(response.sessions);
      setTotals(response.totals);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [token, statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-title-md2 font-black uppercase text-black dark:text-white">Monitor de cajas</h1>
          <p className="mt-1 text-sm text-slate-500">Vista en tiempo real de todas las cajas de la empresa, sin importar quien las abrio.</p>
        </div>
        <div className="flex items-center gap-3">
          <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)} className="h-11 rounded-lg border border-stroke bg-white px-3 text-sm outline-none dark:border-strokedark dark:bg-boxdark">
            <option value="">Abiertas y en arqueo</option>
            <option value="ABIERTA">Solo abiertas</option>
            <option value="CERRADA">Cerradas</option>
            <option value="CANCELADA">Canceladas</option>
          </select>
          <button type="button" onClick={() => void load()} className="inline-flex h-11 items-center gap-2 rounded-lg border border-stroke px-4 text-sm font-bold text-black hover:border-primary hover:text-primary dark:border-strokedark dark:text-white">
            <FiRefreshCw /> Actualizar
          </button>
        </div>
      </div>

      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

      {totals && (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div className="rounded-xl border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="flex items-center justify-between">
              <p className="text-xs font-bold uppercase text-slate-500">Cajas abiertas</p>
              <FiActivity className="text-primary" />
            </div>
            <p className="mt-2 text-xl font-black text-black dark:text-white">{totals.open_count}</p>
          </div>
          <div className="rounded-xl border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="flex items-center justify-between">
              <p className="text-xs font-bold uppercase text-slate-500">Fondo inicial total</p>
              <FiDollarSign className="text-slate-400" />
            </div>
            <p className="mt-2 text-xl font-black text-black dark:text-white">{formatCurrency(totals.total_opening)}</p>
          </div>
          <div className="rounded-xl border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="flex items-center justify-between">
              <p className="text-xs font-bold uppercase text-slate-500">Ventas del periodo</p>
              <FiTrendingUp className="text-[#0F9F37]" />
            </div>
            <p className="mt-2 text-xl font-black text-black dark:text-white">{formatCurrency(totals.total_sales)}</p>
          </div>
          <div className="rounded-xl border-2 border-primary bg-[#E8F0FF] p-4 dark:bg-primary/10">
            <div className="flex items-center justify-between">
              <p className="text-xs font-bold uppercase text-primary">Efectivo total en cajas</p>
              <FiDollarSign className="text-primary" />
            </div>
            <p className="mt-2 text-xl font-black text-primary">{formatCurrency(totals.total_cash)}</p>
          </div>
        </div>
      )}

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-4 py-3">Caja</th>
                <th className="px-4 py-3">Sucursal</th>
                <th className="px-4 py-3">Usuario</th>
                <th className="px-4 py-3">Terminal</th>
                <th className="px-4 py-3">Abierta</th>
                <th className="px-4 py-3 text-right">Fondo inicial</th>
                <th className="px-4 py-3 text-right">Ventas</th>
                <th className="px-4 py-3 text-right">Efectivo</th>
                <th className="px-4 py-3">Estado</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-4 py-3 font-semibold text-black dark:text-white">
                    <Link to={`/cash-register/sessions/${row.id}`} className="hover:text-primary hover:underline">
                      {row.cash_register?.name ?? '-'}
                    </Link>
                  </td>
                  <td className="px-4 py-3">{row.branch?.name ?? '-'}</td>
                  <td className="px-4 py-3">{row.opened_by?.full_name ?? '-'}</td>
                  <td className="px-4 py-3 text-xs text-slate-500">{row.terminal?.name ?? '-'}</td>
                  <td className="px-4 py-3 text-xs text-slate-500">{formatDateTime(row.opened_at)}</td>
                  <td className="px-4 py-3 text-right">{formatCurrency(row.opening_amount)}</td>
                  <td className="px-4 py-3 text-right">{formatCurrency(row.net_sales)}</td>
                  <td className="px-4 py-3 text-right font-bold text-primary">{formatCurrency(row.balance_actual)}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[row.status] ?? ''}`}>{row.status.replace('_', ' ')}</span>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr>
                  <td colSpan={9} className="px-4 py-8 text-center text-sm text-slate-500">No hay cajas que coincidan con este filtro.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
