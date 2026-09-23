import { FormEvent, useCallback, useEffect, useState } from 'react';
import { FiEdit2, FiLock, FiMinusCircle, FiPlus, FiPlusCircle, FiTrash2, FiX } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { SwitchField } from '../../components/SwitchField';
import ActionsMenu from '../../components/ActionsMenu';
import { CashMovementReason, CashMovementReasonListResponse, CashMovementType } from '../../types/cashRegister';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

type FormState = { type: CashMovementType; name: string; requires_note: boolean };
const emptyForm: FormState = { type: 'INGRESO', name: '', requires_note: false };

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function ReasonList({
  title,
  icon,
  reasons,
  onEdit,
  onDelete,
  onToggleActive,
}: {
  title: string;
  icon: React.ReactNode;
  reasons: CashMovementReason[];
  onEdit: (reason: CashMovementReason) => void;
  onDelete: (reason: CashMovementReason) => void;
  onToggleActive: (reason: CashMovementReason) => void;
}) {
  return (
    <div className="rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="flex items-center gap-2 border-b border-stroke px-6 py-4 dark:border-strokedark">
        {icon}
        <h2 className="text-lg font-black text-black dark:text-white">{title}</h2>
      </div>
      <div className="divide-y divide-stroke dark:divide-strokedark">
        {reasons.map((reason) => (
          <div key={reason.id} className="flex items-center justify-between gap-3 px-6 py-3">
            <div>
              <p className="text-sm font-semibold text-black dark:text-white">
                {reason.name}
                {reason.is_system && <FiLock className="ml-2 inline h-3.5 w-3.5 text-slate-400" title="Motivo del sistema" />}
              </p>
              {reason.requires_note && <p className="text-xs text-slate-500">Exige observacion obligatoria</p>}
            </div>
            <div className="flex items-center gap-3">
              <SwitchField checked={reason.is_active} onChange={() => onToggleActive(reason)} label="Activo" />
              {!reason.is_system && (
                <ActionsMenu
                  ariaLabel={`Mas opciones de ${reason.name}`}
                  items={[
                    { label: 'Editar', icon: FiEdit2, onClick: () => onEdit(reason) },
                    { label: 'Eliminar', icon: FiTrash2, variant: 'danger', onClick: () => onDelete(reason) },
                  ]}
                />
              )}
            </div>
          </div>
        ))}
        {reasons.length === 0 && <p className="px-6 py-6 text-center text-sm text-slate-500">No hay motivos configurados.</p>}
      </div>
    </div>
  );
}

export default function CashMovementReasonsPage() {
  const { token } = useAuth();
  const [items, setItems] = useState<CashMovementReason[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<CashMovementReason | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadItems = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setError('');

    try {
      const response = await apiRequest<CashMovementReasonListResponse>('/cash-movement-reasons', {}, token);
      setItems(response.data);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadItems();
  }, [loadItems]);

  function openCreate(type: CashMovementType) {
    setEditing(null);
    setForm({ ...emptyForm, type });
    setFormError('');
    setDialogOpen(true);
  }

  function openEdit(reason: CashMovementReason) {
    setEditing(reason);
    setForm({ type: reason.type, name: reason.name, requires_note: reason.requires_note });
    setFormError('');
    setDialogOpen(true);
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setSubmitting(true);
    setFormError('');

    try {
      const response = editing
        ? await apiRequest<{ message: string }>(`/cash-movement-reasons/${editing.id}`, { method: 'PUT', body: JSON.stringify(form) }, token)
        : await apiRequest<{ message: string }>('/cash-movement-reasons', { method: 'POST', body: JSON.stringify(form) }, token);

      setNotice(response.message);
      setDialogOpen(false);
      await loadItems();
    } catch (err) {
      setFormError(getErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleToggleActive(reason: CashMovementReason) {
    if (!token || reason.is_system) return;

    try {
      await apiRequest(`/cash-movement-reasons/${reason.id}`, { method: 'PUT', body: JSON.stringify({ type: reason.type, name: reason.name, requires_note: reason.requires_note, is_active: !reason.is_active }) }, token);
      await loadItems();
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  async function handleDelete(reason: CashMovementReason) {
    if (!token) return;
    const confirmed = window.confirm(`¿Eliminar el motivo "${reason.name}"?`);
    if (!confirmed) return;

    try {
      const response = await apiRequest<{ message: string }>(`/cash-movement-reasons/${reason.id}`, { method: 'DELETE' }, token);
      setNotice(response.message);
      await loadItems();
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  const ingresos = items.filter((item) => item.type === 'INGRESO');
  const egresos = items.filter((item) => item.type === 'EGRESO');

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-title-md2 font-black uppercase text-black dark:text-white">Motivos de movimiento</h1>
          <p className="mt-1 text-sm text-slate-500">Catalogo de razones validas para registrar entradas y salidas manuales de efectivo.</p>
        </div>
        <div className="flex gap-3">
          <button type="button" onClick={() => openCreate('INGRESO')} className="inline-flex h-11 items-center gap-2 rounded-lg border border-[#0F9F37] px-4 text-sm font-bold text-[#0F9F37] hover:bg-[#0F9F37] hover:text-white">
            <FiPlus /> Motivo de ingreso
          </button>
          <button type="button" onClick={() => openCreate('EGRESO')} className="inline-flex h-11 items-center gap-2 rounded-lg border border-amber-500 px-4 text-sm font-bold text-amber-600 hover:bg-amber-500 hover:text-white">
            <FiPlus /> Motivo de egreso
          </button>
        </div>
      </div>

      {notice && <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}
      {error && <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

      {loading ? (
        <div className="rounded-lg border border-dashed border-stroke p-8 text-center text-sm text-slate-500">Cargando...</div>
      ) : (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <ReasonList title="Ingresos" icon={<FiPlusCircle className="text-[#0F9F37]" />} reasons={ingresos} onEdit={openEdit} onDelete={handleDelete} onToggleActive={handleToggleActive} />
          <ReasonList title="Egresos" icon={<FiMinusCircle className="text-amber-500" />} reasons={egresos} onEdit={openEdit} onDelete={handleDelete} onToggleActive={handleToggleActive} />
        </div>
      )}

      {dialogOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form onSubmit={handleSubmit} className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">{editing ? 'Editar motivo' : 'Nuevo motivo'}</p>
              <button type="button" onClick={() => setDialogOpen(false)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            {formError && <div className="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-500">{formError}</div>}

            <div className="space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Tipo</span>
                <select value={form.type} onChange={(event) => setForm((current) => ({ ...current, type: event.target.value as CashMovementType }))} className={inputClass}>
                  <option value="INGRESO">Ingreso</option>
                  <option value="EGRESO">Egreso</option>
                </select>
              </label>

              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Nombre</span>
                <input value={form.name} onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))} className={inputClass} placeholder="Ej. Compra urgente" required />
              </label>

              <label className="flex items-center gap-2 text-sm font-semibold text-black dark:text-white">
                <input
                  type="checkbox"
                  checked={form.requires_note}
                  onChange={(event) => setForm((current) => ({ ...current, requires_note: event.target.checked }))}
                  className="h-4 w-4 rounded border-stroke text-primary"
                />
                Exigir observacion obligatoria al usarlo
              </label>
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button type="button" onClick={() => setDialogOpen(false)} className="inline-flex h-11 items-center rounded-lg border border-stroke px-5 text-sm font-bold text-black dark:border-strokedark dark:text-white">
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
