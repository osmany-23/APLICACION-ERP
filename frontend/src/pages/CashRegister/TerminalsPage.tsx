import { FormEvent, useCallback, useEffect, useState } from 'react';
import { FiEdit2, FiMonitor, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import ActionsMenu from '../../components/ActionsMenu';
import { CashRegister, CashRegisterListResponse, Terminal, TerminalListResponse } from '../../types/cashRegister';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function formatDateTime(value: string | null) {
  if (!value) return 'Nunca';
  return new Date(value).toLocaleString('es-NI', { dateStyle: 'medium', timeStyle: 'short' });
}

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

export default function TerminalsPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<Terminal[]>([]);
  const [registers, setRegisters] = useState<CashRegister[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [editing, setEditing] = useState<Terminal | null>(null);
  const [form, setForm] = useState({ name: '', cash_register_id: '', status: 'ACTIVA' as 'ACTIVA' | 'INACTIVA' });
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setError('');

    try {
      const [terminalsResponse, registersResponse] = await Promise.all([
        apiRequest<TerminalListResponse>('/terminals', {}, token),
        apiRequest<CashRegisterListResponse>('/cash-registers', {}, token),
      ]);
      setItems(terminalsResponse.data);
      setRegisters(registersResponse.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
  }, [loadItems]);

  function openEdit(terminal: Terminal) {
    setEditing(terminal);
    setForm({ name: terminal.name ?? '', cash_register_id: terminal.cash_register_id ? String(terminal.cash_register_id) : '', status: terminal.status });
    setFormError('');
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token || !editing) return;
    setSubmitting(true);
    setFormError('');

    try {
      await apiRequest(
        `/terminals/${editing.id}`,
        {
          method: 'PUT',
          body: JSON.stringify({
            name: form.name || undefined,
            cash_register_id: form.cash_register_id ? Number(form.cash_register_id) : null,
            status: form.status,
          }),
        },
        token,
      );
      setNotice('Terminal actualizada correctamente.');
      setEditing(null);
      await loadItems();
    } catch (err) {
      setFormError(getErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(terminal: Terminal) {
    if (!token) return;
    const confirmed = window.confirm(`¿Eliminar la terminal "${terminal.name ?? terminal.code}"?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<{ message: string }>(`/terminals/${terminal.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-title-md2 font-black uppercase text-black dark:text-white">Terminales</h1>
        <p className="mt-1 text-sm text-slate-500">
          Equipos que se han conectado al sistema para abrir caja. Se registran automaticamente al abrir una apertura; aqui puedes renombrarlos y opcionalmente bloquearlos a una caja especifica.
        </p>
      </div>

      {notice && <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="overflow-x-auto rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-4 py-3">Terminal</th>
                <th className="px-4 py-3">Sucursal</th>
                <th className="px-4 py-3">Caja asignada</th>
                <th className="px-4 py-3">Sistema / navegador</th>
                <th className="px-4 py-3">IP</th>
                <th className="px-4 py-3">Ultima conexion</th>
                <th className="px-4 py-3">Estado</th>
                <th className="px-4 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {items.map((terminal) => (
                <tr key={terminal.id} className="border-b border-stroke text-sm dark:border-strokedark">
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2 font-semibold text-black dark:text-white">
                      <FiMonitor className="text-slate-400" /> {terminal.name || terminal.code}
                    </div>
                    <div className="text-xs text-slate-500">{terminal.code}</div>
                  </td>
                  <td className="px-4 py-3">{terminal.branch_name ?? '-'}</td>
                  <td className="px-4 py-3">
                    {terminal.cash_register_name ? (
                      <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">{terminal.cash_register_name}</span>
                    ) : (
                      <span className="text-xs text-slate-400">Sin bloquear</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-500">{[terminal.os_info, terminal.browser_info].filter(Boolean).join(' / ') || '-'}</td>
                  <td className="px-4 py-3 text-xs text-slate-500">{terminal.ip_address ?? '-'}</td>
                  <td className="px-4 py-3 text-xs text-slate-500">{formatDateTime(terminal.last_seen_at)}</td>
                  <td className="px-4 py-3">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${terminal.status === 'ACTIVA' ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'}`}>
                      {terminal.status}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end">
                      <ActionsMenu
                        ariaLabel={`Mas opciones de ${terminal.name ?? terminal.code}`}
                        items={[
                          { label: 'Editar', icon: FiEdit2, onClick: () => openEdit(terminal) },
                          { label: 'Eliminar', icon: FiTrash2, variant: 'danger', onClick: () => void handleDelete(terminal) },
                        ]}
                      />
                    </div>
                  </td>
                </tr>
              ))}
              {items.length === 0 && (
                <tr>
                  <td colSpan={8} className="px-4 py-8 text-center text-sm text-slate-500">Todavia no se ha conectado ninguna terminal.</td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      {editing && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleSubmit} className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">Editar terminal</p>
              <button type="button" onClick={() => setEditing(null)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {formError && <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">{formError}</div>}

            <div className="space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Nombre</span>
                <input value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} placeholder="POS-001" />
              </label>

              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Caja asignada (candado)</span>
                <select value={form.cash_register_id} onChange={(event) => setForm((current) => ({ ...current, cash_register_id: event.target.value }))} className={inputClass}>
                  <option value="">Sin bloquear (puede abrir cualquier caja)</option>
                  {registers.map((register) => (
                    <option key={register.id} value={register.id}>{register.name}</option>
                  ))}
                </select>
              </label>

              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Estado</span>
                <select value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value as 'ACTIVA' | 'INACTIVA' }))} className={inputClass}>
                  <option value="ACTIVA">Activa</option>
                  <option value="INACTIVA">Inactiva</option>
                </select>
              </label>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={() => setEditing(null)} className="inline-flex h-11 items-center rounded-lg border border-stroke px-5 text-sm font-bold text-black dark:border-strokedark dark:text-white">
                Cancelar
              </button>
              <button type="submit" disabled={submitting} className="inline-flex h-11 items-center rounded-lg bg-primary px-5 text-sm font-bold text-white disabled:opacity-60">
                {submitting ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
