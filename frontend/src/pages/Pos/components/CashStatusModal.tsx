import { useEffect, useState } from 'react';
import {
  FiArrowDownCircle,
  FiArrowUpCircle,
  FiClock,
  FiCornerUpLeft,
  FiCreditCard,
  FiDollarSign,
  FiInfo,
  FiPercent,
  FiRefreshCw,
  FiTrendingUp,
  FiX,
} from 'react-icons/fi';
import { Link } from 'react-router-dom';
import { apiRequest } from '../../../services/api';
import { CashSession, CashSessionResponse } from '../../../types/cashRegister';

function formatCurrency(value: number | null | undefined) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value ?? 0);
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return '-';
  return new Date(value).toLocaleString('es-NI', { dateStyle: 'medium', timeStyle: 'short' });
}

function Row({
  label,
  value,
  icon,
  tone = 'default',
}: {
  label: string;
  value: number | null | undefined;
  icon: React.ReactNode;
  tone?: 'default' | 'positive' | 'negative';
}) {
  const amount = value ?? 0;
  const valueTone = tone === 'positive' ? 'text-emerald-600 dark:text-emerald-400' : tone === 'negative' ? 'text-red-500' : 'text-black dark:text-white';
  const sign = tone === 'negative' && amount !== 0 ? '- ' : '';

  return (
    <div className="flex items-center justify-between py-2">
      <span className="flex items-center gap-2.5 text-sm text-slate-500 dark:text-slate-400">
        <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-white/5">{icon}</span>
        {label}
      </span>
      <span className={`text-sm font-bold ${valueTone}`}>{sign}{formatCurrency(amount)}</span>
    </div>
  );
}

export default function CashStatusModal({ token, onClose }: { token: string; onClose: () => void }) {
  const [session, setSession] = useState<CashSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    apiRequest<CashSessionResponse>('/cash-sessions/current', {}, token)
      .then((response) => {
        if (!cancelled) setSession(response.item);
      })
      .catch(() => {
        if (!cancelled) setError('No se pudo cargar el estado de la caja.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [token]);

  const summary = session?.summary;

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-[#0F9F37] to-emerald-500 px-5 py-4 text-white">
          <span className="flex items-center gap-2 text-sm font-black uppercase tracking-wide"><FiDollarSign /> Estado de caja</span>
          <button type="button" onClick={onClose} className="rounded-full p-1 hover:bg-white/10">
            <FiX />
          </button>
        </div>

        <div className="p-6">
          {loading && <p className="py-8 text-center text-sm text-slate-500">Cargando...</p>}
          {!loading && error && <p className="py-8 text-center text-sm text-red-500">{error}</p>}

          {!loading && !error && !session && (
            <div className="space-y-4 py-6 text-center">
              <p className="text-sm text-slate-500">No tienes ninguna caja abierta en este momento.</p>
              <Link
                to="/cash-register/desk"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex h-11 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-bold text-white hover:bg-opacity-90"
              >
                Abrir caja
              </Link>
            </div>
          )}

          {!loading && session && (
            <>
              <div className="flex flex-col gap-4 sm:flex-row">
                <div className="flex flex-1 items-center justify-between rounded-xl bg-slate-50 px-4 py-3 dark:bg-meta-4">
                  <div>
                    <p className="font-black text-black dark:text-white">{session.cash_register?.name}</p>
                    <p className="text-xs text-slate-500">Abierta: {formatDateTime(session.opened_at)}</p>
                  </div>
                  <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-bold text-green-700 dark:bg-green-900/40 dark:text-green-300">
                    <span className="h-2 w-2 rounded-full bg-green-500" /> ABIERTA
                  </span>
                </div>

                <div className="flex flex-1 items-center justify-between rounded-xl bg-gradient-to-r from-primary to-primary-dark px-4 py-4 text-white shadow-sm">
                  <span className="text-sm font-bold uppercase tracking-wide">Efectivo en caja ahora</span>
                  <span className="text-2xl font-black">{formatCurrency(summary?.balance_actual)}</span>
                </div>
              </div>

              <div className="mt-5 grid grid-cols-1 gap-x-6 lg:grid-cols-2">
                <div>
                  <p className="mb-1 text-xs font-bold uppercase tracking-wide text-slate-400">Movimientos de efectivo</p>
                  <div className="rounded-xl border border-stroke px-4 dark:border-strokedark">
                    <Row label="Monto inicial" value={summary?.opening_amount} icon={<FiDollarSign className="h-3.5 w-3.5" />} />
                    <div className="border-t border-stroke dark:border-strokedark" />
                    <Row label="Ventas en efectivo" value={summary?.cash_sales} icon={<FiTrendingUp className="h-3.5 w-3.5" />} tone="positive" />
                    <div className="border-t border-stroke dark:border-strokedark" />
                    <Row label="Ingresos por creditos" value={summary?.credit_collections} icon={<FiClock className="h-3.5 w-3.5" />} tone="positive" />
                    <div className="border-t border-stroke dark:border-strokedark" />
                    <Row label="Ingresos manuales" value={summary?.manual_income} icon={<FiArrowUpCircle className="h-3.5 w-3.5" />} tone="positive" />
                    <div className="border-t border-stroke dark:border-strokedark" />
                    <Row label="Egresos manuales" value={summary?.manual_expense} icon={<FiArrowDownCircle className="h-3.5 w-3.5" />} tone="negative" />
                    <div className="border-t border-stroke dark:border-strokedark" />
                    <Row label="Devoluciones" value={summary?.returns} icon={<FiCornerUpLeft className="h-3.5 w-3.5" />} tone="negative" />
                  </div>
                </div>

                <div>
                  <p className="mb-1 mt-5 text-xs font-bold uppercase tracking-wide text-slate-400 lg:mt-0">Otros medios de pago</p>
                  <div className="rounded-xl border border-stroke px-4 dark:border-strokedark">
                    <Row label="Ventas con tarjeta" value={summary?.card_sales} icon={<FiCreditCard className="h-3.5 w-3.5" />} />
                    <div className="border-t border-stroke dark:border-strokedark" />
                    <Row label="Transferencias" value={summary?.transfer_sales} icon={<FiRefreshCw className="h-3.5 w-3.5" />} />
                  </div>

                  <p className="mb-1 mt-5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-400">
                    <FiInfo className="h-3.5 w-3.5" /> Solo informativo — no afecta la caja
                  </p>
                  <div className="rounded-xl border border-dashed border-stroke bg-slate-50/60 px-4 dark:border-strokedark dark:bg-white/[0.02]">
                    <Row label="Ventas a credito de hoy" value={summary?.credit_sales} icon={<FiClock className="h-3.5 w-3.5" />} />
                    <div className="border-t border-dashed border-stroke dark:border-strokedark" />
                    <Row label="Descuentos otorgados hoy" value={summary?.discounts} icon={<FiPercent className="h-3.5 w-3.5" />} />
                  </div>
                </div>
              </div>

              <Link
                to="/cash-register/desk"
                target="_blank"
                rel="noopener noreferrer"
                className="mt-5 block text-center text-xs font-semibold text-primary hover:underline"
              >
                Ver panel completo de caja
              </Link>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
