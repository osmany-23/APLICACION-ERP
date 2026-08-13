import { FormEvent, useMemo, useState } from 'react';
import {
  FiBriefcase,
  FiCheckCircle,
  FiClock,
  FiEdit2,
  FiHome,
  FiKey,
  FiMail,
  FiPhone,
  FiShield,
  FiUser,
  FiX,
  FiXCircle,
} from 'react-icons/fi';
import Breadcrumb from '../components/Breadcrumbs/Breadcrumb';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';

// Vista de perfil real: antes mostraba datos de plantilla ("Danish Heilium",
// seguidores falsos, Lorem ipsum, redes sociales sin destino). Todo lo que
// se ve aqui sale de useAuth() (el mismo objeto que llena el header/sidebar)
// o de los dos endpoints de autoservicio nuevos (PUT /auth/me y
// POST /auth/change-password) — nada de datos de muestra.

function initialsFrom(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean).slice(0, 2);
  if (parts.length === 0) return 'U';
  return parts.map((part) => part[0]?.toUpperCase()).join('');
}

function formatDateTime(value: string | null) {
  if (!value) return 'Nunca';
  return new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function formatDate(value: string | null) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('es-NI', { dateStyle: 'long' }).format(new Date(value));
}

function formatMonthYear(value: string | null) {
  if (!value) return '-';
  return new Intl.DateTimeFormat('es-NI', { month: 'short', year: 'numeric' }).format(new Date(value));
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }
  return 'La solicitud no pudo completarse.';
}

const inputClass =
  'h-12 w-full rounded-lg border border-stroke bg-white px-4 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

function InfoCard({
  icon: Icon,
  label,
  value,
}: {
  icon: React.ComponentType<{ className?: string }>;
  label: string;
  value: string;
}) {
  return (
    <div className="flex items-start gap-3 rounded-lg border border-stroke p-4 dark:border-strokedark">
      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
        <Icon className="h-5 w-5" />
      </span>
      <div className="min-w-0">
        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</p>
        <p className="mt-0.5 truncate text-sm font-semibold text-black dark:text-white">{value}</p>
      </div>
    </div>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <h4 className="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">{title}</h4>
      {children}
    </div>
  );
}

