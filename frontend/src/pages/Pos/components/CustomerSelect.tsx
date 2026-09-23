import { useEffect, useMemo, useRef, useState } from 'react';
import { FiCheck, FiChevronDown, FiSearch, FiUser, FiUsers } from 'react-icons/fi';
import { Customer } from '../../../types/customer';
import ClickOutside from '../../../components/ClickOutside';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

// Quita diacriticos (tildes) despues de normalizar a NFD, para que buscar
// "codigo" tambien encuentre "código". ̀-ͯ es el bloque Unicode
// de "combining diacritical marks" que NFD deja sueltos sobre cada letra.
const DIACRITICS_REGEX = /[̀-ͯ]/g;

function normalizeText(value: string) {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(DIACRITICS_REGEX, '');
}

// Insignia "Contado"/"Credito": mismos colores que ya usa el listado de
// Clientes (verde esmeralda / ambar), para que el mismo estado se vea igual
// en toda la app.
function SalesTypeBadge({ salesType }: { salesType: Customer['sales_type'] }) {
  const isCash = salesType === 'CONTADO';

  return (
    <span
      className={`inline-flex w-fit shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-bold ${
        isCash
          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
          : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
      }`}
    >
      {isCash ? 'Contado' : 'Credito'}
    </span>
  );
}

export default function CustomerSelect({
  customers,
  value,
  onChange,
}: {
  customers: Customer[];
  value: string;
  onChange: (customerId: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const searchInputRef = useRef<HTMLInputElement>(null);

  const selectedCustomer = useMemo(
    () => customers.find((customer) => String(customer.id) === value) ?? null,
    [customers, value],
  );

  const filteredCustomers = useMemo(() => {
    const normalized = normalizeText(search.trim());
    if (!normalized) return customers;

    return customers.filter((customer) =>
      normalizeText(`${customer.full_name} ${customer.code} ${customer.tax_id ?? ''}`).includes(normalized),
    );
  }, [customers, search]);

  useEffect(() => {
    if (open) {
      // Pequeño delay: al abrir, el input aun no esta montado en el mismo tick.
      const timeout = window.setTimeout(() => searchInputRef.current?.focus(), 30);
      return () => window.clearTimeout(timeout);
    }
    setSearch('');
  }, [open]);

  function handleSelect(customerId: number) {
    onChange(String(customerId));
    setOpen(false);
  }

  return (
    <ClickOutside onClick={() => setOpen(false)} className="relative">
      <button
        type="button"
        onClick={() => setOpen((current) => !current)}
        className="flex h-12 w-[260px] items-center gap-2 rounded-xl border border-stroke bg-white pl-3 pr-2.5 text-left text-sm outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark"
      >
        <FiUser className="shrink-0 text-slate-400" />
        {selectedCustomer ? (
          <span className="flex min-w-0 flex-1 items-center gap-2">
            <span className="truncate font-semibold text-black dark:text-white">{selectedCustomer.full_name}</span>
            <SalesTypeBadge salesType={selectedCustomer.sales_type} />
          </span>
        ) : (
          <span className="flex-1 truncate text-slate-400">Selecciona un cliente</span>
        )}
        <FiChevronDown className={`h-4 w-4 shrink-0 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute left-0 top-[calc(100%+6px)] z-50 w-[360px] overflow-hidden rounded-xl border border-stroke bg-white shadow-elevated dark:border-strokedark dark:bg-boxdark">
          <div className="border-b border-stroke p-2 dark:border-strokedark">
            <div className="relative">
              <FiSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                ref={searchInputRef}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por nombre, codigo o RUC..."
                className="h-10 w-full rounded-lg border border-stroke bg-slate-50 pl-9 pr-3 text-sm outline-none focus:border-primary dark:border-strokedark dark:bg-meta-4"
              />
            </div>
          </div>

          <div className="max-h-72 overflow-y-auto py-1">
            {filteredCustomers.length === 0 && (
              <div className="flex flex-col items-center gap-2 px-4 py-8 text-center text-sm text-slate-400">
                <FiUsers className="h-6 w-6" />
                No se encontraron clientes.
              </div>
            )}

            {filteredCustomers.map((customer) => {
              const isSelected = String(customer.id) === value;
              const isCredit = customer.sales_type === 'CREDITO';
              const hasCredit = customer.credit_available > 0;
              const availablePercent = customer.credit_limit > 0
                ? Math.max(0, Math.min(100, (customer.credit_available / customer.credit_limit) * 100))
                : 0;

              return (
                <button
                  key={customer.id}
                  type="button"
                  onClick={() => handleSelect(customer.id)}
                  className={`flex w-full items-start gap-2 px-3 py-2.5 text-left transition ${
                    isSelected ? 'bg-primary/5' : 'hover:bg-slate-50 dark:hover:bg-white/[0.03]'
                  }`}
                >
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <span className="truncate text-sm font-bold text-black dark:text-white">{customer.full_name}</span>
                      <SalesTypeBadge salesType={customer.sales_type} />
                    </div>

                    {isCredit && (
                      <div className="mt-1.5">
                        <div className="flex items-baseline justify-between gap-2 text-xs">
                          <span className="text-slate-400">Disponible</span>
                          <span className={`font-bold ${hasCredit ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'}`}>
                            {formatCurrency(customer.credit_available)}
                          </span>
                        </div>
                        <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                          <div
                            className={`h-full rounded-full ${hasCredit ? 'bg-emerald-400' : 'bg-red-400'}`}
                            style={{ width: `${availablePercent}%` }}
                          />
                        </div>
                        <div className="mt-0.5 text-right text-[11px] text-slate-400">
                          de {formatCurrency(customer.credit_limit)} limite
                        </div>
                      </div>
                    )}
                  </div>
                  {isSelected && <FiCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary" />}
                </button>
              );
            })}
          </div>
        </div>
      )}
    </ClickOutside>
  );
}
