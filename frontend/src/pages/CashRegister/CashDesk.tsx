import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import {
  FiAlertTriangle,
  FiArchive,
  FiCheckCircle,
  FiClipboard,
  FiClock,
  FiCreditCard,
  FiDollarSign,
  FiLock,
  FiMinusCircle,
  FiPlusCircle,
  FiRefreshCw,
  FiTrash2,
  FiTrendingDown,
  FiTrendingUp,
  FiUser,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import ActionsMenu from '../../components/ActionsMenu';
import { hasCajaAction } from '../../utils/cashPermissions';
import {
  CashBreakdown,
  CashMovement,
  CashMovementListResponse,
  CashMovementReason,
  CashMovementReasonListResponse,
  CashMovementResponse,
  CashRegister,
  CashRegisterListResponse,
  CashSession,
  CashSessionResponse,
} from '../../types/cashRegister';

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

const DENOMINATIONS = [1000, 500, 200, 100, 50, 20, 10, 5, 1, 0.5];

const TERMINAL_STORAGE_KEY = 'erp_terminal_code';

function getTerminalCode(): string {
  let code = localStorage.getItem(TERMINAL_STORAGE_KEY);

  if (!code) {
    code = `TERM-${Math.random().toString(36).slice(2, 10).toUpperCase()}`;
    localStorage.setItem(TERMINAL_STORAGE_KEY, code);
  }

  return code;
}

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const textareaClass =
  'min-h-20 w-full rounded-lg border border-stroke bg-white px-4 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function Field({ label, children, hint }: { label: string; children: React.ReactNode; hint?: string }) {
  return (
    <label className="block">
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">{label}</span>
      {children}
      {hint && <span className="mt-1.5 block text-xs text-slate-500">{hint}</span>}
    </label>
  );
}

function DenominationBreakdown({
  breakdown,
  onChange,
}: {
  breakdown: CashBreakdown;
  onChange: (next: CashBreakdown) => void;
}) {
  const total = useMemo(
    () => DENOMINATIONS.reduce((sum, denom) => sum + denom * (breakdown[String(denom)] || 0), 0),
    [breakdown],
  );

  return (
    <div className="rounded-lg border border-dashed border-stroke p-4 dark:border-strokedark">
      <p className="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">
        Desglose de billetes y monedas (opcional)
      </p>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
        {DENOMINATIONS.map((denom) => (
          <label key={denom} className="block">
            <span className="mb-1 block text-xs font-semibold text-slate-500">
              {denom >= 1 ? `C$${denom}` : `C$${denom.toFixed(2)}`}
            </span>
            <input
              type="number"
              min="0"
              value={breakdown[String(denom)] ?? ''}
              onChange={(event) =>
                onChange({ ...breakdown, [String(denom)]: Number(event.target.value) || 0 })
              }
              className="h-10 w-full rounded-lg border border-stroke bg-white px-2 text-sm outline-none focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
              placeholder="0"
            />
          </label>
        ))}
      </div>
      <div className="mt-3 flex justify-between border-t border-stroke pt-3 text-sm font-bold text-black dark:border-strokedark dark:text-white">
        <span>Total del desglose</span>
        <span>{formatCurrency(total)}</span>
      </div>
    </div>
  );
}

