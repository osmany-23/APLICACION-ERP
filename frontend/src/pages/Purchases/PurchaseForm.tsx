import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { FiPlus, FiTrash2 } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { PurchaseItemDraft, PurchaseSaveResponse } from '../../types/purchase';

type Supplier = { id: number; code: string; name: string; payable_balance: number };
type Warehouse = { id: number; name: string };
type PaymentMethod = { id: number; name: string; cash: boolean };
type PaymentTerm = { id: number; name: string; days: number };
type ProductOption = {
  id: number;
  code: string;
  name: string;
  cost: number;
  tax_type: string;
  tax_percentage: number;
  stock: number;
  allow_purchase: boolean;
};

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function lineTotals(line: PurchaseItemDraft) {
  const base = Math.max(line.quantity * line.unit_cost - line.discount, 0);
  const taxRate = line.tax_type === 'TAXABLE' ? line.tax_percentage : 0;
  const tax = Math.round(base * (taxRate / 100) * 100) / 100;

  return { base, tax, total: base + tax };
}

export default function PurchaseForm() {
  const { token } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const initialSupplierId = searchParams.get('supplier_id') ?? '';

  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [products, setProducts] = useState<ProductOption[]>([]);
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [paymentTerms, setPaymentTerms] = useState<PaymentTerm[]>([]);
  const [loadingCatalogs, setLoadingCatalogs] = useState(true);

  const [supplierId, setSupplierId] = useState(initialSupplierId);
  const [warehouseId, setWarehouseId] = useState('');
  const [paymentMethodId, setPaymentMethodId] = useState('');
  const [paymentTermId, setPaymentTermId] = useState('');
  const [purchaseDate, setPurchaseDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [notes, setNotes] = useState('');
  const [items, setItems] = useState<PurchaseItemDraft[]>([]);
  const [selectedProductId, setSelectedProductId] = useState('');

  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadCatalogs = useCallback(async () => {
    if (!token) return;

    setLoadingCatalogs(true);

    try {
      const [suppliersRes, warehousesRes, productsRes, methodsRes, termsRes] = await Promise.all([
        apiRequest<{ data: Supplier[] }>('/relations/suppliers', {}, token),
        apiRequest<{ data: Warehouse[] }>('/relations/warehouses', {}, token),
        apiRequest<{ data: ProductOption[] }>('/products', {}, token),
        apiRequest<{ data: PaymentMethod[] }>('/settings/payment-methods', {}, token),
        apiRequest<{ data: PaymentTerm[] }>('/settings/payment-terms', {}, token),
      ]);

      setSuppliers(suppliersRes.data);
      setWarehouses(warehousesRes.data);
      setProducts(productsRes.data.filter((product) => product.allow_purchase));
      setPaymentMethods(methodsRes.data);
      setPaymentTerms(termsRes.data);

      if (warehousesRes.data.length === 1) {
        setWarehouseId(String(warehousesRes.data[0].id));
      }
    } catch (loadError) {
      setError(getErrorMessage(loadError));
    } finally {
      setLoadingCatalogs(false);
    }
  }, [token]);

  useEffect(() => {
    void loadCatalogs();
  }, [loadCatalogs]);

  const selectedSupplier = useMemo(
    () => suppliers.find((supplier) => supplier.id === Number(supplierId)) ?? null,
    [suppliers, supplierId],
  );

  function addItem() {
    const product = products.find((item) => item.id === Number(selectedProductId));
    if (!product) return;

    setItems((current) => {
      const existing = current.find((line) => line.product_id === product.id);

      if (existing) {
        return current.map((line) =>
          line.product_id === product.id ? { ...line, quantity: line.quantity + 1 } : line,
        );
      }

      return [
        ...current,
        {
          key: `${product.id}-${Date.now()}`,
          product_id: product.id,
          product_name: `${product.code} - ${product.name}`,
          quantity: 1,
          unit_cost: product.cost,
          discount: 0,
          tax_type: product.tax_type,
          tax_percentage: product.tax_percentage,
          stock: product.stock,
        },
      ];
    });
    setSelectedProductId('');
  }

  function updateItem(key: string, patch: Partial<PurchaseItemDraft>) {
    setItems((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  }

  function removeItem(key: string) {
    setItems((current) => current.filter((line) => line.key !== key));
  }

  const totals = useMemo(() => {
    return items.reduce(
      (acc, line) => {
        const { base, tax, total } = lineTotals(line);
        return {
          subtotal: acc.subtotal + line.quantity * line.unit_cost,
          discount: acc.discount + line.discount,
          tax: acc.tax + tax,
          total: acc.total + total,
        };
      },
      { subtotal: 0, discount: 0, tax: 0, total: 0 },
    );
  }, [items]);

  const isCreditPurchase = paymentMethodId === '';

  async function submitPurchase(confirm: boolean) {
    setError('');

    if (!supplierId) {
      setError('Selecciona un proveedor.');
      return;
    }

    if (!warehouseId) {
      setError('Selecciona el almacen de ingreso.');
      return;
    }

    if (items.length === 0) {
      setError('Agrega al menos un producto a la compra.');
      return;
    }

    if (!token) {
      setError('No autenticado.');
      return;
    }

    setSubmitting(true);

    try {
      const response = await apiRequest<PurchaseSaveResponse>(
        '/purchases',
        {
          method: 'POST',
          body: JSON.stringify({
            supplier_id: Number(supplierId),
            warehouse_id: Number(warehouseId),
            payment_method_id: paymentMethodId ? Number(paymentMethodId) : null,
            payment_term_id: paymentTermId ? Number(paymentTermId) : null,
            purchase_date: purchaseDate,
            notes: notes.trim() || null,
            confirm,
            items: items.map((line) => ({
              product_id: line.product_id,
              quantity: line.quantity,
              unit_cost: line.unit_cost,
              discount: line.discount,
            })),
          }),
        },
        token,
      );

      navigate(`/purchases/${response.item.id}`, { replace: true });
    } catch (saveError) {
      setError(getErrorMessage(saveError));
    } finally {
      setSubmitting(false);
    }
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    void submitPurchase(true);
  }

  const inputClass =
    'h-11 w-full rounded-lg border border-stroke bg-white px-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

  if (loadingCatalogs) {
    return (
      <div className="rounded-[10px] border border-stroke bg-white p-8 text-center text-sm text-slate-500 shadow-default dark:border-strokedark dark:bg-boxdark">
        Cargando datos para la compra...
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
        <h2 className="mb-6 text-xl font-black text-black dark:text-white">Nueva compra</h2>

        {error && <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-500">{error}</div>}

        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
          <label className="block">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Proveedor</span>
            <select value={supplierId} onChange={(event) => setSupplierId(event.target.value)} className={inputClass}>
              <option value="">Selecciona un proveedor</option>
              {suppliers.map((supplier) => (
                <option key={supplier.id} value={supplier.id}>
                  {supplier.code} - {supplier.name}
                </option>
              ))}
            </select>
            {selectedSupplier && (
              <p className="mt-2 text-xs text-slate-500">
                Saldo actual por pagar: <span className="font-semibold text-amber-600">{formatCurrency(selectedSupplier.payable_balance)}</span>
              </p>
            )}
          </label>

          <label className="block">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Almacen de ingreso</span>
            <select value={warehouseId} onChange={(event) => setWarehouseId(event.target.value)} className={inputClass}>
              <option value="">Selecciona un almacen</option>
              {warehouses.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                  {warehouse.name}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Fecha</span>
            <input type="date" value={purchaseDate} onChange={(event) => setPurchaseDate(event.target.value)} className={inputClass} />
          </label>

          <label className="block">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Forma de pago</span>
            <select value={paymentMethodId} onChange={(event) => setPaymentMethodId(event.target.value)} className={inputClass}>
              <option value="">Credito (cuenta por pagar)</option>
              {paymentMethods.map((method) => (
                <option key={method.id} value={method.id}>
                  {method.name}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Condicion de pago</span>
            <select value={paymentTermId} onChange={(event) => setPaymentTermId(event.target.value)} className={inputClass}>
              <option value="">Sin condicion</option>
              {paymentTerms.map((term) => (
                <option key={term.id} value={term.id}>
                  {term.name}
                </option>
              ))}
            </select>
          </label>

          <label className="block">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Notas</span>
            <input value={notes} onChange={(event) => setNotes(event.target.value)} className={inputClass} placeholder="Numero de factura del proveedor, etc." />
          </label>
        </div>

        {isCreditPurchase && (
          <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
            Sin forma de pago seleccionada, la compra se registrara a credito y generara una cuenta por pagar.
          </p>
        )}
      </div>

      <div className="rounded-[10px] border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
          <label className="block flex-1">
            <span className="mb-2 block text-sm font-semibold text-black dark:text-white">Producto</span>
            <select value={selectedProductId} onChange={(event) => setSelectedProductId(event.target.value)} className={inputClass}>
              <option value="">Selecciona un producto</option>
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.code} - {product.name} (Stock: {product.stock})
                </option>
              ))}
            </select>
          </label>
          <button
            type="button"
            onClick={addItem}
            disabled={!selectedProductId}
            className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-white disabled:opacity-50"
          >
            <FiPlus /> Agregar
          </button>
        </div>

        <div className="overflow-x-auto">
          <table className="min-w-full text-left">
            <thead>
              <tr className="border-b border-stroke text-sm font-semibold text-black dark:border-strokedark dark:text-white">
                <th className="px-3 py-3">Producto</th>
                <th className="px-3 py-3 w-24">Cantidad</th>
                <th className="px-3 py-3 w-32">Costo unit.</th>
                <th className="px-3 py-3 w-28">Descuento</th>
                <th className="px-3 py-3 text-right">Impuesto</th>
                <th className="px-3 py-3 text-right">Total</th>
                <th className="px-3 py-3" />
              </tr>
            </thead>
            <tbody>
              {items.map((line) => {
                const { tax, total } = lineTotals(line);
                return (
                  <tr key={line.key} className="border-b border-stroke text-sm dark:border-strokedark">
                    <td className="px-3 py-3">{line.product_name}</td>
                    <td className="px-3 py-3">
                      <input
                        type="number"
                        min="0.0001"
                        step="0.0001"
                        value={line.quantity}
                        onChange={(event) => updateItem(line.key, { quantity: Number(event.target.value) || 0 })}
                        className="h-10 w-full rounded-lg border border-stroke bg-white px-2 text-sm dark:border-strokedark dark:bg-boxdark"
                      />
                    </td>
                    <td className="px-3 py-3">
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={line.unit_cost}
                        onChange={(event) => updateItem(line.key, { unit_cost: Number(event.target.value) || 0 })}
                        className="h-10 w-full rounded-lg border border-stroke bg-white px-2 text-sm dark:border-strokedark dark:bg-boxdark"
                      />
                    </td>
                    <td className="px-3 py-3">
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={line.discount}
                        onChange={(event) => updateItem(line.key, { discount: Number(event.target.value) || 0 })}
                        className="h-10 w-full rounded-lg border border-stroke bg-white px-2 text-sm dark:border-strokedark dark:bg-boxdark"
                      />
                    </td>
                    <td className="px-3 py-3 text-right">{formatCurrency(tax)}</td>
                    <td className="px-3 py-3 text-right font-semibold">{formatCurrency(total)}</td>
                    <td className="px-3 py-3 text-right">
                      <button
                        type="button"
                        onClick={() => removeItem(line.key)}
                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-500 text-red-500 hover:bg-red-500 hover:text-white"
                      >
                        <FiTrash2 />
                      </button>
                    </td>
                  </tr>
                );
              })}
              {items.length === 0 && (
                <tr>
                  <td colSpan={7} className="px-3 py-8 text-center text-sm text-slate-500">
                    Agrega productos a la compra.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="mt-6 flex justify-end">
          <div className="w-full max-w-sm space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-slate-500">Subtotal</span>
              <span className="font-semibold">{formatCurrency(totals.subtotal)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Descuento</span>
              <span className="font-semibold">-{formatCurrency(totals.discount)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-500">Impuesto</span>
              <span className="font-semibold">{formatCurrency(totals.tax)}</span>
            </div>
            <div className="flex justify-between border-t border-stroke pt-2 text-base dark:border-strokedark">
              <span className="font-bold text-black dark:text-white">Total</span>
              <span className="font-bold text-black dark:text-white">{formatCurrency(totals.total)}</span>
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          type="button"
          disabled={submitting}
          onClick={() => void submitPurchase(false)}
          className="inline-flex h-12 items-center justify-center rounded-lg border border-stroke px-6 text-sm font-bold text-black transition hover:border-primary hover:text-primary disabled:opacity-60"
        >
          Guardar borrador
        </button>
        <button
          type="submit"
          disabled={submitting}
          className="inline-flex h-12 items-center justify-center rounded-lg bg-primary px-6 text-sm font-bold text-white disabled:opacity-60"
        >
          {submitting ? 'Guardando...' : 'Confirmar recepcion'}
        </button>
      </div>
    </form>
  );
}
