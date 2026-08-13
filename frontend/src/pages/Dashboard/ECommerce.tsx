import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  FiAlertTriangle,
  FiArrowRight,
  FiBox,
  FiFileText,
  FiShoppingCart,
  FiUsers,
} from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { apiRequest } from '../../services/api';

// Panel principal del ERP. A diferencia de la plantilla original (que
// mostraba datos de muestra: "Total Views", un mapa, un chat falso), este
// panel consume los mismos endpoints que ya usan las paginas de Productos,
// Ventas, Compras y Clientes, para reflejar el estado real del negocio.

type DashboardProduct = {
  id: number;
  name: string;
  code: string;
  stock: number;
  minimum_stock: number;
  cost: number;
  status: number;
};

type DashboardSale = {
  id: number;
  sale_number: string;
  sale_date: string | null;
  status: string;
  customer_name: string | null;
  total: number;
  balance_due: number;
};

type DashboardPurchase = {
  id: number;
  purchase_number?: string;
  purchase_date?: string | null;
  status: string;
  supplier_name?: string | null;
  total: number;
  balance_due: number;
};

type DashboardCustomer = {
  id: number;
  full_name: string;
  current_balance: number;
  credit_limit: number;
};

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function toNumber(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

const saleStatusStyles: Record<string, string> = {
  DRAFT: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  PENDING: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  COMPLETED: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  CANCELLED: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};
const saleStatusLabels: Record<string, string> = {
  DRAFT: 'Borrador',
  PENDING: 'Pendiente',
  COMPLETED: 'Confirmada',
  CANCELLED: 'Anulada',
};
const purchaseStatusStyles: Record<string, string> = {
  DRAFT: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-200',
  PENDING: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  RECEIVED: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  CANCELLED: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};
const purchaseStatusLabels: Record<string, string> = {
  DRAFT: 'Borrador',
  PENDING: 'Pendiente',
  RECEIVED: 'Recibida',
  CANCELLED: 'Anulada',
};

function StatCard({
  label,
  value,
  hint,
  icon: Icon,
  accent,
}: {
  label: string;
  value: string;
  hint: string;
  icon: React.ComponentType<{ className?: string }>;
  accent: 'primary' | 'emerald' | 'amber' | 'violet';
}) {
  const accentClasses: Record<typeof accent, string> = {
    primary: 'bg-primary/10 text-primary',
    emerald: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
  };

  return (
    <div className="rounded-xl border border-stroke bg-white p-5 shadow-card transition-shadow hover:shadow-soft dark:border-strokedark dark:bg-boxdark">
      <div className={`mb-4 flex h-11 w-11 items-center justify-center rounded-lg ${accentClasses[accent]}`}>
        <Icon className="h-5.5 w-5.5" />
      </div>
      <p className="text-xs font-semibold uppercase tracking-wide text-bodydark2">{label}</p>
      <p className="mt-1.5 text-2xl font-bold text-black dark:text-white">{value}</p>
      <p className="mt-1 text-xs text-body dark:text-bodydark">{hint}</p>
    </div>
  );
}

function PanelCard({
  title,
  action,
  children,
}: {
  title: string;
  action?: { label: string; to: string };
  children: React.ReactNode;
}) {
  return (
    <div className="rounded-xl border border-stroke bg-white shadow-card dark:border-strokedark dark:bg-boxdark">
      <div className="flex items-center justify-between border-b border-stroke px-5 py-4 dark:border-strokedark">
        <h3 className="font-semibold text-black dark:text-white">{title}</h3>
        {action && (
          <Link
            to={action.to}
            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
          >
            {action.label} <FiArrowRight className="h-3 w-3" />
          </Link>
        )}
      </div>
      <div className="p-5">{children}</div>
    </div>
  );
}

const ECommerce = () => {
  const { token, user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [products, setProducts] = useState<DashboardProduct[]>([]);
  const [productMeta, setProductMeta] = useState({ total: 0, inventory_value: 0 });
  const [sales, setSales] = useState<DashboardSale[]>([]);
  const [saleMeta, setSaleMeta] = useState({ total: 0, total_amount: 0, balance_due: 0 });
  const [purchases, setPurchases] = useState<DashboardPurchase[]>([]);
  const [purchaseMeta, setPurchaseMeta] = useState({ total: 0, total_amount: 0, balance_due: 0 });
  const [customers, setCustomers] = useState<DashboardCustomer[]>([]);

  useEffect(() => {
    if (!token) return;

    let cancelled = false;

    async function loadDashboard() {
      try {
        const [productsRes, salesRes, purchasesRes, customersRes] = await Promise.all([
          apiRequest<{ data: any[]; meta: any }>('/products', {}, token),
          apiRequest<{ data: any[]; meta: any }>('/sales', {}, token),
          apiRequest<{ data: any[]; meta: any }>('/purchases', {}, token),
          apiRequest<{ data: any[] }>('/customers', {}, token),
        ]);

        if (cancelled) return;

        setProducts(
          (productsRes.data || []).map((p) => ({
            id: Number(p.id),
            name: p.name || p.full_name || 'Producto',
            code: p.code || '',
            stock: toNumber(p.stock),
            minimum_stock: toNumber(p.minimum_stock),
            cost: toNumber(p.cost),
            status: toNumber(p.status),
          })),
        );
        setProductMeta({
          total: toNumber(productsRes.meta?.total),
          inventory_value: toNumber(productsRes.meta?.inventory_value),
        });

        setSales(
          (salesRes.data || []).map((s) => ({
            id: Number(s.id),
            sale_number: s.sale_number,
            sale_date: s.sale_date,
            status: s.status,
            customer_name: s.customer_name,
            total: toNumber(s.total),
            balance_due: toNumber(s.balance_due),
          })),
        );
        setSaleMeta({
          total: toNumber(salesRes.meta?.total),
          total_amount: toNumber(salesRes.meta?.total_amount),
          balance_due: toNumber(salesRes.meta?.balance_due),
        });

        setPurchases(
          (purchasesRes.data || []).map((p) => ({
            id: Number(p.id),
            purchase_number: p.purchase_number,
            purchase_date: p.purchase_date,
            status: p.status,
            supplier_name: p.supplier_name,
            total: toNumber(p.total),
            balance_due: toNumber(p.balance_due),
          })),
        );
        setPurchaseMeta({
          total: toNumber(purchasesRes.meta?.total),
          total_amount: toNumber(purchasesRes.meta?.total_amount),
          balance_due: toNumber(purchasesRes.meta?.balance_due),
        });

        setCustomers(
          (customersRes.data || []).map((c) => ({
            id: Number(c.id),
            full_name: c.full_name,
            current_balance: toNumber(c.current_balance),
            credit_limit: toNumber(c.credit_limit),
          })),
        );
      } catch {
        // Silencioso: si algun endpoint falla (p.ej. el usuario no tiene
        // permiso sobre ese modulo), esa seccion del panel simplemente
        // queda vacia en vez de romper el dashboard completo.
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void loadDashboard();
    return () => {
      cancelled = true;
    };
  }, [token]);

  const lowStockProducts = useMemo(
    () =>
      products
        .filter((p) => p.status === 1 && p.stock <= p.minimum_stock)
        .sort((a, b) => a.stock - b.stock)
        .slice(0, 5),
    [products],
  );

  const topDebtors = useMemo(
    () =>
      customers
        .filter((c) => c.current_balance > 0)
        .sort((a, b) => b.current_balance - a.current_balance)
        .slice(0, 5),
    [customers],
  );

  const recentSales = sales.slice(0, 6);
  const recentPurchases = purchases.slice(0, 6);

  const today = useMemo(
    () =>
      new Intl.DateTimeFormat('es-NI', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
      }).format(new Date()),
    [],
  );

  const firstName = (user?.full_name || 'de vuelta').split(' ')[0];

  return (
    <>
      <div className="mb-6 flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
        <div>
          <h1 className="text-title-sm font-bold text-black dark:text-white">
            Hola, {firstName} 👋
          </h1>
          <p className="mt-1 text-sm capitalize text-bodydark2">{today}</p>
        </div>
      </div>

      {loading ? (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          {[0, 1, 2, 3].map((i) => (
            <div
              key={i}
              className="h-36 animate-pulse rounded-xl border border-stroke bg-white dark:border-strokedark dark:bg-boxdark"
            />
          ))}
        </div>
      ) : (
        <>
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <StatCard
              label="Ventas del periodo"
              value={formatCurrency(saleMeta.total_amount)}
              hint={`${saleMeta.total} facturas emitidas`}
              icon={FiFileText}
              accent="primary"
            />
            <StatCard
              label="Cuentas por cobrar"
              value={formatCurrency(saleMeta.balance_due)}
              hint="Saldo pendiente de clientes"
              icon={FiUsers}
              accent="amber"
            />
            <StatCard
              label="Compras del periodo"
              value={formatCurrency(purchaseMeta.total_amount)}
              hint={`${purchaseMeta.total} ordenes recibidas`}
              icon={FiShoppingCart}
              accent="violet"
            />
            <StatCard
              label="Valor de inventario"
              value={formatCurrency(productMeta.inventory_value)}
              hint={`${productMeta.total} productos activos`}
              icon={FiBox}
              accent="emerald"
            />
          </div>

          <div className="mt-4 grid grid-cols-1 gap-4 md:mt-6 xl:grid-cols-2">
            <PanelCard title="Ventas recientes" action={{ label: 'Ver todas', to: '/sales' }}>
              {recentSales.length === 0 ? (
                <p className="py-6 text-center text-sm text-bodydark2">
                  Aun no hay facturas registradas.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <tbody className="divide-y divide-stroke dark:divide-strokedark">
                      {recentSales.map((sale) => (
                        <tr key={sale.id}>
                          <td className="py-2.5 pr-3">
                            <Link
                              to={`/sales/${sale.id}`}
                              className="font-semibold text-black hover:text-primary dark:text-white"
                            >
                              {sale.sale_number}
                            </Link>
                            <p className="text-xs text-bodydark2">{sale.customer_name || 'Sin cliente'}</p>
                          </td>
                          <td className="py-2.5 pr-3 text-right font-semibold text-black dark:text-white">
                            {formatCurrency(sale.total)}
                          </td>
                          <td className="py-2.5 text-right">
                            <span
                              className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                saleStatusStyles[sale.status] || ''
                              }`}
                            >
                              {saleStatusLabels[sale.status] || sale.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </PanelCard>

            <PanelCard title="Compras recientes" action={{ label: 'Ver todas', to: '/purchases' }}>
              {recentPurchases.length === 0 ? (
                <p className="py-6 text-center text-sm text-bodydark2">
                  Aun no hay compras registradas.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-sm">
                    <tbody className="divide-y divide-stroke dark:divide-strokedark">
                      {recentPurchases.map((purchase) => (
                        <tr key={purchase.id}>
                          <td className="py-2.5 pr-3">
                            <Link
                              to={`/purchases/${purchase.id}`}
                              className="font-semibold text-black hover:text-primary dark:text-white"
                            >
                              {purchase.purchase_number}
                            </Link>
                            <p className="text-xs text-bodydark2">{purchase.supplier_name || 'Sin proveedor'}</p>
                          </td>
                          <td className="py-2.5 pr-3 text-right font-semibold text-black dark:text-white">
                            {formatCurrency(purchase.total)}
                          </td>
                          <td className="py-2.5 text-right">
                            <span
                              className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                purchaseStatusStyles[purchase.status] || ''
                              }`}
                            >
                              {purchaseStatusLabels[purchase.status] || purchase.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </PanelCard>

            <PanelCard title="Stock bajo" action={{ label: 'Ver productos', to: '/products' }}>
              {lowStockProducts.length === 0 ? (
                <p className="py-6 text-center text-sm text-bodydark2">
                  Todo el inventario esta en niveles saludables.
                </p>
              ) : (
                <ul className="divide-y divide-stroke dark:divide-strokedark">
                  {lowStockProducts.map((product) => (
                    <li key={product.id} className="flex items-center justify-between gap-3 py-2.5">
                      <div className="flex items-center gap-3">
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
                          <FiAlertTriangle className="h-4 w-4" />
                        </span>
                        <div>
                          <p className="text-sm font-medium text-black dark:text-white">{product.name}</p>
                          <p className="text-xs text-bodydark2">{product.code}</p>
                        </div>
                      </div>
                      <span className="text-sm font-semibold text-amber-600 dark:text-amber-400">
                        {product.stock} / {product.minimum_stock}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </PanelCard>

            <PanelCard title="Clientes con saldo pendiente" action={{ label: 'Ver clientes', to: '/customers' }}>
              {topDebtors.length === 0 ? (
                <p className="py-6 text-center text-sm text-bodydark2">
                  Ningun cliente tiene saldo pendiente.
                </p>
              ) : (
                <ul className="divide-y divide-stroke dark:divide-strokedark">
                  {topDebtors.map((customer) => (
                    <li key={customer.id} className="flex items-center justify-between gap-3 py-2.5">
                      <p className="text-sm font-medium text-black dark:text-white">{customer.full_name}</p>
                      <span className="text-sm font-semibold text-black dark:text-white">
                        {formatCurrency(customer.current_balance)}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </PanelCard>
          </div>
        </>
      )}
    </>
  );
};

export default ECommerce;