function StatCard({
  label,
  value,
  icon,
  tone = 'default',
}: {
  label: string;
  value: string;
  icon: React.ReactNode;
  tone?: 'default' | 'positive' | 'negative' | 'primary';
}) {
  const toneClass = {
    default: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    positive: 'bg-[#E7F8ED] text-[#0F9F37]',
    negative: 'bg-red-50 text-red-500 dark:bg-red-900/20',
    primary: 'bg-[#E8F0FF] text-primary',
  }[tone];

  return (
    <div className="rounded-xl border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="flex items-center justify-between">
        <p className="text-xs font-bold uppercase tracking-wide text-slate-500">{label}</p>
        <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${toneClass}`}>{icon}</span>
      </div>
      <p className="mt-2 text-xl font-black text-black dark:text-white">{value}</p>
    </div>
  );
}

type MovementModalState = { type: 'INGRESO' | 'EGRESO' } | null;

export default function CashDesk() {
  const { token, user } = useAuth();
  const [session, setSession] = useState<CashSession | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [registers, setRegisters] = useState<CashRegister[]>([]);
  const [openForm, setOpenForm] = useState({ cash_register_id: '', opening_amount: '', opening_note: '' });
  const [openBreakdown, setOpenBreakdown] = useState<CashBreakdown>({});
  const [useBreakdown, setUseBreakdown] = useState(false);
  const [submittingOpen, setSubmittingOpen] = useState(false);
  const [openError, setOpenError] = useState('');

  const [movements, setMovements] = useState<CashMovement[]>([]);
  const [reasons, setReasons] = useState<CashMovementReason[]>([]);
  const [movementModal, setMovementModal] = useState<MovementModalState>(null);
  const [movementForm, setMovementForm] = useState({ amount: '', reason_id: '', reason_text: '', reference: '', observation: '' });
  const [movementError, setMovementError] = useState('');
  const [submittingMovement, setSubmittingMovement] = useState(false);

  const [closeModalOpen, setCloseModalOpen] = useState(false);
  const [closeForm, setCloseForm] = useState({ counted_cash: '', closing_note: '' });
  const [closeBreakdown, setCloseBreakdown] = useState<CashBreakdown>({});
  const [closeError, setCloseError] = useState('');
  const [submittingClose, setSubmittingClose] = useState(false);

  const canAddIncome = hasCajaAction(user, 'agregar_efectivo');
  const canWithdraw = hasCajaAction(user, 'retirar_efectivo');
  const canClose = hasCajaAction(user, 'cerrar');
  const canCancelMovement = hasCajaAction(user, 'anular_movimiento');
  const canAuthorize = hasCajaAction(user, 'autorizar_movimiento');

  const loadCurrent = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<CashSessionResponse>('/cash-sessions/current', {}, token);
      setSession(response.item);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [token]);

  const loadRegisters = useCallback(async () => {
    if (!token) return;

    try {
      const response = await apiRequest<CashRegisterListResponse>('/cash-registers?status=ACTIVA', {}, token);
      setRegisters(response.data.filter((register) => register.can_operate && !register.is_open));
    } catch {
      // El selector simplemente queda vacio; el error principal ya se maneja en loadCurrent.
    }
  }, [token]);

  const loadMovements = useCallback(async (sessionId: number) => {
    if (!token) return;

    try {
      const response = await apiRequest<CashMovementListResponse>(`/cash-movements?cash_session_id=${sessionId}`, {}, token);
      setMovements(response.data);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }, [token]);

  const loadReasons = useCallback(async () => {
    if (!token) return;

    try {
      const response = await apiRequest<CashMovementReasonListResponse>('/cash-movement-reasons', {}, token);
      setReasons(response.data.filter((reason) => reason.is_active));
    } catch {
      // El formulario simplemente exige texto libre si no hay catalogo.
    }
  }, [token]);

  useEffect(() => {
    void loadCurrent();
    void loadReasons();
  }, [loadCurrent, loadReasons]);

  useEffect(() => {
    if (!session) {
      void loadRegisters();
      return;
    }

    void loadMovements(session.id);
  }, [session, loadRegisters, loadMovements]);

  async function handleOpen(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setOpenError('');
    setSubmittingOpen(true);

    try {
      const breakdownTotal = DENOMINATIONS.reduce((sum, denom) => sum + denom * (openBreakdown[String(denom)] || 0), 0);

      const response = await apiRequest<CashSessionResponse>(
        '/cash-sessions/open',
        {
          method: 'POST',
          body: JSON.stringify({
            cash_register_id: Number(openForm.cash_register_id),
            opening_amount: useBreakdown ? breakdownTotal : Number(openForm.opening_amount || 0),
            opening_breakdown: useBreakdown && Object.keys(openBreakdown).length ? openBreakdown : undefined,
            opening_note: openForm.opening_note || undefined,
            terminal_code: getTerminalCode(),
            terminal_name: navigator.platform || undefined,
            device_name: navigator.userAgent.slice(0, 100),
          }),
        },
        token,
      );

      setSession(response.item);
      setNotice('Caja abierta correctamente.');
      setOpenForm({ cash_register_id: '', opening_amount: '', opening_note: '' });
      setOpenBreakdown({});
    } catch (err) {
      setOpenError(getErrorMessage(err));
    } finally {
      setSubmittingOpen(false);
    }
  }

  function openMovementModal(type: 'INGRESO' | 'EGRESO') {
    setMovementModal({ type });
    setMovementForm({ amount: '', reason_id: '', reason_text: '', reference: '', observation: '' });
    setMovementError('');
  }

  async function handleMovementSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token || !session || !movementModal) return;
    setMovementError('');
    setSubmittingMovement(true);

    try {
      const response = await apiRequest<CashMovementResponse>(
        '/cash-movements',
        {
          method: 'POST',
          body: JSON.stringify({
            cash_session_id: session.id,
            type: movementModal.type,
            amount: Number(movementForm.amount || 0),
            reason_id: movementForm.reason_id || undefined,
            reason_text: movementForm.reason_text || undefined,
            reference: movementForm.reference || undefined,
            observation: movementForm.observation || undefined,
          }),
        },
        token,
      );

      setNotice(response.message || 'Movimiento registrado.');
      setMovementModal(null);
      await Promise.all([loadCurrent(), loadMovements(session.id)]);
    } catch (err) {
      setMovementError(getErrorMessage(err));
    } finally {
      setSubmittingMovement(false);
    }
  }

  async function handleCancelMovement(movement: CashMovement) {
    if (!token || !session) return;
    const reason = window.prompt('Motivo de la anulacion:');
    if (reason === null) return;
    if (reason.trim() === '') {
      setError('Debes indicar el motivo de la anulacion.');
      return;
    }

    try {
      await apiRequest(`/cash-movements/${movement.id}/cancel`, { method: 'POST', body: JSON.stringify({ reason }) }, token);
      setNotice('Movimiento anulado.');
      await Promise.all([loadCurrent(), loadMovements(session.id)]);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  async function handleAuthorizeMovement(movement: CashMovement, approve: boolean) {
    if (!token || !session) return;

    try {
      await apiRequest(
        `/cash-movements/${movement.id}/${approve ? 'authorize' : 'reject'}`,
        { method: 'POST', body: approve ? undefined : JSON.stringify({}) },
        token,
      );
      setNotice(approve ? 'Movimiento autorizado.' : 'Movimiento rechazado.');
      await Promise.all([loadCurrent(), loadMovements(session.id)]);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  async function handleClose(event: FormEvent) {
    event.preventDefault();
    if (!token || !session) return;
    setCloseError('');
    setSubmittingClose(true);

    try {
      const breakdownTotal = DENOMINATIONS.reduce((sum, denom) => sum + denom * (closeBreakdown[String(denom)] || 0), 0);
      const countedCash = Object.keys(closeBreakdown).length ? breakdownTotal : Number(closeForm.counted_cash || 0);

      const response = await apiRequest<CashSessionResponse>(
        `/cash-sessions/${session.id}/close`,
        {
          method: 'POST',
          body: JSON.stringify({
            counted_cash: countedCash,
            closing_breakdown: Object.keys(closeBreakdown).length ? closeBreakdown : undefined,
            closing_note: closeForm.closing_note || undefined,
          }),
        },
        token,
      );

      setNotice(`Caja cerrada. Diferencia: ${formatCurrency(response.item?.cash_difference ?? 0)}`);
      setCloseModalOpen(false);
      setCloseForm({ counted_cash: '', closing_note: '' });
      setCloseBreakdown({});
      setSession(null);
      await loadCurrent();
    } catch (err) {
      setCloseError(getErrorMessage(err));
    } finally {
      setSubmittingClose(false);
    }
  }

  const summary = session?.summary;
  const pendingCount = summary?.pending_authorization_count ?? 0;

  if (loading) {
    return (
      <div className="rounded-lg border border-dashed border-stroke p-12 text-center text-sm text-slate-500">
        Cargando estado de caja...
      </div>
    );
  }

  if (error && !session) {
    return <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>;
  }

  if (!session) {
    return (
      <div className="mx-auto max-w-2xl space-y-5">
        <div>
          <h1 className="text-title-md2 font-black uppercase text-black dark:text-white">Apertura de caja</h1>
          <p className="mt-1 text-sm text-slate-500">Selecciona una caja disponible e ingresa el fondo inicial para comenzar tu turno.</p>
        </div>

        {notice && <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}

        {registers.length === 0 ? (
          <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500 dark:border-strokedark">
            No tienes ninguna caja disponible para abrir. Puede que ya esten todas abiertas o que no tengas autorizacion sobre ellas.
          </div>
        ) : (
          <form onSubmit={handleOpen} className="space-y-5 rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            {openError && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{openError}</div>}

            <Field label="Caja">
              <select
                value={openForm.cash_register_id}
                onChange={(event) => setOpenForm((current) => ({ ...current, cash_register_id: event.target.value }))}
                className={inputClass}
                required
              >
                <option value="" disabled hidden>Selecciona una caja</option>
                {registers.map((register) => (
                  <option key={register.id} value={register.id}>
                    {register.name} ({register.branch_name})
                  </option>
                ))}
              </select>
            </Field>

            <label className="flex items-center gap-2 text-sm font-semibold text-black dark:text-white">
              <input type="checkbox" checked={useBreakdown} onChange={(event) => setUseBreakdown(event.target.checked)} className="h-4 w-4 rounded border-stroke text-primary" />
              Ingresar el fondo inicial desglosado por billetes/monedas
            </label>

            {useBreakdown ? (
              <DenominationBreakdown breakdown={openBreakdown} onChange={setOpenBreakdown} />
            ) : (
              <Field label="Efectivo inicial">
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={openForm.opening_amount}
                  onChange={(event) => setOpenForm((current) => ({ ...current, opening_amount: event.target.value }))}
                  className={inputClass}
                  placeholder="0.00"
                  required
                />
              </Field>
            )}

            <Field label="Nota" hint="Opcional: cualquier comentario sobre esta apertura.">
              <textarea
                value={openForm.opening_note}
                onChange={(event) => setOpenForm((current) => ({ ...current, opening_note: event.target.value }))}
                className={textareaClass}
                placeholder="Ej. Turno de la manana"
              />
            </Field>

            <button
              type="submit"
              disabled={submittingOpen || !openForm.cash_register_id}
              className="inline-flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-primary text-sm font-bold text-white transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            >
              <FiLock /> {submittingOpen ? 'Abriendo...' : 'Abrir caja'}
            </button>
          </form>
        )}
      </div>
    );
  }

  return (
    <div className="space-y-5">
      {notice && (
        <div className="flex items-center justify-between rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">
          {notice}
          <button type="button" onClick={() => setNotice('')}><FiX /></button>
        </div>
      )}
      {error && (
        <div className="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">
          {error}
          <button type="button" onClick={() => setError('')}><FiX /></button>
        </div>
      )}

      <div className="rounded-[10px] border border-stroke bg-gradient-to-r from-primary to-[#3C50E0] p-6 text-white shadow-default">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-black">{session.cash_register?.name}</h1>
              <span className="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-bold">
                <span className="h-2 w-2 rounded-full bg-[#34D399]" /> CAJA ABIERTA
              </span>
            </div>
            <p className="mt-1 text-sm text-white/80">{session.branch?.name}</p>
          </div>
          <div className="grid grid-cols-2 gap-x-8 gap-y-1 text-sm text-white/90 sm:grid-cols-4">
            <div>
              <p className="text-xs uppercase text-white/60">Abierta</p>
              <p className="font-semibold">{formatDateTime(session.opened_at)}</p>
            </div>
            <div>
              <p className="text-xs uppercase text-white/60">Usuario</p>
              <p className="font-semibold">{session.opened_by?.full_name ?? '-'}</p>
            </div>
            <div>
              <p className="text-xs uppercase text-white/60">Terminal</p>
              <p className="font-semibold">{session.terminal?.name ?? '-'}</p>
            </div>
            <div>
              <p className="text-xs uppercase text-white/60">Sesion #</p>
              <p className="font-semibold">{session.id}</p>
            </div>
          </div>
        </div>
      </div>

      {pendingCount > 0 && (
        <div className="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
          <FiAlertTriangle /> Hay {pendingCount} movimiento(s) pendiente(s) de autorizacion. La caja no podra cerrarse hasta resolverlos.
        </div>
      )}

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
        <StatCard label="Monto inicial" value={formatCurrency(summary?.opening_amount)} icon={<FiDollarSign />} />
        <StatCard label="Ventas efectivo" value={formatCurrency(summary?.cash_sales)} icon={<FiTrendingUp />} tone="positive" />
        <StatCard label="Ventas tarjeta" value={formatCurrency(summary?.card_sales)} icon={<FiCreditCard />} />
        <StatCard label="Transferencias" value={formatCurrency(summary?.transfer_sales)} icon={<FiRefreshCw />} />
        <StatCard label="Otros medios" value={formatCurrency(summary?.other_sales)} icon={<FiArchive />} />
        <StatCard label="Ventas a credito" value={formatCurrency(summary?.credit_sales)} icon={<FiClock />} />
        <StatCard label="Ingresos manuales" value={formatCurrency(summary?.manual_income)} icon={<FiPlusCircle />} tone="positive" />
        <StatCard label="Egresos manuales" value={formatCurrency(summary?.manual_expense)} icon={<FiMinusCircle />} tone="negative" />
        <StatCard label="Descuentos" value={formatCurrency(summary?.discounts)} icon={<FiTrendingDown />} />
        <StatCard label="Impuestos" value={formatCurrency(summary?.taxes)} icon={<FiArchive />} />
        <StatCard label="Costo de mercancia" value={formatCurrency(summary?.cost_of_goods)} icon={<FiArchive />} />
        <StatCard label="Utilidad bruta" value={formatCurrency(summary?.gross_profit)} icon={<FiTrendingUp />} tone="positive" />
        <StatCard label="Utilidad neta" value={formatCurrency(summary?.net_profit)} icon={<FiTrendingUp />} tone="primary" />
      </div>

      <div className="rounded-[10px] border-2 border-primary bg-[#E8F0FF] p-6 dark:bg-primary/10">
        <p className="text-xs font-black uppercase tracking-wide text-primary">Balance actual (efectivo en caja)</p>
        <p className="mt-1 text-3xl font-black text-primary">{formatCurrency(summary?.balance_actual)}</p>
      </div>

      <div className="flex flex-wrap gap-3">
        {canAddIncome && (
          <button type="button" onClick={() => openMovementModal('INGRESO')} className="inline-flex h-12 items-center gap-2 rounded-lg border border-[#0F9F37] px-5 text-sm font-bold text-[#0F9F37] transition hover:bg-[#0F9F37] hover:text-white">
            <FiPlusCircle /> Añadir efectivo
          </button>
        )}
        {canWithdraw && (
          <button type="button" onClick={() => openMovementModal('EGRESO')} className="inline-flex h-12 items-center gap-2 rounded-lg border border-amber-500 px-5 text-sm font-bold text-amber-600 transition hover:bg-amber-500 hover:text-white">
            <FiMinusCircle /> Retirar efectivo
          </button>
        )}
        {canClose && (
          <button type="button" onClick={() => setCloseModalOpen(true)} className="ml-auto inline-flex h-12 items-center gap-2 rounded-lg bg-red-500 px-5 text-sm font-bold text-white transition hover:bg-red-600">
            <FiXCircle /> Cerrar caja
          </button>
        )}
      </div>

      <div className="rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="border-b border-stroke px-6 py-4 dark:border-strokedark">
          <h2 className="text-lg font-black text-black dark:text-white">Movimientos de esta apertura</h2>
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
                <th className="px-4 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {movements.map((movement) => (
                <tr key={movement.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-4 py-3">{formatDateTime(movement.created_at)}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ${movement.type === 'INGRESO' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'}`}>
                      {movement.type === 'INGRESO' ? <FiPlusCircle /> : <FiMinusCircle />} {movement.type}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div>{movement.reason_name ?? 'Otro'}</div>
                    {movement.reason_text && <div className="text-xs text-slate-500">{movement.reason_text}</div>}
                  </td>
                  <td className="px-4 py-3 text-right font-semibold">{formatCurrency(movement.amount)}</td>
                  <td className="px-4 py-3">
                    <span className="inline-flex items-center gap-1 text-xs text-slate-500"><FiUser className="h-3 w-3" /> {movement.user?.full_name}</span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                      movement.status === 'ACTIVO' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                      : movement.status === 'PENDIENTE_AUTORIZACION' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                      : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                    }`}>
                      {movement.status.replace('_', ' ')}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones del movimiento ${movement.id}`}
                        disabled={movement.status !== 'ACTIVO' && movement.status !== 'PENDIENTE_AUTORIZACION'}
                        items={[
                          ...(movement.status === 'PENDIENTE_AUTORIZACION' && canAuthorize
                            ? [
                                { label: 'Autorizar', icon: FiCheckCircle, onClick: () => void handleAuthorizeMovement(movement, true) },
                                { label: 'Rechazar', icon: FiXCircle, variant: 'danger' as const, onClick: () => void handleAuthorizeMovement(movement, false) },
                              ]
                            : []),
                          ...(movement.status === 'ACTIVO' && canCancelMovement
                            ? [{ label: 'Anular', icon: FiTrash2, variant: 'danger' as const, onClick: () => void handleCancelMovement(movement) }]
                            : []),
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {movements.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-4 py-8 text-center text-sm text-slate-500">Todavia no hay movimientos registrados en esta apertura.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {movementModal && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleMovementSubmit} className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">
                {movementModal.type === 'INGRESO' ? 'Añadir efectivo' : 'Retirar efectivo'}
              </p>
              <button type="button" onClick={() => setMovementModal(null)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {movementError && <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">{movementError}</div>}

            <div className="space-y-5">
              <Field label="Monto">
                <input
                  type="number" min="0.01" step="0.01" required
                  value={movementForm.amount}
                  onChange={(event) => setMovementForm((current) => ({ ...current, amount: event.target.value }))}
                  className={inputClass}
                  placeholder="0.00"
                />
              </Field>

              <Field label="Motivo">
                <select
                  value={movementForm.reason_id}
                  onChange={(event) => setMovementForm((current) => ({ ...current, reason_id: event.target.value }))}
                  className={inputClass}
                >
                  <option value="">Sin motivo del catalogo (usar texto libre)</option>
                  {reasons.filter((reason) => reason.type === movementModal.type).map((reason) => (
                    <option key={reason.id} value={reason.id}>{reason.name}</option>
                  ))}
                </select>
              </Field>

              <Field label="Detalle del motivo" hint="Obligatorio si no seleccionaste un motivo del catalogo, o si el motivo elegido lo exige.">
                <input
                  value={movementForm.reason_text}
                  onChange={(event) => setMovementForm((current) => ({ ...current, reason_text: event.target.value }))}
                  className={inputClass}
                  placeholder="Ej. Cambio para caja chica"
                />
              </Field>

              <Field label="Referencia" hint="Opcional: numero de recibo, factura, etc.">
                <input
                  value={movementForm.reference}
                  onChange={(event) => setMovementForm((current) => ({ ...current, reference: event.target.value }))}
                  className={inputClass}
                />
              </Field>

              <Field label="Observacion">
                <textarea
                  value={movementForm.observation}
                  onChange={(event) => setMovementForm((current) => ({ ...current, observation: event.target.value }))}
                  className={textareaClass}
                />
              </Field>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={() => setMovementModal(null)} className="inline-flex h-11 items-center rounded-lg border border-stroke px-5 text-sm font-bold text-black dark:border-strokedark dark:text-white">
                Cancelar
              </button>
              <button type="submit" disabled={submittingMovement} className="inline-flex h-11 items-center rounded-lg bg-primary px-5 text-sm font-bold text-white disabled:opacity-60">
                {submittingMovement ? 'Guardando...' : 'Registrar movimiento'}
              </button>
            </div>
          </form>
        </div>
      )}

      {closeModalOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleClose} className="max-h-full w-full max-w-2xl overflow-y-auto rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">Cerrar caja</p>
              <button type="button" onClick={() => setCloseModalOpen(false)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {closeError && <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">{closeError}</div>}

            <div className="mb-5 grid grid-cols-2 gap-4">
              <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
                <p className="text-xs font-bold uppercase text-slate-500">Efectivo esperado</p>
                <p className="mt-1 text-xl font-black text-black dark:text-white">{formatCurrency(summary?.balance_actual)}</p>
              </div>
              <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
                <p className="text-xs font-bold uppercase text-slate-500">Diferencia estimada</p>
                <p className="mt-1 text-xl font-black text-black dark:text-white">
                  {formatCurrency(
                    (Object.keys(closeBreakdown).length
                      ? DENOMINATIONS.reduce((sum, denom) => sum + denom * (closeBreakdown[String(denom)] || 0), 0)
                      : Number(closeForm.counted_cash || 0)) - (summary?.balance_actual ?? 0),
                  )}
                </p>
              </div>
            </div>

            <div className="space-y-5">
              <DenominationBreakdown breakdown={closeBreakdown} onChange={setCloseBreakdown} />

              <Field label="Efectivo contado (si no usas el desglose de arriba)">
                <input
                  type="number" min="0" step="0.01"
                  value={closeForm.counted_cash}
                  onChange={(event) => setCloseForm((current) => ({ ...current, counted_cash: event.target.value }))}
                  className={inputClass}
                  placeholder="0.00"
                />
              </Field>

              <Field label="Nota de cierre">
                <textarea
                  value={closeForm.closing_note}
                  onChange={(event) => setCloseForm((current) => ({ ...current, closing_note: event.target.value }))}
                  className={textareaClass}
                  placeholder="Ej. Faltante justificado por..."
                />
              </Field>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={() => setCloseModalOpen(false)} className="inline-flex h-11 items-center rounded-lg border border-stroke px-5 text-sm font-bold text-black dark:border-strokedark dark:text-white">
                Cancelar
              </button>
              <button type="submit" disabled={submittingClose} className="inline-flex h-11 items-center gap-2 rounded-lg bg-red-500 px-5 text-sm font-bold text-white disabled:opacity-60">
                <FiClipboard /> {submittingClose ? 'Cerrando...' : 'Confirmar cierre'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
