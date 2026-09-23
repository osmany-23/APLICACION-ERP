import { useCallback, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import {
  FiArchive,
  FiClock,
  FiCornerUpLeft,
  FiCreditCard,
  FiDollarSign,
  FiMinusCircle,
  FiPlusCircle,
  FiRefreshCw,
  FiTrendingDown,
  FiTrendingUp,
} from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { CashMovement, CashMovementListResponse, CashSession, CashSessionResponse } from '../../types/cashRegister';

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

function formatDateTime(value: string | null | undefined) {
  if (!value) return '-';
  return new Date(value).toLocaleString('es-NI', { dateStyle: 'medium', timeStyle: 'short' });
}

function StatRow({ label, value, icon }: { label: string; value: string; icon: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between border-b border-stroke py-2.5 last:border-0 dark:border-strokedark">
      <span className="flex items-center gap-2 text-sm text-slate-500">{icon} {label}</span>
      <span className="text-sm font-bold text-black dark:text-white">{value}</span>
    </div>
  );
}

export default function CashSessionDetail() {
  const { id } = useParams<{ id: string }>();
  const { token } = useAuth();
  const [session, setSession] = useState<CashSession | null>(null);
  const [movements, setMovements] = useState<CashMovement[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    if (!token || !id) return;
    setLoading(true);
    setError('');

    try {
      const [sessionResponse, movementsResponse] = await Promise.all([
        apiRequest<CashSessionResponse>(`/cash-sessions/${id}`, {}, token),
        apiRequest<CashMovementListResponse>(`/cash-movements?cash_session_id=${id}`, {}, token),
      ]);
      setSession(sessionResponse.item);
      setMovements(movementsResponse.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [token, id]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return <div className="rounded-lg border border-dashed border-stroke p-12 text-center text-sm text-slate-500">Cargando...</div>;
  }

  if (error || !session) {
    return <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error || 'Apertura no encontrada.'}</div>;
  }

  const summary = session.summary;

  return (
    <div className="space-y-5">
      <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <h1 className="text-xl font-black text-black dark:text-white">{session.cash_register?.name}</h1>
            <p className="mt-1 text-sm text-slate-500">{session.branch?.name}</p>
          </div>
          <span className={`rounded-full px-3 py-1 text-xs font-bold ${session.status === 'ABIERTA' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200'}`}>
            {session.status.replace('_', ' ')}
          </span>
        </div>

        <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div>
            <p className="text-xs uppercase text-slate-400">Responsable</p>
            <p className="text-sm font-semibold text-black dark:text-white">{session.opened_by?.full_name ?? '-'}</p>
          </div>
          <div>
            <p className="text-xs uppercase text-slate-400">Abierta</p>
            <p className="text-sm font-semibold text-black dark:text-white">{formatDateTime(session.opened_at)}</p>
          </div>
          <div>
            <p className="text-xs uppercase text-slate-400">Cerrada</p>
            <p className="text-sm font-semibold text-black dark:text-white">{formatDateTime(session.closed_at)}</p>
          </div>
          <div>
            <p className="text-xs uppercase text-slate-400">Terminal</p>
            <p className="text-sm font-semibold text-black dark:text-white">{session.terminal?.name ?? '-'}</p>
          </div>
        </div>
      </div>

      {session.status === 'CERRADA' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div className="rounded-xl border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
            <p className="text-xs font-bold uppercase text-slate-500">Efectivo esperado</p>
            <p className="mt-1 text-xl font-black text-black dark:text-white">{formatCurrency(session.expected_cash)}</p>
          </div>
          <div className="rounded-xl border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
            <p className="text-xs font-bold uppercase text-slate-500">Efectivo contado</p>
            <p className="mt-1 text-xl font-black text-black dark:text-white">{formatCurrency(session.counted_cash)}</p>
          </div>
          <div className={`rounded-xl border-2 p-4 ${(session.cash_difference ?? 0) < 0 ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : (session.cash_difference ?? 0) > 0 ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20' : 'border-green-400 bg-green-50 dark:bg-green-900/20'}`}>
            <p className="text-xs font-bold uppercase text-slate-600 dark:text-slate-300">Diferencia</p>
            <p className="mt-1 text-xl font-black text-black dark:text-white">{formatCurrency(session.cash_difference)}</p>
          </div>
        </div>
      )}

      {summary && (
        <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
          <h2 className="mb-2 text-lg font-black text-black dark:text-white">Resumen financiero</h2>
          <StatRow label="Monto inicial" value={formatCurrency(summary.opening_amount)} icon={<FiDollarSign />} />
          <StatRow label="Ventas en efectivo" value={formatCurrency(summary.cash_sales)} icon={<FiTrendingUp />} />
          <StatRow label="Ventas con tarjeta" value={formatCurrency(summary.card_sales)} icon={<FiCreditCard />} />
          <StatRow label="Transferencias" value={formatCurrency(summary.transfer_sales)} icon={<FiRefreshCw />} />
          <StatRow label="Otros medios" value={formatCurrency(summary.other_sales)} icon={<FiArchive />} />
          <StatRow label="Ventas a credito (no afecta caja)" value={formatCurrency(summary.credit_sales)} icon={<FiClock />} />
          <StatRow label="Ingresos por creditos" value={formatCurrency(summary.credit_collections)} icon={<FiClock />} />
          <StatRow label="Ingresos manuales" value={formatCurrency(summary.manual_income)} icon={<FiPlusCircle />} />
          <StatRow label="Egresos manuales" value={formatCurrency(summary.manual_expense)} icon={<FiMinusCircle />} />
          <StatRow label="Devoluciones" value={formatCurrency(summary.returns)} icon={<FiCornerUpLeft />} />
          <StatRow label="Descuentos (no afecta caja)" value={formatCurrency(summary.discounts)} icon={<FiTrendingDown />} />
          <StatRow label="Costo de mercancia" value={formatCurrency(summary.cost_of_goods)} icon={<FiArchive />} />
          <StatRow label="Utilidad bruta" value={formatCurrency(summary.gross_profit)} icon={<FiTrendingUp />} />
          <StatRow label="Utilidad neta" value={formatCurrency(summary.net_profit)} icon={<FiTrendingUp />} />
          <div className="mt-3 flex items-center justify-between rounded-lg bg-[#E8F0FF] px-4 py-3 dark:bg-primary/10">
            <span className="text-sm font-bold text-primary">Balance actual</span>
            <span className="text-lg font-black text-primary">{formatCurrency(summary.balance_actual)}</span>
          </div>
        </div>
      )}

      <div className="rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="border-b border-stroke px-6 py-4 dark:border-strokedark">
          <h2 className="text-lg font-black text-black dark:text-white">Movimientos</h2>
        </div>
        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-4 py-3">Fecha</th>
                <th className="px-4 py-3">Tipo</th>
                <th className="px-4 py-3">Motivo</th>
                <th className="px-4 py-3 text-right">Monto</th>
                <th className="px-4 py-3">Usuario</th>
                <th className="px-4 py-3">Estado</th>
              </tr>
            </thead>
            <tbody>
              {movements.map((movement) => (
                <tr key={movement.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-4 py-3">{formatDateTime(movement.created_at)}</td>
                  <td className="px-4 py-3">{movement.type}</td>
                  <td className="px-4 py-3">{movement.reason_name ?? movement.reason_text ?? '-'}</td>
                  <td className="px-4 py-3 text-right font-semibold">{formatCurrency(movement.amount)}</td>
                  <td className="px-4 py-3">{movement.user?.full_name}</td>
                  <td className="px-4 py-3">{movement.status.replace('_', ' ')}</td>
                </tr>
              ))}
              {movements.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-sm text-slate-500">Sin movimientos registrados.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
