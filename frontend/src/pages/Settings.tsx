import {
  ChangeEvent,
  FormEvent,
  ReactNode,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from 'react';
import {
  FiArchive,
  FiBriefcase,
  FiCheck,
  FiClock,
  FiCreditCard,
  FiDatabase,
  FiGlobe,
  FiImage,
  FiLock,
  FiMail,
  FiRefreshCw,
  FiSave,
  FiSettings,
  FiShield,
  FiUploadCloud,
} from 'react-icons/fi';
import Breadcrumb from '../components/Breadcrumbs/Breadcrumb';
import { useAuth } from '../context/AuthContext';
import { useBranding } from '../context/BrandingContext';
import { ApiError, apiRequest } from '../services/api';

type Currency = {
  id: number;
  code: string;
  name: string;
  symbol: string | null;
  decimal_places: number;
  decimal_separator: string;
  thousands_separator: string;
  symbol_position: 'before' | 'after';
  is_base: boolean;
  is_active: boolean;
};

type Branch = {
  id: number;
  code: string | null;
  name: string;
  phone: string | null;
  email: string | null;
  full_address: string | null;
  is_headquarters: boolean;
  status: number;
};

type Tax = {
  id: number;
  code: string | null;
  name: string;
  rate: number | string | null;
};

type ExchangeRate = {
  id: number;
  from_currency_id: number;
  from_currency: string | null;
  to_currency_id: number;
  to_currency: string | null;
  rate: number;
  date: string | null;
  created_by_name: string | null;
  observation: string | null;
};

type Backup = {
  id: number;
  file_name: string;
  file_size: number;
  status: string;
  created_at: string | null;
  restored_at: string | null;
};

type Company = {
  id: number;
  name: string | null;
  legal_name: string | null;
  short_name: string | null;
  slogan: string | null;
  description: string | null;
  tax_id: string | null;
  nrc: string | null;
  commercial_registry: string | null;
  business_activity: string | null;
  tax_regime: string | null;
  taxpayer_type: string | null;
  phone: string | null;
  mobile: string | null;
  whatsapp: string | null;
  email: string | null;
  website: string | null;
  fiscal_address: string | null;
  commercial_address: string | null;
  country: string | null;
  department: string | null;
  city: string | null;
  full_address: string | null;
  postal_code: string | null;
  latitude: number | string | null;
  longitude: number | string | null;
  logo_url: string | null;
  logo_dark_url: string | null;
  favicon_url: string | null;
  currency_id: number | null;
  timezone: string | null;
  locale: string | null;
  date_format: string | null;
  time_format: string | null;
};

type SettingsValues = {
  regional?: Record<string, string | number | boolean | null>;
  monetary?: Record<string, string | number | boolean | null>;
  mail?: Record<string, string | number | boolean | null>;
  security?: Record<string, string | number | boolean | null>;
  inventory?: Record<string, string | number | boolean | null>;
  sales?: Record<string, string | number | boolean | null>;
  purchases?: Record<string, string | number | boolean | null>;
  printing?: Record<string, string | number | boolean | null>;
  backup?: Record<string, string | number | boolean | null>;
  system?: Record<string, string | number | boolean | null>;
};

type SettingsOptions = {
  countries: string[];
  timezones: string[];
  locales: string[];
  date_formats: string[];
  time_formats: string[];
  print_formats: string[];
  two_factor_supported: boolean;
};

type BrandingPayload = {
  company_name: string;
  commercial_name?: string | null;
  slogan?: string | null;
  welcome_text: string;
  logo_url?: string | null;
  logo_dark_url?: string | null;
  favicon_url?: string | null;
  notifications_enabled?: boolean;
};

type GeneralSettingsData = {
  company: Company;
  branding: BrandingPayload;
  branches: Branch[];
  currencies: Currency[];
  taxes: Tax[];
  exchange_rates: ExchangeRate[];
  settings: SettingsValues;
  options: SettingsOptions;
  backups: Backup[];
};

type GeneralSettingsResponse = {
  data: GeneralSettingsData;
  message?: string;
};

type MessageResponse = {
  message: string;
};

type BackupResponse = {
  message: string;
  item: Backup;
};

type CompanyForm = {
  name: string;
  legal_name: string;
  short_name: string;
  slogan: string;
  description: string;
  tax_id: string;
  nrc: string;
  commercial_registry: string;
  business_activity: string;
  tax_regime: string;
  taxpayer_type: string;
  phone: string;
  mobile: string;
  whatsapp: string;
  email: string;
  website: string;
  fiscal_address: string;
  commercial_address: string;
  country: string;
  department: string;
  city: string;
  full_address: string;
  postal_code: string;
  latitude: string;
  longitude: string;
};

type SettingsForm = {
  company: CompanyForm;
  headquarters_id: string;
  finance: {
    currency_id: string;
    decimal_separator: string;
    thousands_separator: string;
    decimal_places: string;
    symbol_position: string;
    dollar_exchange_rate: string;
    exchange_rate_date: string;
    observation: string;
  };
  regional: {
    timezone: string;
    locale: string;
    date_format: string;
    time_format: string;
  };
  mail: {
    smtp_host: string;
    smtp_port: string;
    smtp_username: string;
    smtp_password: string;
    smtp_encryption: string;
    from_address: string;
    from_name: string;
  };
  security: {
    session_timeout_minutes: string;
    max_login_attempts: string;
    lockout_enabled: boolean;
    lockout_minutes: string;
    password_min_length: string;
    password_complexity: string;
    two_factor_enabled: boolean;
  };
  inventory: {
    minimum_stock: string;
    allow_negative_stock: boolean;
    track_lots: boolean;
    track_serials: boolean;
    alerts_enabled: boolean;
  };
  sales: {
    auto_numbering: boolean;
    prefix: string;
    suffix: string;
    digits: string;
    rounding: string;
    default_tax_id: string;
  };
  purchases: {
    prefix: string;
    default_tax_id: string;
    authorization_required: boolean;
  };
  printing: {
    default_format: string;
    default_printer: string;
  };
  backup: {
    schedule_enabled: boolean;
    frequency: string;
    time: string;
    retention_days: string;
  };
  system: {
    audit_enabled: boolean;
    notifications_enabled: boolean;
  };
};

type LogoFiles = {
  logo_file: File | null;
  logo_dark_file: File | null;
  favicon_file: File | null;
};

type TabKey = 'company' | 'regional' | 'operations' | 'security' | 'backup';
type ConfigGroup = Exclude<keyof SettingsForm, 'company' | 'headquarters_id'>;

const inputClass =
  'h-11 w-full rounded-lg border border-stroke bg-white px-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const selectClass =
  'h-11 w-full rounded-lg border border-stroke bg-white px-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';
const textareaClass =
  'min-h-24 w-full rounded-lg border border-stroke bg-white px-3 py-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

const emptyLogoFiles: LogoFiles = {
  logo_file: null,
  logo_dark_file: null,
  favicon_file: null,
};

const emptyForm: SettingsForm = {
  company: {
    name: '',
    legal_name: '',
    short_name: '',
    slogan: '',
    description: '',
    tax_id: '',
    nrc: '',
    commercial_registry: '',
    business_activity: '',
    tax_regime: '',
    taxpayer_type: '',
    phone: '',
    mobile: '',
    whatsapp: '',
    email: '',
    website: '',
    fiscal_address: '',
    commercial_address: '',
    country: '',
    department: '',
    city: '',
    full_address: '',
    postal_code: '',
    latitude: '',
    longitude: '',
  },
  headquarters_id: '',
  finance: {
    currency_id: '',
    decimal_separator: '.',
    thousands_separator: ',',
    decimal_places: '2',
    symbol_position: 'before',
    dollar_exchange_rate: '',
    exchange_rate_date: today(),
    observation: '',
  },
  regional: {
    timezone: 'America/Managua',
    locale: 'es_NI',
    date_format: 'dd/mm/yyyy',
    time_format: '24h',
  },
  mail: {
    smtp_host: '',
    smtp_port: '587',
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: 'tls',
    from_address: '',
    from_name: '',
  },
  security: {
    session_timeout_minutes: '720',
    max_login_attempts: '5',
    lockout_enabled: true,
    lockout_minutes: '15',
    password_min_length: '8',
    password_complexity: 'medium',
    two_factor_enabled: false,
  },
  inventory: {
    minimum_stock: '0',
    allow_negative_stock: false,
    track_lots: false,
    track_serials: false,
    alerts_enabled: true,
  },
  sales: {
    auto_numbering: true,
    prefix: 'FAC',
    suffix: '',
    digits: '8',
    rounding: '2',
    default_tax_id: '',
  },
  purchases: {
    prefix: 'COM',
    default_tax_id: '',
    authorization_required: false,
  },
  printing: {
    default_format: 'a4',
    default_printer: '',
  },
  backup: {
    schedule_enabled: false,
    frequency: 'daily',
    time: '02:00',
    retention_days: '7',
  },
  system: {
    audit_enabled: true,
    notifications_enabled: true,
  },
};

function today() {
  return new Date().toISOString().slice(0, 10);
}

function text(value: unknown, fallback = '') {
  if (value === null || value === undefined) {
    return fallback;
  }

  return String(value);
}

function bool(value: unknown, fallback = false) {
  if (typeof value === 'boolean') {
    return value;
  }

  if (value === null || value === undefined || value === '') {
    return fallback;
  }

  return value === 1 || value === '1' || value === 'true';
}

function nullableText(value: string) {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function numberOrNull(value: string) {
  if (value.trim() === '') {
    return null;
  }

  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : null;
}

function intOr(value: string, fallback: number) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? Math.trunc(parsed) : fallback;
}

function normalizePrintFormat(value: unknown) {
  const normalized = text(value, 'a4').trim().toLowerCase();

  if (normalized === 'carta') {
    return 'letter';
  }

  if (normalized === 'ticket' || normalized === 'letter' || normalized === 'a4') {
    return normalized;
  }

  return 'a4';
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function formatDate(value?: string | null) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat('es-NI', {
    dateStyle: 'medium',
    timeStyle: value.includes('T') ? 'short' : undefined,
  }).format(date);
}

function formatFileSize(size: number) {
  if (!size) {
    return '0 KB';
  }

  if (size < 1024 * 1024) {
    return `${Math.max(1, Math.round(size / 1024))} KB`;
  }

  return `${(size / 1024 / 1024).toFixed(2)} MB`;
}

function toFormData(payload: GeneralSettingsData): SettingsForm {
  const company = payload.company;
  const settings = payload.settings || {};
  const regional = settings.regional || {};
  const monetary = settings.monetary || {};
  const mail = settings.mail || {};
  const security = settings.security || {};
  const inventory = settings.inventory || {};
  const sales = settings.sales || {};
  const purchases = settings.purchases || {};
  const printing = settings.printing || {};
  const backup = settings.backup || {};
  const system = settings.system || {};
  const headquarters = payload.branches.find((branch) => branch.is_headquarters);
  const baseCurrency = payload.currencies.find((currency) => currency.is_base);

  return {
    company: {
      name: text(company.name),
      legal_name: text(company.legal_name),
      short_name: text(company.short_name),
      slogan: text(company.slogan),
      description: text(company.description),
      tax_id: text(company.tax_id),
      nrc: text(company.nrc),
      commercial_registry: text(company.commercial_registry),
      business_activity: text(company.business_activity),
      tax_regime: text(company.tax_regime),
      taxpayer_type: text(company.taxpayer_type),
      phone: text(company.phone),
      mobile: text(company.mobile),
      whatsapp: text(company.whatsapp),
      email: text(company.email),
      website: text(company.website),
      fiscal_address: text(company.fiscal_address),
      commercial_address: text(company.commercial_address),
      country: text(company.country),
      department: text(company.department),
      city: text(company.city),
      full_address: text(company.full_address),
      postal_code: text(company.postal_code),
      latitude: text(company.latitude),
      longitude: text(company.longitude),
    },
    headquarters_id: text(headquarters?.id || ''),
    finance: {
      currency_id: text(company.currency_id || baseCurrency?.id || ''),
      decimal_separator: text(monetary.decimal_separator, '.'),
      thousands_separator: text(monetary.thousands_separator, ','),
      decimal_places: text(monetary.decimal_places, '2'),
      symbol_position: text(monetary.symbol_position, 'before'),
      dollar_exchange_rate: '',
      exchange_rate_date: today(),
      observation: '',
    },
    regional: {
      timezone: text(regional.timezone || company.timezone, 'America/Managua'),
      locale: text(regional.locale || company.locale, 'es_NI'),
      date_format: text(regional.date_format || company.date_format, 'dd/mm/yyyy'),
      time_format: text(regional.time_format || company.time_format, '24h'),
    },
    mail: {
      smtp_host: text(mail.smtp_host),
      smtp_port: text(mail.smtp_port, '587'),
      smtp_username: text(mail.smtp_username),
      smtp_password: '',
      smtp_encryption: text(mail.smtp_encryption, 'tls'),
      from_address: text(mail.from_address),
      from_name: text(mail.from_name),
    },
    security: {
      session_timeout_minutes: text(security.session_timeout_minutes, '720'),
      max_login_attempts: text(security.max_login_attempts, '5'),
      lockout_enabled: bool(security.lockout_enabled, true),
      lockout_minutes: text(security.lockout_minutes, '15'),
      password_min_length: text(security.password_min_length, '8'),
      password_complexity: text(security.password_complexity, 'medium'),
      two_factor_enabled: bool(security.two_factor_enabled, false),
    },
    inventory: {
      minimum_stock: text(inventory.minimum_stock, '0'),
      allow_negative_stock: bool(inventory.allow_negative_stock, false),
      track_lots: bool(inventory.track_lots, false),
      track_serials: bool(inventory.track_serials, false),
      alerts_enabled: bool(inventory.alerts_enabled, true),
    },
    sales: {
      auto_numbering: bool(sales.auto_numbering, true),
      prefix: text(sales.prefix, 'FAC'),
      suffix: text(sales.suffix),
      digits: text(sales.digits, '8'),
      rounding: text(sales.rounding, '2'),
      default_tax_id: text(sales.default_tax_id),
    },
    purchases: {
      prefix: text(purchases.prefix, 'COM'),
      default_tax_id: text(purchases.default_tax_id),
      authorization_required: bool(purchases.authorization_required, false),
    },
    printing: {
      default_format: normalizePrintFormat(printing.default_format),
      default_printer: text(printing.default_printer),
    },
    backup: {
      schedule_enabled: bool(backup.schedule_enabled, false),
      frequency: text(backup.frequency, 'daily'),
      time: text(backup.time, '02:00'),
      retention_days: text(backup.retention_days, '7'),
    },
    system: {
      audit_enabled: bool(system.audit_enabled, true),
      notifications_enabled: bool(system.notifications_enabled, true),
    },
  };
}

function Field({
  children,
  label,
  className = '',
}: {
  children: ReactNode;
  label: string;
  className?: string;
}) {
  return (
    <label className={`block ${className}`}>
      <span className="mb-2 block text-sm font-semibold text-black dark:text-white">
        {label}
      </span>
      {children}
    </label>
  );
}

function Panel({
  children,
  icon,
  title,
}: {
  children: ReactNode;
  icon: ReactNode;
  title: string;
}) {
  return (
    <section className="rounded-lg border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="flex items-center gap-3 border-b border-stroke px-5 py-4 dark:border-strokedark">
        <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          {icon}
        </span>
        <h3 className="text-base font-bold text-black dark:text-white">{title}</h3>
      </div>
      <div className="p-5">{children}</div>
    </section>
  );
}

function ToggleField({
  checked,
  label,
  onChange,
}: {
  checked: boolean;
  label: string;
  onChange: (checked: boolean) => void;
}) {
  return (
    <div className="flex items-center justify-between gap-4 rounded-lg border border-stroke px-4 py-3 dark:border-strokedark">
      <span className="text-sm font-semibold text-black dark:text-white">{label}</span>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        aria-label={label}
        onClick={() => onChange(!checked)}
        className={`relative inline-flex h-7 w-13 shrink-0 items-center rounded-full transition ${
          checked ? 'bg-[#0F9F37]' : 'bg-red-500'
        }`}
      >
        <span
          className={`inline-block h-5 w-5 rounded-full bg-white shadow transition ${
            checked ? 'translate-x-7' : 'translate-x-1'
          }`}
        />
      </button>
    </div>
  );
}

function LogoUploader({
  currentUrl,
  file,
  inputKey,
  label,
  name,
  onChange,
}: {
  currentUrl?: string | null;
  file: File | null;
  inputKey: number;
  label: string;
  name: keyof LogoFiles;
  onChange: (field: keyof LogoFiles, file: File | null) => void;
}) {
  const inputId = `settings-${name}`;

  return (
    <div className="rounded-lg border border-stroke p-4 dark:border-strokedark">
      <div className="mb-3 flex h-20 items-center justify-center rounded-lg bg-slate-50 dark:bg-meta-4">
        {currentUrl ? (
          <img src={currentUrl} alt={label} className="max-h-16 max-w-full object-contain" />
        ) : (
          <FiImage className="h-8 w-8 text-slate-400" />
        )}
      </div>
      <div className="mb-3 min-h-10">
        <p className="text-sm font-semibold text-black dark:text-white">{label}</p>
        <p className="truncate text-xs text-slate-500">{file?.name || 'Sin archivo nuevo'}</p>
      </div>
      <input
        key={inputKey}
        id={inputId}
        type="file"
        accept="image/*,.ico"
        className="hidden"
        onChange={(event) => onChange(name, event.target.files?.[0] || null)}
      />
      <label
        htmlFor={inputId}
        className="inline-flex h-10 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-stroke text-sm font-bold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
      >
        <FiUploadCloud className="h-4 w-4" />
        Seleccionar
      </label>
    </div>
  );
}

function SummaryBox({
  icon,
  label,
  value,
}: {
  icon: ReactNode;
  label: string;
  value: string;
}) {
  return (
    <div className="min-w-0 rounded-lg border border-stroke bg-white p-4 shadow-default dark:border-strokedark dark:bg-boxdark">
      <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
        {icon}
      </div>
      <p className="text-xs font-semibold uppercase text-slate-500">{label}</p>
      <p className="mt-1 truncate text-base font-bold text-black dark:text-white">{value}</p>
    </div>
  );
}

export default function Settings() {
  const { token } = useAuth();
  const { updateBranding } = useBranding();
  const [data, setData] = useState<GeneralSettingsData | null>(null);
  const [form, setForm] = useState<SettingsForm>(emptyForm);
  const [activeTab, setActiveTab] = useState<TabKey>('company');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [logoSaving, setLogoSaving] = useState(false);
  const [testingMail, setTestingMail] = useState(false);
  const [backupSaving, setBackupSaving] = useState(false);
  const [pageError, setPageError] = useState('');
  const [notice, setNotice] = useState('');
  const [logoFiles, setLogoFiles] = useState<LogoFiles>(emptyLogoFiles);
  const [logoInputKey, setLogoInputKey] = useState(0);
  const [restoreFile, setRestoreFile] = useState<File | null>(null);
  const [restoreInputKey, setRestoreInputKey] = useState(0);

  const loadSettings = useCallback(async () => {
    if (!token) {
      setLoading(false);
      setPageError('No autenticado.');
      return;
    }

    setLoading(true);
    setPageError('');

    try {
      const response = await apiRequest<GeneralSettingsResponse>(
        '/settings/general',
        {},
        token,
      );
      setData(response.data);
      setForm(toFormData(response.data));
      setLogoFiles(emptyLogoFiles);
      setLogoInputKey((current) => current + 1);
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadSettings();
  }, [loadSettings]);

  const currencies = data?.currencies ?? [];
  const branches = data?.branches ?? [];
  const taxes = data?.taxes ?? [];
  const options = data?.options;

  const selectedCurrency = useMemo(
    () => currencies.find((currency) => String(currency.id) === form.finance.currency_id),
    [currencies, form.finance.currency_id],
  );

  const selectedHeadquarters = useMemo(
    () => branches.find((branch) => String(branch.id) === form.headquarters_id),
    [branches, form.headquarters_id],
  );

  const tabs: { key: TabKey; label: string; icon: ReactNode }[] = [
    { key: 'company', label: 'Empresa', icon: <FiBriefcase className="h-4 w-4" /> },
    { key: 'regional', label: 'Regional y moneda', icon: <FiGlobe className="h-4 w-4" /> },
    { key: 'operations', label: 'Operaciones', icon: <FiSettings className="h-4 w-4" /> },
    { key: 'security', label: 'Correo y seguridad', icon: <FiShield className="h-4 w-4" /> },
    { key: 'backup', label: 'Respaldos y sistema', icon: <FiDatabase className="h-4 w-4" /> },
  ];

  function updateCompany(field: keyof CompanyForm, value: string) {
    setForm((current) => ({
      ...current,
      company: {
        ...current.company,
        [field]: value,
      },
    }));
  }

  function updateGroup(group: ConfigGroup, field: string, value: string | boolean) {
    setForm((current) => ({
      ...current,
      [group]: {
        ...(current[group] as Record<string, string | boolean>),
        [field]: value,
      },
    }) as SettingsForm);
  }

  function updateHeadquarters(value: string) {
    setForm((current) => ({ ...current, headquarters_id: value }));
  }

  function handleLogoFile(field: keyof LogoFiles, file: File | null) {
    setLogoFiles((current) => ({ ...current, [field]: file }));
  }

  function handleRestoreFile(event: ChangeEvent<HTMLInputElement>) {
    setRestoreFile(event.target.files?.[0] || null);
  }

  function buildSettingsPayload() {
    const company = form.company;
    const payload: Record<string, unknown> = {
      company: {
        name: company.name.trim(),
        legal_name: nullableText(company.legal_name),
        short_name: nullableText(company.short_name),
        slogan: nullableText(company.slogan),
        description: nullableText(company.description),
        tax_id: nullableText(company.tax_id),
        nrc: nullableText(company.nrc),
        commercial_registry: nullableText(company.commercial_registry),
        business_activity: nullableText(company.business_activity),
        tax_regime: nullableText(company.tax_regime),
        taxpayer_type: nullableText(company.taxpayer_type),
        phone: nullableText(company.phone),
        mobile: nullableText(company.mobile),
        whatsapp: nullableText(company.whatsapp),
        email: nullableText(company.email),
        website: nullableText(company.website),
        fiscal_address: nullableText(company.fiscal_address),
        commercial_address: nullableText(company.commercial_address),
        country: nullableText(company.country),
        department: nullableText(company.department),
        city: nullableText(company.city),
        full_address: nullableText(company.full_address),
        postal_code: nullableText(company.postal_code),
        latitude: numberOrNull(company.latitude),
        longitude: numberOrNull(company.longitude),
      },
      regional: {
        timezone: nullableText(form.regional.timezone),
        locale: nullableText(form.regional.locale),
        date_format: nullableText(form.regional.date_format),
        time_format: nullableText(form.regional.time_format),
      },
      mail: {
        smtp_host: nullableText(form.mail.smtp_host),
        smtp_port: intOr(form.mail.smtp_port, 587),
        smtp_username: nullableText(form.mail.smtp_username),
        smtp_password: form.mail.smtp_password.trim() || '__KEEP__',
        smtp_encryption: form.mail.smtp_encryption,
        from_address: nullableText(form.mail.from_address),
        from_name: nullableText(form.mail.from_name),
      },
      security: {
        session_timeout_minutes: intOr(form.security.session_timeout_minutes, 720),
        max_login_attempts: intOr(form.security.max_login_attempts, 5),
        lockout_enabled: form.security.lockout_enabled,
        lockout_minutes: intOr(form.security.lockout_minutes, 15),
        password_min_length: intOr(form.security.password_min_length, 8),
        password_complexity: form.security.password_complexity,
        two_factor_enabled: form.security.two_factor_enabled,
      },
      inventory: {
        minimum_stock: numberOrNull(form.inventory.minimum_stock) ?? 0,
        allow_negative_stock: form.inventory.allow_negative_stock,
        track_lots: form.inventory.track_lots,
        track_serials: form.inventory.track_serials,
        alerts_enabled: form.inventory.alerts_enabled,
      },
      sales: {
        auto_numbering: form.sales.auto_numbering,
        prefix: nullableText(form.sales.prefix),
        suffix: nullableText(form.sales.suffix),
        digits: intOr(form.sales.digits, 8),
        rounding: intOr(form.sales.rounding, 2),
        default_tax_id: form.sales.default_tax_id ? Number(form.sales.default_tax_id) : null,
      },
      purchases: {
        prefix: nullableText(form.purchases.prefix),
        default_tax_id: form.purchases.default_tax_id
          ? Number(form.purchases.default_tax_id)
          : null,
        authorization_required: form.purchases.authorization_required,
      },
      printing: {
        default_format: form.printing.default_format,
        default_printer: nullableText(form.printing.default_printer),
      },
      backup: {
        schedule_enabled: form.backup.schedule_enabled,
        frequency: form.backup.frequency,
        time: form.backup.time || '02:00',
        retention_days: intOr(form.backup.retention_days, 7),
      },
      system: {
        audit_enabled: form.system.audit_enabled,
        notifications_enabled: form.system.notifications_enabled,
      },
    };

    if (form.headquarters_id) {
      payload.branches = {
        headquarters_id: Number(form.headquarters_id),
      };
    }

    if (form.finance.currency_id) {
      payload.finance = {
        currency_id: Number(form.finance.currency_id),
        decimal_separator: form.finance.decimal_separator || '.',
        thousands_separator: form.finance.thousands_separator || ',',
        decimal_places: intOr(form.finance.decimal_places, 2),
        symbol_position: form.finance.symbol_position,
      };
    }

    return payload;
  }

  function buildCurrencyPayload() {
    const payload: Record<string, unknown> = {
      currency_id: Number(form.finance.currency_id),
      decimal_separator: form.finance.decimal_separator || '.',
      thousands_separator: form.finance.thousands_separator || ',',
      decimal_places: intOr(form.finance.decimal_places, 2),
      symbol_position: form.finance.symbol_position,
    };

    if (form.finance.dollar_exchange_rate.trim()) {
      payload.dollar_exchange_rate = Number(form.finance.dollar_exchange_rate);
      payload.exchange_rate_date = form.finance.exchange_rate_date || today();
      payload.observation = nullableText(form.finance.observation);
    }

    return payload;
  }

  async function handleSave(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!token) {
      setPageError('No autenticado.');
      return;
    }

    if (!form.company.name.trim()) {
      setPageError('Ingresa el nombre comercial.');
      setActiveTab('company');
      return;
    }

    if (!form.finance.currency_id) {
      setPageError('Selecciona la moneda principal.');
      setActiveTab('regional');
      return;
    }

    setSaving(true);
    setPageError('');
    setNotice('');

    try {
      let response = await apiRequest<GeneralSettingsResponse>(
        '/settings/general',
        {
          method: 'PUT',
          body: JSON.stringify(buildSettingsPayload()),
        },
        token,
      );

      if (form.finance.dollar_exchange_rate.trim()) {
        response = await apiRequest<GeneralSettingsResponse>(
          '/settings/general/currency',
          {
            method: 'PUT',
            body: JSON.stringify(buildCurrencyPayload()),
          },
          token,
        );
      }

      setData(response.data);
      setForm(toFormData(response.data));
      updateBranding(response.data.branding);
      setNotice(response.message || 'Configuracion guardada correctamente.');
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function handleUploadLogos() {
    if (!token) {
      setPageError('No autenticado.');
      return;
    }

    const hasFile = Object.values(logoFiles).some(Boolean);

    if (!hasFile) {
      setPageError('Selecciona al menos una imagen.');
      return;
    }

    const body = new FormData();

    Object.entries(logoFiles).forEach(([field, file]) => {
      if (file) {
        body.append(field, file);
      }
    });

    setLogoSaving(true);
    setPageError('');
    setNotice('');

    try {
      const response = await apiRequest<GeneralSettingsResponse>(
        '/settings/general/logos',
        { method: 'POST', body },
        token,
      );
      setData(response.data);
      setForm(toFormData(response.data));
      setLogoFiles(emptyLogoFiles);
      setLogoInputKey((current) => current + 1);
      updateBranding(response.data.branding);
      setNotice(response.message || 'Logotipos actualizados correctamente.');
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setLogoSaving(false);
    }
  }

  async function handleTestMail() {
    if (!token) {
      setPageError('No autenticado.');
      return;
    }

    setTestingMail(true);
    setPageError('');
    setNotice('');

    try {
      const response = await apiRequest<MessageResponse>(
        '/settings/general/mail/test',
        { method: 'POST' },
        token,
      );
      setNotice(response.message);
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setTestingMail(false);
    }
  }

  async function handleCreateBackup() {
    if (!token) {
      setPageError('No autenticado.');
      return;
    }

    setBackupSaving(true);
    setPageError('');
    setNotice('');

    try {
      const response = await apiRequest<BackupResponse>(
        '/settings/general/backup/create',
        { method: 'POST' },
        token,
      );
      setData((current) =>
        current
          ? {
              ...current,
              backups: [response.item, ...current.backups].slice(0, 10),
            }
          : current,
      );
      setNotice(response.message);
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setBackupSaving(false);
    }
  }

  async function handleRestoreBackup() {
    if (!token) {
      setPageError('No autenticado.');
      return;
    }

    if (!restoreFile) {
      setPageError('Selecciona un archivo JSON de respaldo.');
      return;
    }

    const body = new FormData();
    body.append('backup_file', restoreFile);

    setBackupSaving(true);
    setPageError('');
    setNotice('');

    try {
      const response = await apiRequest<GeneralSettingsResponse>(
        '/settings/general/backup/restore',
        { method: 'POST', body },
        token,
      );
      setData(response.data);
      setForm(toFormData(response.data));
      setRestoreFile(null);
      setRestoreInputKey((current) => current + 1);
      updateBranding(response.data.branding);
      setNotice(response.message || 'Respaldo restaurado correctamente.');
    } catch (error) {
      setPageError(getErrorMessage(error));
    } finally {
      setBackupSaving(false);
    }
  }

  function resetForm() {
    if (data) {
      setForm(toFormData(data));
      setNotice('Cambios locales descartados.');
      setPageError('');
    }
  }

  if (loading) {
    return (
      <div>
        <Breadcrumb pageName="Configuracion del sistema" />
        <div className="rounded-lg border border-dashed border-stroke bg-white p-8 text-center text-sm text-slate-500 shadow-default dark:border-strokedark dark:bg-boxdark">
          Cargando configuracion...
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div>
        <Breadcrumb pageName="Configuracion del sistema" />
        <div className="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-600">
          {pageError || 'No se pudo cargar la configuracion.'}
        </div>
      </div>
    );
  }

  return (
    <form onSubmit={handleSave} className="space-y-6">
      <Breadcrumb pageName="Configuracion del sistema" />

      <div className="flex flex-col gap-3 rounded-lg border border-stroke bg-white p-5 shadow-default dark:border-strokedark dark:bg-boxdark md:flex-row md:items-center md:justify-between">
        <div>
          <h2 className="text-xl font-black text-black dark:text-white">
            Configuracion general
          </h2>
          <p className="mt-1 text-sm text-slate-500">
            {data.company.legal_name || data.company.name || data.branding.company_name}
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            type="button"
            onClick={resetForm}
            className="inline-flex h-11 items-center justify-center gap-2 rounded-lg border border-stroke px-4 text-sm font-bold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
          >
            <FiRefreshCw className="h-4 w-4" />
            Descartar
          </button>
          <button
            type="submit"
            disabled={saving}
            className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-white disabled:opacity-60"
          >
            {saving ? <FiRefreshCw className="h-4 w-4 animate-spin" /> : <FiSave className="h-4 w-4" />}
            {saving ? 'Guardando...' : 'Guardar cambios'}
          </button>
        </div>
      </div>

      {pageError && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-600">
          {pageError}
        </div>
      )}

      {notice && (
        <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
          <FiCheck className="h-4 w-4" />
          {notice}
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
        <SummaryBox
          icon={<FiBriefcase className="h-5 w-5" />}
          label="Empresa"
          value={form.company.short_name || form.company.name || 'Sin nombre'}
        />
        <SummaryBox
          icon={<FiCreditCard className="h-5 w-5" />}
          label="Moneda"
          value={selectedCurrency ? `${selectedCurrency.code} - ${selectedCurrency.name}` : 'Sin moneda'}
        />
        <SummaryBox
          icon={<FiGlobe className="h-5 w-5" />}
          label="Casa matriz"
          value={selectedHeadquarters?.name || 'Sin casa matriz'}
        />
      </div>

      <div className="overflow-x-auto rounded-lg border border-stroke bg-white p-2 shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="flex min-w-max gap-2">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActiveTab(tab.key)}
              className={`inline-flex h-11 items-center gap-2 rounded-lg px-4 text-sm font-bold transition ${
                activeTab === tab.key
                  ? 'bg-primary text-white'
                  : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-meta-4'
              }`}
            >
              {tab.icon}
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {activeTab === 'company' && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
          <div className="space-y-6 xl:col-span-2">
            <Panel title="Datos fiscales" icon={<FiBriefcase className="h-5 w-5" />}>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Field label="Nombre comercial">
                  <input
                    value={form.company.name}
                    onChange={(event) => updateCompany('name', event.target.value)}
                    className={inputClass}
                    required
                  />
                </Field>
                <Field label="Razon social">
                  <input
                    value={form.company.legal_name}
                    onChange={(event) => updateCompany('legal_name', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Nombre corto">
                  <input
                    value={form.company.short_name}
                    onChange={(event) => updateCompany('short_name', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Slogan">
                  <input
                    value={form.company.slogan}
                    onChange={(event) => updateCompany('slogan', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Identificacion fiscal">
                  <input
                    value={form.company.tax_id}
                    onChange={(event) => updateCompany('tax_id', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="NRC">
                  <input
                    value={form.company.nrc}
                    onChange={(event) => updateCompany('nrc', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Registro comercial">
                  <input
                    value={form.company.commercial_registry}
                    onChange={(event) =>
                      updateCompany('commercial_registry', event.target.value)
                    }
                    className={inputClass}
                  />
                </Field>
                <Field label="Actividad economica">
                  <input
                    value={form.company.business_activity}
                    onChange={(event) =>
                      updateCompany('business_activity', event.target.value)
                    }
                    className={inputClass}
                  />
                </Field>
                <Field label="Regimen fiscal">
                  <input
                    value={form.company.tax_regime}
                    onChange={(event) => updateCompany('tax_regime', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Tipo de contribuyente">
                  <input
                    value={form.company.taxpayer_type}
                    onChange={(event) => updateCompany('taxpayer_type', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Descripcion" className="md:col-span-2">
                  <textarea
                    value={form.company.description}
                    onChange={(event) => updateCompany('description', event.target.value)}
                    className={textareaClass}
                  />
                </Field>
              </div>
            </Panel>

            <Panel title="Contacto y ubicacion" icon={<FiGlobe className="h-5 w-5" />}>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Field label="Telefono">
                  <input
                    value={form.company.phone}
                    onChange={(event) => updateCompany('phone', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Movil">
                  <input
                    value={form.company.mobile}
                    onChange={(event) => updateCompany('mobile', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="WhatsApp">
                  <input
                    value={form.company.whatsapp}
                    onChange={(event) => updateCompany('whatsapp', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Correo">
                  <input
                    type="email"
                    value={form.company.email}
                    onChange={(event) => updateCompany('email', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Sitio web">
                  <input
                    type="url"
                    value={form.company.website}
                    onChange={(event) => updateCompany('website', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Pais">
                  <select
                    value={form.company.country}
                    onChange={(event) => updateCompany('country', event.target.value)}
                    className={selectClass}
                  >
                    <option value="" disabled hidden>Seleccionar</option>
                    {(options?.countries ?? []).map((country) => (
                      <option key={country} value={country}>
                        {country}
                      </option>
                    ))}
                  </select>
                </Field>
                <Field label="Departamento">
                  <input
                    value={form.company.department}
                    onChange={(event) => updateCompany('department', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Ciudad">
                  <input
                    value={form.company.city}
                    onChange={(event) => updateCompany('city', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Direccion fiscal">
                  <textarea
                    value={form.company.fiscal_address}
                    onChange={(event) => updateCompany('fiscal_address', event.target.value)}
                    className={textareaClass}
                  />
                </Field>
                <Field label="Direccion comercial">
                  <textarea
                    value={form.company.commercial_address}
                    onChange={(event) =>
                      updateCompany('commercial_address', event.target.value)
                    }
                    className={textareaClass}
                  />
                </Field>
                <Field label="Direccion completa" className="md:col-span-2">
                  <textarea
                    value={form.company.full_address}
                    onChange={(event) => updateCompany('full_address', event.target.value)}
                    className={textareaClass}
                  />
                </Field>
                <Field label="Codigo postal">
                  <input
                    value={form.company.postal_code}
                    onChange={(event) => updateCompany('postal_code', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Latitud">
                  <input
                    type="number"
                    step="0.000001"
                    value={form.company.latitude}
                    onChange={(event) => updateCompany('latitude', event.target.value)}
                    className={inputClass}
                  />
                </Field>
                <Field label="Longitud">
                  <input
                    type="number"
                    step="0.000001"
                    value={form.company.longitude}
                    onChange={(event) => updateCompany('longitude', event.target.value)}
                    className={inputClass}
                  />
                </Field>
              </div>
            </Panel>
          </div>

          <div className="space-y-6">
            <Panel title="Identidad visual" icon={<FiImage className="h-5 w-5" />}>
              <div className="space-y-4">
                <LogoUploader
                  currentUrl={data.company.logo_url}
                  file={logoFiles.logo_file}
                  inputKey={logoInputKey}
                  label="Logo para el menu desplegable"
                  name="logo_file"
                  onChange={handleLogoFile}
                />
                <LogoUploader
                  currentUrl={data.company.logo_dark_url}
                  file={logoFiles.logo_dark_file}
                  inputKey={logoInputKey}
                  label="Logo login"
                  name="logo_dark_file"
                  onChange={handleLogoFile}
                />
                <LogoUploader
                  currentUrl={data.company.favicon_url}
                  file={logoFiles.favicon_file}
                  inputKey={logoInputKey}
                  label="Favicon"
                  name="favicon_file"
                  onChange={handleLogoFile}
                />
                <button
                  type="button"
                  onClick={() => void handleUploadLogos()}
                  disabled={logoSaving}
                  className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary text-sm font-bold text-white disabled:opacity-60"
                >
                  {logoSaving ? (
                    <FiRefreshCw className="h-4 w-4 animate-spin" />
                  ) : (
                    <FiUploadCloud className="h-4 w-4" />
                  )}
                  {logoSaving ? 'Subiendo...' : 'Actualizar imagenes'}
                </button>
              </div>
            </Panel>

            <Panel title="Casa matriz" icon={<FiBriefcase className="h-5 w-5" />}>
              <Field label="Sucursal principal">
                <select
                  value={form.headquarters_id}
                  onChange={(event) => updateHeadquarters(event.target.value)}
                  className={selectClass}
                >
                  <option value="" disabled hidden>Seleccionar</option>
                  {branches.map((branch) => (
                    <option key={branch.id} value={branch.id}>
                      {branch.name}
                    </option>
                  ))}
                </select>
              </Field>
              <div className="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600 dark:bg-meta-4 dark:text-slate-300">
                <p className="font-bold text-black dark:text-white">
                  {selectedHeadquarters?.name || 'Sin seleccion'}
                </p>
                <p className="mt-1">{selectedHeadquarters?.full_address || '-'}</p>
                <p className="mt-1">{selectedHeadquarters?.phone || selectedHeadquarters?.email || '-'}</p>
              </div>
            </Panel>
          </div>
        </div>
      )}

      {activeTab === 'regional' && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <Panel title="Regional" icon={<FiGlobe className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Zona horaria">
                <select
                  value={form.regional.timezone}
                  onChange={(event) => updateGroup('regional', 'timezone', event.target.value)}
                  className={selectClass}
                >
                  {(options?.timezones ?? []).map((timezone) => (
                    <option key={timezone} value={timezone}>
                      {timezone}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Idioma">
                <select
                  value={form.regional.locale}
                  onChange={(event) => updateGroup('regional', 'locale', event.target.value)}
                  className={selectClass}
                >
                  {(options?.locales ?? []).map((locale) => (
                    <option key={locale} value={locale}>
                      {locale}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Formato de fecha">
                <select
                  value={form.regional.date_format}
                  onChange={(event) => updateGroup('regional', 'date_format', event.target.value)}
                  className={selectClass}
                >
                  {(options?.date_formats ?? []).map((format) => (
                    <option key={format} value={format}>
                      {format}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Formato de hora">
                <select
                  value={form.regional.time_format}
                  onChange={(event) => updateGroup('regional', 'time_format', event.target.value)}
                  className={selectClass}
                >
                  {(options?.time_formats ?? []).map((format) => (
                    <option key={format} value={format}>
                      {format}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
          </Panel>

          <Panel title="Moneda y formato" icon={<FiCreditCard className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Moneda principal">
                <select
                  value={form.finance.currency_id}
                  onChange={(event) => updateGroup('finance', 'currency_id', event.target.value)}
                  className={selectClass}
                  required
                >
                  <option value="" disabled hidden>Seleccionar</option>
                  {currencies.map((currency) => (
                    <option key={currency.id} value={currency.id}>
                      {currency.code} - {currency.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Posicion del simbolo">
                <select
                  value={form.finance.symbol_position}
                  onChange={(event) =>
                    updateGroup('finance', 'symbol_position', event.target.value)
                  }
                  className={selectClass}
                >
                  <option value="before">Antes</option>
                  <option value="after">Despues</option>
                </select>
              </Field>
              <Field label="Decimales">
                <input
                  type="number"
                  min="0"
                  max="6"
                  value={form.finance.decimal_places}
                  onChange={(event) =>
                    updateGroup('finance', 'decimal_places', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Separador decimal">
                <input
                  value={form.finance.decimal_separator}
                  onChange={(event) =>
                    updateGroup('finance', 'decimal_separator', event.target.value)
                  }
                  className={inputClass}
                  maxLength={4}
                />
              </Field>
              <Field label="Separador de miles">
                <input
                  value={form.finance.thousands_separator}
                  onChange={(event) =>
                    updateGroup('finance', 'thousands_separator', event.target.value)
                  }
                  className={inputClass}
                  maxLength={4}
                />
              </Field>
              <div className="rounded-lg bg-slate-50 p-4 text-sm text-slate-600 dark:bg-meta-4 dark:text-slate-300">
                <p className="font-bold text-black dark:text-white">Vista previa</p>
                <p className="mt-2 text-lg font-black text-primary">
                  {selectedCurrency?.symbol || '$'}
                  {form.finance.symbol_position === 'before' ? ' ' : ''}
                  1{form.finance.thousands_separator}234{form.finance.decimal_separator}
                  {'0'.repeat(Math.max(0, intOr(form.finance.decimal_places, 2)))}
                </p>
              </div>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
              <Field label="Tipo de cambio USD">
                <input
                  type="number"
                  min="0"
                  step="0.000001"
                  value={form.finance.dollar_exchange_rate}
                  onChange={(event) =>
                    updateGroup('finance', 'dollar_exchange_rate', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Fecha">
                <input
                  type="date"
                  value={form.finance.exchange_rate_date}
                  onChange={(event) =>
                    updateGroup('finance', 'exchange_rate_date', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Observacion">
                <input
                  value={form.finance.observation}
                  onChange={(event) => updateGroup('finance', 'observation', event.target.value)}
                  className={inputClass}
                />
              </Field>
            </div>
          </Panel>

          <Panel title="Ultimos tipos de cambio" icon={<FiCreditCard className="h-5 w-5" />}>
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-stroke text-xs font-bold uppercase text-slate-500 dark:border-strokedark">
                    <th className="px-3 py-3">Par</th>
                    <th className="px-3 py-3">Tasa</th>
                    <th className="px-3 py-3">Fecha</th>
                    <th className="px-3 py-3">Usuario</th>
                  </tr>
                </thead>
                <tbody>
                  {data.exchange_rates.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="px-3 py-5 text-center text-slate-500">
                        Sin registros
                      </td>
                    </tr>
                  ) : (
                    data.exchange_rates.map((rate) => (
                      <tr key={rate.id} className="border-b border-stroke dark:border-strokedark">
                        <td className="px-3 py-3 font-semibold text-black dark:text-white">
                          {rate.from_currency || '-'} / {rate.to_currency || '-'}
                        </td>
                        <td className="px-3 py-3">{rate.rate}</td>
                        <td className="px-3 py-3">{rate.date || '-'}</td>
                        <td className="px-3 py-3">{rate.created_by_name || '-'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}

      {activeTab === 'operations' && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <Panel title="Inventario" icon={<FiArchive className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Stock minimo">
                <input
                  type="number"
                  min="0"
                  step="0.01"
                  value={form.inventory.minimum_stock}
                  onChange={(event) =>
                    updateGroup('inventory', 'minimum_stock', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <ToggleField
                label="Alertas de inventario"
                checked={form.inventory.alerts_enabled}
                onChange={(checked) => updateGroup('inventory', 'alerts_enabled', checked)}
              />
              <ToggleField
                label="Permitir stock negativo"
                checked={form.inventory.allow_negative_stock}
                onChange={(checked) =>
                  updateGroup('inventory', 'allow_negative_stock', checked)
                }
              />
              <ToggleField
                label="Controlar lotes"
                checked={form.inventory.track_lots}
                onChange={(checked) => updateGroup('inventory', 'track_lots', checked)}
              />
              <ToggleField
                label="Controlar series"
                checked={form.inventory.track_serials}
                onChange={(checked) => updateGroup('inventory', 'track_serials', checked)}
              />
            </div>
          </Panel>

          <Panel title="Ventas" icon={<FiCreditCard className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <ToggleField
                label="Numeracion automatica"
                checked={form.sales.auto_numbering}
                onChange={(checked) => updateGroup('sales', 'auto_numbering', checked)}
              />
              <Field label="Prefijo">
                <input
                  value={form.sales.prefix}
                  onChange={(event) => updateGroup('sales', 'prefix', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Sufijo">
                <input
                  value={form.sales.suffix}
                  onChange={(event) => updateGroup('sales', 'suffix', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Digitos">
                <input
                  type="number"
                  min="1"
                  max="20"
                  value={form.sales.digits}
                  onChange={(event) => updateGroup('sales', 'digits', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Redondeo">
                <input
                  type="number"
                  min="0"
                  max="6"
                  value={form.sales.rounding}
                  onChange={(event) => updateGroup('sales', 'rounding', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Impuesto por defecto">
                <select
                  value={form.sales.default_tax_id}
                  onChange={(event) =>
                    updateGroup('sales', 'default_tax_id', event.target.value)
                  }
                  className={selectClass}
                >
                  <option value="">Ninguno</option>
                  {taxes.map((tax) => (
                    <option key={tax.id} value={tax.id}>
                      {tax.code ? `${tax.code} - ` : ''}
                      {tax.name}
                    </option>
                  ))}
                </select>
              </Field>
            </div>
          </Panel>

          <Panel title="Compras" icon={<FiArchive className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Prefijo">
                <input
                  value={form.purchases.prefix}
                  onChange={(event) => updateGroup('purchases', 'prefix', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Impuesto por defecto">
                <select
                  value={form.purchases.default_tax_id}
                  onChange={(event) =>
                    updateGroup('purchases', 'default_tax_id', event.target.value)
                  }
                  className={selectClass}
                >
                  <option value="">Ninguno</option>
                  {taxes.map((tax) => (
                    <option key={tax.id} value={tax.id}>
                      {tax.code ? `${tax.code} - ` : ''}
                      {tax.name}
                    </option>
                  ))}
                </select>
              </Field>
              <ToggleField
                label="Requiere autorizacion"
                checked={form.purchases.authorization_required}
                onChange={(checked) =>
                  updateGroup('purchases', 'authorization_required', checked)
                }
              />
            </div>
          </Panel>

          <Panel title="Impresion" icon={<FiSettings className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Formato">
                <select
                  value={form.printing.default_format}
                  onChange={(event) =>
                    updateGroup('printing', 'default_format', event.target.value)
                  }
                  className={selectClass}
                >
                  {(options?.print_formats ?? ['ticket', 'letter', 'a4']).map((format) => (
                    <option key={format} value={format}>
                      {format.toUpperCase()}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Impresora">
                <input
                  value={form.printing.default_printer}
                  onChange={(event) =>
                    updateGroup('printing', 'default_printer', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
            </div>
          </Panel>
        </div>
      )}

      {activeTab === 'security' && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <Panel title="Correo SMTP" icon={<FiMail className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Host SMTP">
                <input
                  value={form.mail.smtp_host}
                  onChange={(event) => updateGroup('mail', 'smtp_host', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Puerto">
                <input
                  type="number"
                  min="1"
                  max="65535"
                  value={form.mail.smtp_port}
                  onChange={(event) => updateGroup('mail', 'smtp_port', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Usuario SMTP">
                <input
                  value={form.mail.smtp_username}
                  onChange={(event) =>
                    updateGroup('mail', 'smtp_username', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Contrasena SMTP">
                <input
                  type="password"
                  value={form.mail.smtp_password}
                  onChange={(event) =>
                    updateGroup('mail', 'smtp_password', event.target.value)
                  }
                  className={inputClass}
                  placeholder="Sin cambios"
                />
              </Field>
              <Field label="Cifrado">
                <select
                  value={form.mail.smtp_encryption}
                  onChange={(event) =>
                    updateGroup('mail', 'smtp_encryption', event.target.value)
                  }
                  className={selectClass}
                >
                  <option value="none">Ninguno</option>
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                </select>
              </Field>
              <Field label="Correo remitente">
                <input
                  type="email"
                  value={form.mail.from_address}
                  onChange={(event) => updateGroup('mail', 'from_address', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Nombre remitente">
                <input
                  value={form.mail.from_name}
                  onChange={(event) => updateGroup('mail', 'from_name', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <div className="flex items-end">
                <button
                  type="button"
                  onClick={() => void handleTestMail()}
                  disabled={testingMail}
                  className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-primary text-sm font-bold text-primary transition hover:bg-primary hover:text-white disabled:opacity-60"
                >
                  {testingMail ? (
                    <FiRefreshCw className="h-4 w-4 animate-spin" />
                  ) : (
                    <FiMail className="h-4 w-4" />
                  )}
                  {testingMail ? 'Probando...' : 'Probar SMTP'}
                </button>
              </div>
            </div>
          </Panel>

          <Panel title="Seguridad" icon={<FiLock className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field label="Minutos de sesion">
                <input
                  type="number"
                  min="5"
                  max="43200"
                  value={form.security.session_timeout_minutes}
                  onChange={(event) =>
                    updateGroup('security', 'session_timeout_minutes', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Intentos maximos">
                <input
                  type="number"
                  min="1"
                  max="20"
                  value={form.security.max_login_attempts}
                  onChange={(event) =>
                    updateGroup('security', 'max_login_attempts', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <ToggleField
                label="Bloqueo de acceso"
                checked={form.security.lockout_enabled}
                onChange={(checked) => updateGroup('security', 'lockout_enabled', checked)}
              />
              <Field label="Minutos de bloqueo">
                <input
                  type="number"
                  min="1"
                  max="1440"
                  value={form.security.lockout_minutes}
                  onChange={(event) =>
                    updateGroup('security', 'lockout_minutes', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Longitud minima">
                <input
                  type="number"
                  min="8"
                  max="64"
                  value={form.security.password_min_length}
                  onChange={(event) =>
                    updateGroup('security', 'password_min_length', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
              <Field label="Complejidad">
                <select
                  value={form.security.password_complexity}
                  onChange={(event) =>
                    updateGroup('security', 'password_complexity', event.target.value)
                  }
                  className={selectClass}
                >
                  <option value="low">Baja</option>
                  <option value="medium">Media</option>
                  <option value="high">Alta</option>
                </select>
              </Field>
              <ToggleField
                label="Doble factor"
                checked={form.security.two_factor_enabled}
                onChange={(checked) => updateGroup('security', 'two_factor_enabled', checked)}
              />
            </div>
          </Panel>
        </div>
      )}

      {activeTab === 'backup' && (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <Panel title="Respaldos" icon={<FiDatabase className="h-5 w-5" />}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <ToggleField
                label="Respaldos programados"
                checked={form.backup.schedule_enabled}
                onChange={(checked) => updateGroup('backup', 'schedule_enabled', checked)}
              />
              <Field label="Frecuencia">
                <select
                  value={form.backup.frequency}
                  onChange={(event) => updateGroup('backup', 'frequency', event.target.value)}
                  className={selectClass}
                >
                  <option value="daily">Diario</option>
                  <option value="weekly">Semanal</option>
                  <option value="monthly">Mensual</option>
                </select>
              </Field>
              <Field label="Hora">
                <input
                  type="time"
                  value={form.backup.time}
                  onChange={(event) => updateGroup('backup', 'time', event.target.value)}
                  className={inputClass}
                />
              </Field>
              <Field label="Retencion en dias">
                <input
                  type="number"
                  min="1"
                  max="365"
                  value={form.backup.retention_days}
                  onChange={(event) =>
                    updateGroup('backup', 'retention_days', event.target.value)
                  }
                  className={inputClass}
                />
              </Field>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <button
                type="button"
                onClick={() => void handleCreateBackup()}
                disabled={backupSaving}
                className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-white disabled:opacity-60"
              >
                {backupSaving ? (
                  <FiRefreshCw className="h-4 w-4 animate-spin" />
                ) : (
                  <FiDatabase className="h-4 w-4" />
                )}
                Crear respaldo
              </button>
              <div className="flex gap-2">
                <input
                  key={restoreInputKey}
                  type="file"
                  accept=".json,application/json,text/plain"
                  onChange={handleRestoreFile}
                  className="h-11 min-w-0 flex-1 rounded-lg border border-stroke px-3 text-sm dark:border-strokedark"
                />
                <button
                  type="button"
                  onClick={() => void handleRestoreBackup()}
                  disabled={backupSaving}
                  title="Restaurar respaldo"
                  className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-primary text-primary transition hover:bg-primary hover:text-white disabled:opacity-60"
                  aria-label="Restaurar respaldo"
                >
                  <FiUploadCloud className="h-5 w-5" />
                </button>
              </div>
            </div>
          </Panel>

          <Panel title="Sistema" icon={<FiSettings className="h-5 w-5" />}>
            <div className="space-y-4">
              <ToggleField
                label="Auditoria"
                checked={form.system.audit_enabled}
                onChange={(checked) => updateGroup('system', 'audit_enabled', checked)}
              />
              <ToggleField
                label="Notificaciones"
                checked={form.system.notifications_enabled}
                onChange={(checked) =>
                  updateGroup('system', 'notifications_enabled', checked)
                }
              />
            </div>
          </Panel>

          <Panel title="Historial de respaldos" icon={<FiClock className="h-5 w-5" />}>
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-stroke text-xs font-bold uppercase text-slate-500 dark:border-strokedark">
                    <th className="px-3 py-3">Archivo</th>
                    <th className="px-3 py-3">Tamano</th>
                    <th className="px-3 py-3">Estado</th>
                    <th className="px-3 py-3">Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  {data.backups.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="px-3 py-5 text-center text-slate-500">
                        Sin respaldos
                      </td>
                    </tr>
                  ) : (
                    data.backups.map((backup) => (
                      <tr
                        key={backup.id}
                        className="border-b border-stroke dark:border-strokedark"
                      >
                        <td className="max-w-70 truncate px-3 py-3 font-semibold text-black dark:text-white">
                          {backup.file_name}
                        </td>
                        <td className="px-3 py-3">{formatFileSize(backup.file_size)}</td>
                        <td className="px-3 py-3">{backup.status}</td>
                        <td className="px-3 py-3">{formatDate(backup.created_at)}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </Panel>
        </div>
      )}
    </form>
  );
}
