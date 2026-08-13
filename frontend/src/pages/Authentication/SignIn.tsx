import { FormEvent, useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
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
import { useBranding } from '../../context/BrandingContext';
import { ApiError } from '../../services/api';
import CoverImage from '../../images/icon/fondo.jpg';
import InventoryImage from '../../images/icon/cubo.png';
import InvoiceImage from '../../images/icon/hoja-de-balance.png';
import CalculatorImage from '../../images/icon/grafico-de-barras.png';
// Logo del proveedor del sistema (Sigma Enterprise). Es fijo/permanente en
// el panel izquierdo del login: a diferencia del logo de la empresa cliente
// (panel derecho, configurable desde Configuracion), este NO viene de
// BrandingContext ni se puede cambiar desde la UI.
import SigmaEnterpriseLogo from '../../images/logo/sigma-enterprise-logo.png';

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
  const { branding, initials, loginLogoUrl, logoUrl } = useBranding();
  const [form, setForm] = useState<FormState>({
    username: '',
    password: '',
    remember: true,
  });
  const [errors, setErrors] = useState<FormErrors>({});
  const [showPassword, setShowPassword] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [lockSeconds, setLockSeconds] = useState(0);

  const defaultText = "Gestiona inventario, ventas, contabilidad y más desde una sola plataforma segura y eficiente.";
  const [activeText, setActiveText] = useState(defaultText);

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
    <main className="flex min-h-screen items-center justify-center bg-[#F1F5F9] p-4 text-[#334155] dark:bg-[#0B1120] dark:text-slate-200 sm:p-8">
      <div className="flex w-full max-w-[1100px] flex-col overflow-hidden rounded-[2rem] bg-[#FFFFFF] shadow-2xl dark:bg-[#1E293B] lg:flex-row">
        <section className="relative hidden lg:block lg:w-1/2">
          <img
            src={CoverImage}
            alt="SIGMA ERP"
            className="absolute inset-0 h-full w-full object-cover"
          />
          <div className="absolute inset-0 bg-[#0F172A]/85" />
          <div className="relative flex h-full flex-col justify-center p-6 lg:p-8 xl:p-10">
            <div className="mb-6 flex justify-center">
              <img
                src={SigmaEnterpriseLogo}
                alt="Sigma Enterprise"
                className="h-60 w-auto object-contain drop-shadow-lg"
              />
            </div>

            <div className="max-w-xl">
              <h1 className="mb-2 text-3xl font-black leading-tight tracking-tight text-white xl:text-4xl">
                Una plataforma ERP <br /><span className="bg-gradient-to-r from-[#5B93FF] to-[#7C6FF0] bg-clip-text text-transparent">completa para tu empresa</span>
              </h1>
              <div className="relative min-h-[60px] sm:min-h-[50px]">
                <AnimatePresence mode="wait">
                  <motion.p
                    key={activeText}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -10 }}
                    transition={{ duration: 0.3 }}
                    className="absolute text-sm text-slate-300"
                  >
                    {activeText}
                  </motion.p>
                </AnimatePresence>
              </div>

              <div className="mt-4 grid grid-cols-3 gap-3">
                {[
                  {
                    title: 'Inventario',
                    image: InventoryImage,
                    description: 'Gestiona tu stock, entradas y salidas en tiempo real con precisión.',
                  },
                  {
                    title: 'Facturación',
                    image: InvoiceImage,
                    description: 'Emite facturas y comprobantes electrónicos de forma rápida y segura.',
                  },
                  {
                    title: 'Contabilidad',
                    image: CalculatorImage,
                    description: 'Mantén tus finanzas al día, genera reportes y controla tus gastos.',
                  },
                ].map((item) => (
                  <motion.div
                    key={item.title}
                    initial="rest"
                    whileHover="hover"
                    animate="rest"
                    onMouseEnter={() => setActiveText(item.description)}
                    onMouseLeave={() => setActiveText(defaultText)}
                    className="
                      relative
                      p-[1.5px]
                      rounded-2xl
                      overflow-hidden
                      bg-white/10
                      transition-all
                      duration-300
                      hover:-translate-y-2
                      group
                    "
                  >
                    {/* Border Light Sweep Effect */}
                    <motion.div
                      variants={{
                        rest: { opacity: 0 },
                        hover: { opacity: 1 },
                      }}
                      transition={{ duration: 0.3 }}
                      className="absolute inset-0 z-0 pointer-events-none"
                    >
                      <motion.div
                        animate={{ rotate: 360 }}
                        transition={{ duration: 4, repeat: Infinity, ease: 'linear' }}
                        style={{ x: '-50%', y: '-50%' }}
                        className="absolute top-1/2 left-1/2 h-[250%] w-[250%] bg-[conic-gradient(from_0deg,transparent_40%,#155EEF_50%,#7C6FF0_55%,transparent_65%)]"
                      />
                    </motion.div>

                    {/* Inner Card Content */}
                    <div className="
                      relative
                      z-10
                      w-full
                      h-full
                      rounded-[14px]
                      bg-[#0F172A]/40
                      backdrop-blur-md
                      p-4
                      text-center
                      transition-all
                      duration-300
                    ">
                      <img
                        src={item.image}
                        alt={item.title}
                        className="
                          mx-auto
                          h-12
                          w-12
                          object-contain
                          opacity-75
                          transition-all
                          duration-300
                          group-hover:scale-110
                          group-hover:opacity-100
                        "
                        style={{
                          filter:
                            'brightness(0) saturate(100%) invert(36%) sepia(89%) saturate(1537%) hue-rotate(185deg) brightness(99%) contrast(104%)',
                        }}
                      />

                      <p className="mt-2 text-white text-sm font-medium">
                        {item.title}
                      </p>
                    </div>
                  </motion.div>
                ))}
              </div>

              <div className="mt-6 flex items-center gap-4 rounded-xl border border-white/10 bg-[#FFFFFF]/5 p-4 backdrop-blur-md">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#5B93FF]/20 text-[#5B93FF]">
                  <FiShield size={24} />
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-white">Seguridad y confianza</h3>
                  <p className="mt-1 text-xs text-slate-400">Protegemos tu información con los más altos estándares de seguridad.</p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section className="flex w-full items-center justify-center p-6 sm:p-8 lg:w-1/2 lg:p-8">
          <div className="w-full max-w-md">
            <div className="mb-4 flex flex-col items-center justify-center text-center">
              {loginLogoUrl || logoUrl ? (
                <img
                  src={loginLogoUrl || logoUrl || ''}
                  alt={branding.company_name}
                  className="mb-2 h-16 w-auto object-contain"
                />
              ) : (
                <span className="mb-2 flex h-16 w-16 items-center justify-center rounded-xl bg-[#155EEF]/10 text-xl font-black text-[#155EEF] dark:bg-[#155EEF]/20">
                  {initials}
                </span>
              )}
              <h2 className="mt-2 text-2xl font-bold text-[#334155] dark:text-white">
                Iniciar Sesion
              </h2>
              <p className="mt-2 text-sm font-semibold text-[#334155] dark:text-bodydark">
                Bienvenido a <span className="text-[#155EEF]">{branding.commercial_name}</span>
              </p>
            </div>

            <div className="w-full">
              {errors.form && (
                <div className="mb-4 flex gap-2 rounded-md border border-[#F04438]/20 bg-[#F04438]/5 px-3 py-2 text-xs text-[#F04438] dark:border-[#F04438]/40 dark:bg-[#F04438]/10 dark:text-red-200">
                  <FiAlertCircle className="mt-0.5 shrink-0" />
                  <span>{errors.form}</span>
                </div>
              )}

              <form onSubmit={handleSubmit} noValidate>
                <div className="mb-3">
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
                      placeholder="Ingrese correo electrónico"
                      autoComplete="username"
                      aria-invalid={Boolean(errors.username)}
                      className={`w-full rounded-xl border bg-transparent py-2.5 pl-11 pr-4 text-sm text-[#334155] outline-none transition focus:border-[#155EEF] dark:bg-form-input dark:text-white ${errors.username
                        ? 'border-[#F04438]'
                        : 'border-stroke dark:border-form-strokedark'
                        }`}
                    />
                  </div>
                  {errors.username && (
                    <p className="mt-2 text-sm text-[#F04438]">
                      {errors.username}
                    </p>
                  )}
                </div>

                <div className="mb-3">
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
                      placeholder="Introducir la contraseña"
                      autoComplete="current-password"
                      aria-invalid={Boolean(errors.password)}
                      className={`w-full rounded-xl border bg-transparent py-2.5 pl-11 pr-12 text-sm text-[#334155] outline-none transition focus:border-[#155EEF] dark:bg-form-input dark:text-white ${errors.password
                        ? 'border-[#F04438]'
                        : 'border-stroke dark:border-form-strokedark'
                        }`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((visible) => !visible)}
                      className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-[#155EEF]"
                      aria-label={
                        showPassword ? 'Ocultar contrasena' : 'Mostrar contrasena'
                      }
                    >
                      {showPassword ? <FiEyeOff /> : <FiEye />}
                    </button>
                  </div>
                  <div className="mt-1.5 flex items-center justify-between gap-3 text-xs">
                    {errors.password ? (
                      <p className="text-[#F04438]">
                        {errors.password}
                      </p>
                    ) : (
                      <p className="flex items-center gap-1.5 text-slate-500 dark:text-bodydark">
                        {passwordReady && (
                          <FiCheckCircle className="text-[#12B76A]" />
                        )}
                        Validacion local activa
                      </p>
                    )}
                    {lockSeconds > 0 && (
                      <span className="font-medium text-[#F79009]">
                        {lockSeconds}s
                      </span>
                    )}
                  </div>
                </div>

                <div className="mb-4 flex items-center justify-between gap-4">
                  <label className="flex cursor-pointer items-center gap-2 text-[13px] text-slate-600 dark:text-bodydark">
                    <input
                      type="checkbox"
                      checked={form.remember}
                      onChange={(event) =>
                        updateField('remember', event.target.checked)
                      }
                      className="h-4 w-4 rounded border-stroke text-[#155EEF] focus:ring-[#155EEF] dark:border-strokedark"
                    />
                    Recordarme
                  </label>
                  <a href="#" className="text-[13px] font-medium text-[#155EEF] hover:underline">
                    Has olvidado tu contraseña ?
                  </a>
                </div>

                <button
                  type="submit"
                  disabled={!canSubmit}
                  className="flex w-full items-center justify-center gap-2 rounded-xl border border-[#155EEF] bg-[#155EEF] px-4 py-3 text-[15px] font-medium text-white transition hover:bg-[#155EEF]/90 disabled:cursor-not-allowed disabled:opacity-70"
                >
                  {submitting && <FiLoader className="animate-spin" />}
                  {lockSeconds > 0 ? 'Espera para intentar' : 'Acceso \u2192'}
                </button>

                <div className="mt-4 flex items-center justify-center gap-2 text-[13px] text-slate-500">
                  <FiShield className="text-[#155EEF]" />
                  Seguro, rápido y confiable
                </div>
              </form>
            </div>
          </div>
        </section>
      </div>
    </main>
  );
};

export default SignIn;
