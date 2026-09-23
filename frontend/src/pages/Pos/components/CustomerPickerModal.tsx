import { useMemo, useRef, useState } from 'react';
import { FiCheck, FiSearch, FiUserPlus, FiUsers, FiX } from 'react-icons/fi';
import { Customer } from '../../../types/customer';
import QuickCustomerModal from './QuickCustomerModal';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

// Quita diacriticos (tildes) despues de normalizar a NFD, igual que
// CustomerSelect, para que buscar "codigo" tambien encuentre "código".
const DIACRITICS_REGEX = /[̀-ͯ]/g;

function normalizeText(value: string) {
  return value
    .toLowerCase()
    .normalize('NFD')
    .replace(DIACRITICS_REGEX, '');
}

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

// Ventana amplia para elegir cliente sin tener que cerrar el modal que la
// abrio (ej. Cobrar factura): mismo contenido que el desplegable
// CustomerSelect del encabezado, pero en tarjetas grandes en grilla y con
// mas espacio para buscar, en vez de una lista angosta.
export default function CustomerPickerModal({
  customers,
  selectedId,
  token,
  onSelect,
  onClose,
  onCreated,
}: {
  customers: Customer[];
  selectedId: string;
  token?: string;
  onSelect: (customerId: string) => void;
  onClose: () => void;
  onCreated?: (customer: Customer) => void;
}) {
  const [search, setSearch] = useState('');
  const [showQuickCreate, setShowQuickCreate] = useState(false);
  const searchInputRef = useRef<HTMLInputElement>(null);

  const filteredCustomers = useMemo(() => {
    const normalized = normalizeText(search.trim());
    if (!normalized) return customers;

    return customers.filter((customer) =>
      normalizeText(`${customer.full_name} ${customer.code} ${customer.tax_id ?? ''}`).includes(normalized),
    );
  }, [customers, search]);

  return (
    <div className="fixed inset-0 z-[100010] flex items-center justify-center bg-black/60 px-4 py-6" onClick={onClose}>
      <div
        className="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-primary to-[#4338CA] px-6 py-5 text-white">
          <span className="flex items-center gap-2.5 text-base font-black uppercase tracking-wide">
            <FiUsers className="h-5 w-5" /> Selecciona un cliente
          </span>
          <button type="button" onClick={onClose} className="rounded-full p-1.5 transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="flex items-center gap-3 border-b border-stroke p-4 dark:border-strokedark">
          <div className="relative flex-1">
            <FiSearch className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              ref={searchInputRef}
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Buscar por nombre, codigo o RUC..."
              className="h-12 w-full rounded-xl border border-stroke bg-slate-50 pl-10 pr-3 text-sm outline-none transition focus:border-primary dark:border-strokedark dark:bg-meta-4"
            />
          </div>
          {token && (
            <button
              type="button"
              onClick={() => setShowQuickCreate(true)}
              className="flex h-12 shrink-0 items-center gap-2 rounded-xl bg-primary px-4 text-sm font-bold text-white transition hover:bg-primary/90"
            >
              <FiUserPlus className="h-4 w-4" /> Nuevo cliente
            </button>
          )}
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto p-4">
          {filteredCustomers.length === 0 ? (
            <div className="flex flex-col items-center gap-2 px-4 py-16 text-center text-sm text-slate-400">
              <FiUsers className="h-8 w-8" />
              No se encontraron clientes.
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {filteredCustomers.map((customer) => {
                const isSelected = String(customer.id) === selectedId;
                const isCredit = customer.sales_type === 'CREDITO';
                const hasCredit = customer.credit_available > 0;
                const availablePercent = customer.credit_limit > 0
                  ? Math.max(0, Math.min(100, (customer.credit_available / customer.credit_limit) * 100))
                  : 0;

                return (
                  <button
                    key={customer.id}
                    type="button"
                    onClick={() => {
                      onSelect(String(customer.id));
                      onClose();
                    }}
                    className={`flex flex-col items-start gap-1.5 rounded-xl border-2 p-3.5 text-left transition ${
                      isSelected
                        ? 'border-primary bg-primary/5 shadow-sm'
                        : 'border-stroke hover:border-primary/50 hover:bg-slate-50 dark:border-strokedark dark:hover:bg-white/[0.03]'
                    }`}
                  >
                    <div className="flex w-full items-start justify-between gap-2">
                      <span className="truncate text-sm font-bold text-black dark:text-white">{customer.full_name}</span>
                      {isSelected && <FiCheck className="mt-0.5 h-4 w-4 shrink-0 text-primary" />}
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-slate-400">{customer.code}</span>
                      <SalesTypeBadge salesType={customer.sales_type} />
                    </div>

                    {isCredit && (
                      <div className="mt-1 w-full">
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
                      </div>
                    )}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {showQuickCreate && token && (
        <QuickCustomerModal
          token={token}
          onClose={() => setShowQuickCreate(false)}
          onCreated={(customer) => {
            onCreated?.(customer);
            onSelect(String(customer.id));
            setShowQuickCreate(false);
            onClose();
          }}
        />
      )}
    </div>
  );
}
