import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import {
  FiAlertCircle,
  FiCheckCircle,
  FiEye,
  FiEyeOff,
  FiLoader,
  FiLock,
  FiShield,
  FiUser,
} from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError } from '../../services/api';
import CoverImage from '../../images/cover/cover-01.png';
import LogoDark from '../../images/logo/logo-dark.svg';

type FormState = {
  username: string;
  password: string;
  remember: boolean;
};

type FormErrors = Partial<Record<keyof FormState | 'form', string>>;

const usernamePattern = /^[A-Za-z0-9._@-]+$/;

function validateForm(form: FormState): FormErrors {
  const errors: FormErrors = {};
  const username = form.username.trim();

  if (!username) {
    errors.username = 'Ingresa tu usuario o correo.';
  } else if (username.length < 3) {
    errors.username = 'Debe tener al menos 3 caracteres.';
  } else if (!usernamePattern.test(username)) {
    errors.username = 'Usa solo letras, numeros, punto, guion, guion bajo o @.';
  }

  if (!form.password) {
    errors.password = 'Ingresa tu contrasena.';
  } else if (form.password.length < 8) {
    errors.password = 'Debe tener al menos 8 caracteres.';
  }

  return errors;
}

const SignIn = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();
  const [form, setForm] = useState<FormState>({
    username: '',
    password: '',
    remember: true,
  });
  const [errors, setErrors] = useState<FormErrors>({});
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [lockSeconds, setLockSeconds] = useState(0);

  const redirectTo = useMemo(() => {
    const state = location.state as { from?: string } | null;

    return state?.from || '/';
  }, [location.state]);

  const canSubmit = !submitting && lockSeconds === 0;
  const passwordReady = form.password.length >= 8;

  useEffect(() => {
    if (lockSeconds <= 0) {
      return;
    }

    const timer = window.setInterval(() => {
      setLockSeconds((seconds) => Math.max(seconds - 1, 0));
    }, 1000);

    return () => window.clearInterval(timer);
  }, [lockSeconds]);

  function updateField<T extends keyof FormState>(field: T, value: FormState[T]) {
    setForm((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: undefined, form: undefined }));
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const nextErrors = validateForm(form);

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    setSubmitting(true);
    setErrors({});

    try {
      await login({
        username: form.username.trim(),
        password: form.password,
        remember: form.remember,
      });

      navigate(redirectTo, { replace: true });
    } catch (error) {
      if (error instanceof ApiError) {
        const validationErrors = error.errors
          ? Object.entries(error.errors).reduce<FormErrors>(
              (mappedErrors, [field, messages]) => ({
                ...mappedErrors,
                [field]: messages[0],
              }),
              {},
            )
          : {};

        if (error.status === 429) {
          setLockSeconds(error.retryAfter || 60);
        }

        setErrors({
          ...validationErrors,
          form: error.message,
        });
      } else {
        setErrors({
          form: 'No se pudo iniciar sesion. Intenta nuevamente.',
        });
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="min-h-screen bg-slate-50 text-slate-900 dark:bg-boxdark-2 dark:text-white">
      <div className="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
        <section className="relative hidden overflow-hidden lg:block">
          <img
            src={CoverImage}
            alt="Sistema ERP"
            className="absolute inset-0 h-full w-full object-cover"
          />
          <div className="absolute inset-0 bg-[#111827]/75" />
          <div className="relative flex h-full flex-col justify-between p-12">
            <div className="flex items-center gap-3">
              <span className="flex h-12 w-12 items-center justify-center rounded-lg bg-white/10 text-white ring-1 ring-white/20">
                <FiShield size={24} />
              </span>
              <div>
                <p className="text-lg font-semibold text-white">Sistema ERP</p>
                <p className="text-sm text-white/70">Auto Repuestos Bryan</p>
              </div>
            </div>

            <div className="max-w-xl">
              <p className="mb-4 text-sm font-medium uppercase tracking-[0.28em] text-sky-200">
                Acceso administrativo
              </p>
              <h1 className="text-4xl font-bold leading-tight text-white xl:text-5xl">
                Inventario, ventas y contabilidad bajo una sola sesion segura.
              </h1>
              <div className="mt-8 grid grid-cols-3 gap-3">
                {['Inventario', 'Facturacion', 'Contabilidad'].map((item) => (
                  <div
                    key={item}
                    className="rounded-md border border-white/15 bg-white/10 px-4 py-3 text-sm font-medium text-white shadow-sm backdrop-blur"
                  >
                    {item}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </section>

        <section className="flex items-center justify-center px-4 py-8 sm:px-6 lg:px-12">
          <div className="w-full max-w-md">
            <div className="mb-8 flex items-center justify-between">
              <img src={LogoDark} alt="Sistema ERP" className="h-10 w-auto" />
              <span className="rounded-md border border-stroke bg-white px-3 py-1 text-xs font-semibold text-slate-600 dark:border-strokedark dark:bg-boxdark dark:text-bodydark">
                ERP
              </span>
            </div>

            <div className="rounded-lg border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark sm:p-8">
              <div className="mb-7">
                <p className="mb-2 flex items-center gap-2 text-sm font-medium text-primary">
                  <FiShield />
                  Portal seguro
                </p>
                <h2 className="text-2xl font-bold text-black dark:text-white">
                  Iniciar sesion
                </h2>
                <p className="mt-2 text-sm text-slate-500 dark:text-bodydark">
                  Entra con tu usuario autorizado del ERP.
                </p>
              </div>

              {errors.form && (
                <div className="mb-5 flex gap-3 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200">
                  <FiAlertCircle className="mt-0.5 shrink-0" />
                  <span>{errors.form}</span>
                </div>
              )}

              <form onSubmit={handleSubmit} noValidate>
                <div className="mb-4">
                  <label
                    htmlFor="username"
                    className="mb-2 block text-sm font-medium text-black dark:text-white"
                  >
                    Usuario o correo
                  </label>
                  <div className="relative">
                    <FiUser className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      id="username"
                      type="text"
                      value={form.username}
                      onChange={(event) =>
                        updateField('username', event.target.value)
                      }
                      onBlur={() =>
                        setErrors((current) => ({
                          ...current,
                          ...validateForm({ ...form, password: 'validpass' }),
                        }))
                      }
                      placeholder="admin"
                      autoComplete="username"
                      aria-invalid={Boolean(errors.username)}
                      className={`w-full rounded-md border bg-transparent py-3 pl-11 pr-4 text-black outline-none transition focus:border-primary dark:bg-form-input dark:text-white ${
                        errors.username
                          ? 'border-red-400'
                          : 'border-stroke dark:border-form-strokedark'
                      }`}
                    />
                  </div>
                  {errors.username && (
                    <p className="mt-2 text-sm text-red-600 dark:text-red-300">
                      {errors.username}
                    </p>
                  )}
                </div>

                <div className="mb-4">
                  <label
                    htmlFor="password"
                    className="mb-2 block text-sm font-medium text-black dark:text-white"
                  >
                    Contrasena
                  </label>
                  <div className="relative">
                    <FiLock className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                      id="password"
                      type={showPassword ? 'text' : 'password'}
                      value={form.password}
                      onChange={(event) =>
                        updateField('password', event.target.value)
                      }
                      onBlur={() =>
                        setErrors((current) => ({
                          ...current,
                          password: validateForm(form).password,
                        }))
                      }
                      placeholder="Minimo 8 caracteres"
                      autoComplete="current-password"
                      aria-invalid={Boolean(errors.password)}
                      className={`w-full rounded-md border bg-transparent py-3 pl-11 pr-12 text-black outline-none transition focus:border-primary dark:bg-form-input dark:text-white ${
                        errors.password
                          ? 'border-red-400'
                          : 'border-stroke dark:border-form-strokedark'
                      }`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((visible) => !visible)}
                      className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-primary"
                      aria-label={
                        showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'
                      }
                    >
                      {showPassword ? <FiEyeOff /> : <FiEye />}
                    </button>
                  </div>
                  <div className="mt-2 flex items-center justify-between gap-3 text-sm">
                    {errors.password ? (
                      <p className="text-red-600 dark:text-red-300">
                        {errors.password}
                      </p>
                    ) : (
                      <p className="flex items-center gap-1.5 text-slate-500 dark:text-bodydark">
                        {passwordReady && (
                          <FiCheckCircle className="text-emerald-500" />
                        )}
                        Validacion local activa
                      </p>
                    )}
                    {lockSeconds > 0 && (
                      <span className="font-medium text-amber-600">
                        {lockSeconds}s
                      </span>
                    )}
                  </div>
                </div>

                <div className="mb-6 flex items-center justify-between gap-4">
                  <label className="flex cursor-pointer items-center gap-3 text-sm text-slate-600 dark:text-bodydark">
                    <input
                      type="checkbox"
                      checked={form.remember}
                      onChange={(event) =>
                        updateField('remember', event.target.checked)
                      }
                      className="h-4 w-4 rounded border-stroke text-primary focus:ring-primary"
                    />
                    Mantener sesion
                  </label>
                </div>

                <button
                  type="submit"
                  disabled={!canSubmit}
                  className="flex w-full items-center justify-center gap-2 rounded-md border border-primary bg-primary px-4 py-3 font-medium text-white transition hover:bg-opacity-90 disabled:cursor-not-allowed disabled:opacity-70"
                >
                  {submitting && <FiLoader className="animate-spin" />}
                  {lockSeconds > 0 ? 'Espera para intentar' : 'Entrar al sistema'}
                </button>
              </form>
            </div>
          </div>
        </section>
      </div>
    </main>
  );
};

export default SignIn;