const Profile = () => {
  const { user, token, refreshProfile } = useAuth();

  const [editOpen, setEditOpen] = useState(false);
  const [editForm, setEditForm] = useState({ full_name: '', email: '', phone: '' });
  const [editErrors, setEditErrors] = useState<Record<string, string>>({});
  const [editBusy, setEditBusy] = useState(false);

  const [passwordOpen, setPasswordOpen] = useState(false);
  const [passwordForm, setPasswordForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>({});
  const [passwordBusy, setPasswordBusy] = useState(false);

  const [notice, setNotice] = useState('');
  const [error, setError] = useState('');

  const permissions = user?.role?.permissions ?? [];
  const modules = useMemo(
    () => Array.from(new Set(permissions.map((permission) => permission.module).filter(Boolean))) as string[],
    [permissions],
  );

  if (!user) {
    return (
      <>
        <Breadcrumb pageName="Mi Perfil" />
        <div className="rounded-[10px] border border-stroke bg-white p-8 text-center text-sm text-slate-500 shadow-default dark:border-strokedark dark:bg-boxdark">
          Cargando perfil...
        </div>
      </>
    );
  }

  const displayName = user.full_name || user.username;
  const isActive = user.status === 1;

  function openEdit() {
    setEditForm({
      full_name: user!.full_name || '',
      email: user!.email || '',
      phone: user!.phone || '',
    });
    setEditErrors({});
    setError('');
    setEditOpen(true);
  }

  async function handleEditSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;

    setEditBusy(true);
    setError('');

    try {
      await apiRequest(
        '/auth/me',
        { method: 'PUT', body: JSON.stringify(editForm) },
        token,
      );
      await refreshProfile();
      setNotice('Perfil actualizado correctamente.');
      setEditOpen(false);
    } catch (saveError) {
      if (saveError instanceof ApiError && saveError.errors) {
        setEditErrors(
          Object.keys(saveError.errors).reduce<Record<string, string>>((acc, field) => ({ ...acc, [field]: saveError.errors?.[field]?.[0] ?? '' }), {}),
        );
      }
      setError(getErrorMessage(saveError));
    } finally {
      setEditBusy(false);
    }
  }

  function openPassword() {
    setPasswordForm({ current_password: '', new_password: '', new_password_confirmation: '' });
    setPasswordErrors({});
    setError('');
    setPasswordOpen(true);
  }

  async function handlePasswordSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!token) return;

    if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
      setPasswordErrors({ new_password_confirmation: 'La confirmacion no coincide.' });
      return;
    }

    setPasswordBusy(true);
    setError('');

    try {
      await apiRequest(
        '/auth/change-password',
        { method: 'POST', body: JSON.stringify(passwordForm) },
        token,
      );
      setNotice('Contrasena actualizada correctamente.');
      setPasswordOpen(false);
    } catch (saveError) {
      if (saveError instanceof ApiError && saveError.errors) {
        setPasswordErrors(
          Object.keys(saveError.errors).reduce<Record<string, string>>((acc, field) => ({ ...acc, [field]: saveError.errors?.[field]?.[0] ?? '' }), {}),
        );
      }
      setError(getErrorMessage(saveError));
    } finally {
      setPasswordBusy(false);
    }
  }

  return (
    <>
      <Breadcrumb pageName="Mi Perfil" />

      {notice && <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-600">{notice}</div>}
      {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

      <div className="rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        {/* Cabecera: sin banner de foto de portada (ahi vivia la linea dura
            que se veia mal) — el color de marca va dentro del avatar, que
            queda contenido en su propio circulo sin ningun corte recto de
            fondo detras. */}
        <div className="flex flex-col gap-6 border-b border-stroke p-6 sm:flex-row sm:items-center sm:justify-between dark:border-strokedark">
          <div className="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
            <span className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-gradient-brand text-2xl font-black text-white shadow-elevated sm:h-24 sm:w-24 sm:text-3xl">
              {initialsFrom(displayName)}
            </span>
            <div>
              <h3 className="text-2xl font-black text-black dark:text-white">{displayName}</h3>
              <p className="text-sm text-slate-500">@{user.username}</p>
              <div className="mt-2 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                {user.role && (
                  <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    <FiShield className="h-3.5 w-3.5" /> {user.role.name}
                  </span>
                )}
                <span
                  className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${
                    isActive
                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                      : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                  }`}
                >
                  {isActive ? <FiCheckCircle className="h-3.5 w-3.5" /> : <FiXCircle className="h-3.5 w-3.5" />}
                  {isActive ? 'Activo' : 'Inactivo'}
                </span>
              </div>
            </div>
          </div>
          <div className="flex gap-3">
            <button
              type="button"
              onClick={openPassword}
              className="inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2.5 text-sm font-bold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
            >
              <FiKey className="h-4 w-4" /> Cambiar contrasena
            </button>
            <button
              type="button"
              onClick={openEdit}
              className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white"
            >
              <FiEdit2 className="h-4 w-4" /> Editar perfil
            </button>
          </div>
        </div>

        <div className="space-y-8 p-6">
          {/* Resumen real: modulos con acceso, permisos totales, miembro desde
              — reemplaza los contadores falsos de "Posts/Followers" de la
              plantilla original. */}
          <div className="grid grid-cols-3 divide-x divide-stroke rounded-lg border border-stroke dark:divide-strokedark dark:border-strokedark dark:bg-meta-4">
            <div className="flex flex-col items-center gap-1 px-4 py-4">
              <span className="text-xl font-black text-black dark:text-white">{modules.length}</span>
              <span className="text-xs text-slate-500">Modulos con acceso</span>
            </div>
            <div className="flex flex-col items-center gap-1 px-4 py-4">
              <span className="text-xl font-black text-black dark:text-white">{permissions.length}</span>
              <span className="text-xs text-slate-500">Permisos totales</span>
            </div>
            <div className="flex flex-col items-center gap-1 px-4 py-4">
              <span className="text-xl font-black capitalize text-black dark:text-white">{formatMonthYear(user.created_at)}</span>
              <span className="text-xs text-slate-500">Miembro desde</span>
            </div>
          </div>

          <Section title="Informacion de contacto">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <InfoCard icon={FiMail} label="Correo" value={user.email || 'Sin registrar'} />
              <InfoCard icon={FiPhone} label="Telefono" value={user.phone || 'Sin registrar'} />
            </div>
          </Section>

          <Section title="Informacion laboral">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <InfoCard icon={FiBriefcase} label="Empresa" value={user.company?.name || 'Sin asignar'} />
              <InfoCard icon={FiHome} label="Sucursal" value={user.branch?.name || 'Sin asignar'} />
            </div>
            {user.role?.description && (
              <div className="mt-4 rounded-lg border border-stroke p-4 dark:border-strokedark">
                <h5 className="flex items-center gap-2 text-sm font-black text-black dark:text-white">
                  <FiShield className="h-4 w-4 text-primary" /> Sobre el rol "{user.role.name}"
                </h5>
                <p className="mt-2 text-sm text-slate-500">{user.role.description}</p>
              </div>
            )}
          </Section>

          <Section title="Actividad">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <InfoCard icon={FiClock} label="Ultimo acceso" value={formatDateTime(user.last_login)} />
              <InfoCard icon={FiUser} label="Miembro desde" value={formatDate(user.created_at)} />
            </div>
          </Section>

          {modules.length > 0 && (
            <Section title="Modulos con acceso">
              <div className="flex flex-wrap gap-2">
                {modules.sort().map((module) => (
                  <span
                    key={module}
                    className="rounded-full bg-gray-2 px-3 py-1.5 text-xs font-semibold capitalize text-black dark:bg-meta-4 dark:text-white"
                  >
                    {module.replace(/_/g, ' ')}
                  </span>
                ))}
              </div>
            </Section>
          )}
        </div>
      </div>

      {editOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form
            onSubmit={handleEditSubmit}
            className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">Editar perfil</p>
              <button type="button" onClick={() => setEditOpen(false)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Nombre completo</span>
                <input value={editForm.full_name} onChange={(event) => setEditForm((f) => ({ ...f, full_name: event.target.value }))} className={inputClass} />
                {editErrors.full_name && <span className="mt-2 block text-xs font-semibold text-red-500">{editErrors.full_name}</span>}
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Correo</span>
                <input value={editForm.email} onChange={(event) => setEditForm((f) => ({ ...f, email: event.target.value }))} className={inputClass} />
                {editErrors.email && <span className="mt-2 block text-xs font-semibold text-red-500">{editErrors.email}</span>}
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Telefono</span>
                <input value={editForm.phone} onChange={(event) => setEditForm((f) => ({ ...f, phone: event.target.value }))} className={inputClass} placeholder="8888-8888" />
                {editErrors.phone && <span className="mt-2 block text-xs font-semibold text-red-500">{editErrors.phone}</span>}
              </label>
              <p className="text-xs text-slate-500">
                El usuario, el rol y la sucursal solo puede cambiarlos un administrador desde Administracion / Usuarios.
              </p>
            </div>

            <div className="mt-7 flex justify-end gap-3">
              <button type="button" onClick={() => setEditOpen(false)} className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black hover:border-primary hover:text-primary">
                Cancelar
              </button>
              <button type="submit" disabled={editBusy} className="inline-flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white disabled:opacity-60">
                {editBusy ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </form>
        </div>
      )}

      {passwordOpen && (
        <div className="fixed inset-0 z-99999 flex items-center justify-center bg-black/50 px-4 py-6">
          <form
            onSubmit={handlePasswordSubmit}
            className="w-full max-w-lg rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark"
          >
            <div className="mb-6 flex items-center justify-between gap-4">
              <p className="text-xl font-black text-black dark:text-white">Cambiar contrasena</p>
              <button type="button" onClick={() => setPasswordOpen(false)} className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-stroke text-black hover:border-red-500 hover:text-red-500">
                <FiX className="h-5 w-5" />
              </button>
            </div>

            <div className="space-y-5">
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Contrasena actual</span>
                <input type="password" value={passwordForm.current_password} onChange={(event) => setPasswordForm((f) => ({ ...f, current_password: event.target.value }))} className={inputClass} />
                {passwordErrors.current_password && <span className="mt-2 block text-xs font-semibold text-red-500">{passwordErrors.current_password}</span>}
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Nueva contrasena</span>
                <input type="password" value={passwordForm.new_password} onChange={(event) => setPasswordForm((f) => ({ ...f, new_password: event.target.value }))} className={inputClass} placeholder="Minimo 8 caracteres" />
                {passwordErrors.new_password && <span className="mt-2 block text-xs font-semibold text-red-500">{passwordErrors.new_password}</span>}
              </label>
              <label className="block">
                <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Confirmar nueva contrasena</span>
                <input type="password" value={passwordForm.new_password_confirmation} onChange={(event) => setPasswordForm((f) => ({ ...f, new_password_confirmation: event.target.value }))} className={inputClass} />
                {passwordErrors.new_password_confirmation && <span className="mt-2 block text-xs font-semibold text-red-500">{passwordErrors.new_password_confirmation}</span>}
              </label>
            </div>

            <div className="mt-7 flex justify-end gap-3">
              <button type="button" onClick={() => setPasswordOpen(false)} className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black hover:border-primary hover:text-primary">
                Cancelar
              </button>
              <button type="submit" disabled={passwordBusy} className="inline-flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white disabled:opacity-60">
                {passwordBusy ? 'Guardando...' : 'Actualizar contrasena'}
              </button>
            </div>
          </form>
        </div>
      )}
    </>
  );
};

export default Profile;
