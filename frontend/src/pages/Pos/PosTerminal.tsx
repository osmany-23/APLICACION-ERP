import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FiCalendar,
  FiClock,
  FiFileText,
  FiHome,
  FiList,
  FiMaximize,
  FiMinimize,
  FiPackage,
  FiSearch,
  FiShoppingBag,
  FiUserPlus,
  FiX,
} from 'react-icons/fi';
import { BsCalculator } from 'react-icons/bs';
import { TbBarcode } from 'react-icons/tb';
import { useAuth } from '../../context/AuthContext';
import { ApiError, apiRequest } from '../../services/api';
import { Customer, CustomerListResponse } from '../../types/customer';
import { ReceiptRequest, SaleSaveResponse } from '../../types/sale';
import { HeldCart, PosCartLine, PosCatalogOption, PosDocumentType, PosExchangeRate, PosPaymentMethod, PosProduct, PosWarehouse } from '../../types/pos';
import { resolveTierPrice } from '../../utils/pricing';
import CartPanel, { CartTotals } from './components/CartPanel';
import ProductListPanel from './components/ProductListPanel';
import PaymentModal from './components/PaymentModal';
import ReceiptModal from './components/ReceiptModal';
import HistoryModal from './components/HistoryModal';
import CashStatusModal from './components/CashStatusModal';
import CalculatorModal from './components/CalculatorModal';
import QuickCustomerModal from './components/QuickCustomerModal';
import HeldCartsModal from './components/HeldCartsModal';
import CustomerSelect from './components/CustomerSelect';

const HELD_CARTS_KEY = 'erp_pos_held_carts';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

function readHeldCarts(): HeldCart[] {
  try {
    const raw = localStorage.getItem(HELD_CARTS_KEY);
    return raw ? (JSON.parse(raw) as HeldCart[]) : [];
  } catch {
    return [];
  }
}

export default function PosTerminal() {
  const { token, user } = useAuth();
  const navigate = useNavigate();
  const containerRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Reloj en vivo para la barra inferior (fecha/hora del turno) — se
  // actualiza cada 30s, de sobra para un reloj que solo muestra minutos.
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 30000);
    return () => window.clearInterval(timer);
  }, []);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [customers, setCustomers] = useState<Customer[]>([]);
  const [warehouseId, setWarehouseId] = useState('');
  const [products, setProducts] = useState<PosProduct[]>([]);
  const [categories, setCategories] = useState<PosCatalogOption[]>([]);
  const [brands, setBrands] = useState<PosCatalogOption[]>([]);
  const [paymentMethods, setPaymentMethods] = useState<PosPaymentMethod[]>([]);
  const [documentTypes, setDocumentTypes] = useState<PosDocumentType[]>([]);
  const [exchangeRate, setExchangeRate] = useState<PosExchangeRate | null>(null);

  const [customerId, setCustomerId] = useState('');
  const [documentTypeId, setDocumentTypeId] = useState('');
  const [search, setSearch] = useState('');

  const [cart, setCart] = useState<PosCartLine[]>([]);
  const [discountType, setDiscountType] = useState<'fixed' | 'percentage'>('fixed');
  const [discountValue, setDiscountValue] = useState('');
  const [shipping, setShipping] = useState('');

  const [heldCarts, setHeldCarts] = useState<HeldCart[]>(() => readHeldCarts());

  const [showHistory, setShowHistory] = useState(false);
  const [showCashStatus, setShowCashStatus] = useState(false);
  const [showCalculator, setShowCalculator] = useState(false);
  const [showQuickCustomer, setShowQuickCustomer] = useState(false);
  const [showHeldCarts, setShowHeldCarts] = useState(false);
  const [showPayment, setShowPayment] = useState(false);
  const [showProductPanel, setShowProductPanel] = useState(false);
  const [paymentSubmitting, setPaymentSubmitting] = useState(false);
  const [paymentError, setPaymentError] = useState('');
  const [receipt, setReceipt] = useState<ReceiptRequest | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);

  const loadCatalogs = useCallback(async (silent = false) => {
    if (!token) return;
    // "silent" se usa para el refresco de stock/precios despues de cobrar
    // una venta: no debe volver a tapar la pantalla con el loader de
    // pantalla completa (eso ocultaria el recibo que se acaba de mostrar).
    if (!silent) setLoading(true);
    setError('');

    try {
      const [customersRes, warehousesRes, productsRes, categoriesRes, brandsRes, methodsRes, docTypesRes, exchangeRateRes] = await Promise.all([
        apiRequest<CustomerListResponse>('/customers', {}, token),
        apiRequest<{ data: PosWarehouse[] }>('/relations/warehouses', {}, token),
        apiRequest<{ data: PosProduct[] }>('/products', {}, token),
        apiRequest<{ data: PosCatalogOption[] }>('/catalogs/categories', {}, token),
        apiRequest<{ data: PosCatalogOption[] }>('/catalogs/brands', {}, token),
        apiRequest<{ data: (PosPaymentMethod & { is_active: boolean })[] }>('/settings/payment-methods', {}, token),
        apiRequest<{ data: (PosDocumentType & { is_active: boolean })[] }>('/settings/document-types', {}, token),
        apiRequest<{ data: PosExchangeRate | null }>('/sales/exchange-rate', {}, token).catch(() => ({ data: null })),
      ]);

      setCustomers(customersRes.data);
      setProducts(productsRes.data.filter((product) => product.allow_sale && product.status === 1));
      setCategories(categoriesRes.data);
      setBrands(brandsRes.data);
      setPaymentMethods(methodsRes.data.filter((method) => method.is_active));
      const activeDocTypes = docTypesRes.data.filter((type) => type.is_active);
      setDocumentTypes(activeDocTypes);
      setExchangeRate(exchangeRateRes.data);

      if (warehousesRes.data.length > 0) {
        setWarehouseId((current) => current || String(warehousesRes.data[0].id));
      }

      if (activeDocTypes.length > 0) {
        const preferred = activeDocTypes.find((type) => type.code === 'FACT') ?? activeDocTypes[0];
        setDocumentTypeId((current) => current || String(preferred.id));
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      if (!silent) setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    void loadCatalogs();
    // Solo se quiere disparar en el montaje inicial (loadCatalogs es
    // estable por useCallback salvo que cambie el token).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  useEffect(() => {
    localStorage.setItem(HELD_CARTS_KEY, JSON.stringify(heldCarts));
  }, [heldCarts]);

  useEffect(() => {
    function handleFullscreenChange() {
      setIsFullscreen(Boolean(document.fullscreenElement));
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  // Los productos estan ocultos por defecto: al presionar Control se abre/
  // cierra el panel de catalogo en el lateral derecho, para que el vendedor
  // pueda buscar rapido sin salir del TPV. Se ignora si ya hay otra ventana
  // (cobro, historial, etc.) abierta encima, para que no choquen overlays.
  const anyOtherModalOpen = showPayment || showQuickCustomer || showHistory || showCashStatus || showCalculator || showHeldCarts;
  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === 'Control') {
        if (event.repeat || anyOtherModalOpen) return;
        setShowProductPanel((current) => !current);
        return;
      }

      if (event.key === 'Escape' && showProductPanel) {
        setShowProductPanel(false);
      }
    }

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [anyOtherModalOpen, showProductPanel]);

  const selectedCustomer = useMemo(() => customers.find((customer) => customer.id === Number(customerId)) ?? null, [customers, customerId]);
  const selectedDocumentType = useMemo(
    () => documentTypes.find((type) => String(type.id) === documentTypeId) ?? null,
    [documentTypes, documentTypeId],
  );
  // La proforma es una cotizacion sin efecto real: se guarda como venta
  // DRAFT (confirm:false) para que SalesService nunca mueva inventario ni
  // postee asientos contables hasta que se convierta en Factura de Venta.
  const isProforma = selectedDocumentType?.code === 'PROF';

  const cartProductIds = useMemo(() => new Set(cart.map((item) => item.product_id)), [cart]);

  function addProductToCart(product: PosProduct) {
    // Un combo/kit no lleva su propio stock (depende de sus componentes),
    // asi que nunca se bloquea aqui por "stock <= 0" — el servidor valida
    // la disponibilidad real de cada componente al confirmar la venta.
    if (!product.is_kit && product.stock <= 0) return;

    setCart((current) => {
      const existing = current.find((line) => line.product_id === product.id);

      if (existing) {
        if (!product.is_kit && existing.quantity >= product.stock) return current;
        const nextQuantity = existing.quantity + 1;
        return current.map((line) =>
          line.product_id === product.id
            ? { ...line, quantity: nextQuantity, unit_price: resolveTierPrice(line, nextQuantity) }
            : line,
        );
      }

      return [
        ...current,
        {
          key: `${product.id}-${Date.now()}`,
          product_id: product.id,
          code: product.code,
          name: product.name,
          image_url: product.image_url,
          unit_price: resolveTierPrice(product, 1),
          sale_price: product.sale_price,
          price_tiers: product.price_tiers,
          quantity: 1,
          stock: product.stock,
          tax_type: product.tax_type,
          tax_percentage: product.tax_percentage,
          is_kit: product.is_kit,
        },
      ];
    });
  }

  // La barra superior es solo para codigo/codigo de barras (no busca por
  // nombre): un escaner de mano actua como teclado, tipea el codigo
  // completo en milisegundos y normalmente termina con Enter. Se compara
  // contra "code" (codigo interno) y "barcode" (codigo de barras).
  function findByExactCode(term: string): PosProduct | undefined {
    const normalized = term.trim().toLowerCase();
    if (!normalized) return undefined;

    return products.find(
      (product) =>
        product.code.toLowerCase() === normalized ||
        (product.barcode && product.barcode.toLowerCase() === normalized),
    );
  }

  function handleSearchKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key !== 'Enter') return;
    event.preventDefault();

    const exactMatch = findByExactCode(search);
    if (exactMatch) {
      addProductToCart(exactMatch);
      setSearch('');
    }
  }

  // Respaldo para cuando el escaner no manda Enter al final (o para
  // tipeo manual del codigo): apenas lo escrito coincide EXACTO con un
  // codigo/codigo de barras, se agrega solo. El pequeno debounce evita que
  // dispare a mitad de tipeo si un codigo resulta ser prefijo de otro.
  useEffect(() => {
    if (!search.trim()) return undefined;

    const timeout = window.setTimeout(() => {
      const exactMatch = findByExactCode(search);
      if (exactMatch) {
        addProductToCart(exactMatch);
        setSearch('');
      }
    }, 200);

    return () => window.clearTimeout(timeout);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, products]);

  function incrementItem(key: string) {
    setCart((current) =>
      current.map((line) => {
        if (line.key !== key || (!line.is_kit && line.quantity >= line.stock)) return line;
        const nextQuantity = line.quantity + 1;
        return { ...line, quantity: nextQuantity, unit_price: resolveTierPrice(line, nextQuantity) };
      }),
    );
  }

  function decrementItem(key: string) {
    setCart((current) =>
      current
        .map((line) => {
          if (line.key !== key) return line;
          const nextQuantity = line.quantity - 1;
          return { ...line, quantity: nextQuantity, unit_price: resolveTierPrice(line, nextQuantity) };
        })
        .filter((line) => line.quantity > 0),
    );
  }

  function removeItem(key: string) {
    setCart((current) => current.filter((line) => line.key !== key));
  }

  function resetCurrentSale() {
    setCart([]);
    setDiscountType('fixed');
    setDiscountValue('');
    setShipping('');
    setCustomerId('');
  }

  function handleReiniciar() {
    if (cart.length === 0) return;
    if (!window.confirm('¿Reiniciar la venta actual? Se perdera el carrito.')) return;
    resetCurrentSale();
  }

  function handleMantener() {
    if (cart.length === 0) return;

    const heldCart: HeldCart = {
      id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      savedAt: new Date().toISOString(),
      label: selectedCustomer?.full_name ?? `Venta en espera ${heldCarts.length + 1}`,
      customerId,
      documentTypeId,
      items: cart,
      discountType,
      discountValue,
      shipping,
      notes: '',
    };

    setHeldCarts((current) => [heldCart, ...current]);
    resetCurrentSale();
    setNotice('Venta guardada en espera.');
  }

  function handleResumeHeld(heldCart: HeldCart) {
    if (cart.length > 0) {
      window.alert('Termina, guarda o reinicia la venta actual antes de reanudar otra.');
      return;
    }

    setCustomerId(heldCart.customerId);
    setDocumentTypeId(heldCart.documentTypeId);
    setCart(heldCart.items);
    setDiscountType(heldCart.discountType);
    setDiscountValue(heldCart.discountValue);
    setShipping(heldCart.shipping);
    setHeldCarts((current) => current.filter((item) => item.id !== heldCart.id));
    setShowHeldCarts(false);
  }

  function handleDiscardHeld(heldCart: HeldCart) {
    if (!window.confirm('¿Descartar esta venta en espera? Esta accion no se puede deshacer.')) return;
    setHeldCarts((current) => current.filter((item) => item.id !== heldCart.id));
  }

  // Reparte el descuento/impuesto de forma proporcional al peso de cada
  // linea dentro del carrito, para poder seguir usando POST /sales tal
  // cual (que solo entiende descuento por linea, no un descuento global de
  // la venta) sin tener que tocar el backend de facturacion.
  const totals: CartTotals = useMemo(() => {
    const quantityTotal = cart.reduce((sum, item) => sum + item.quantity, 0);
    const subtotal = cart.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);

    const rawDiscountValue = Number(discountValue || 0);
    const discountAmount = Math.min(
      subtotal,
      Math.max(0, discountType === 'percentage' ? (subtotal * rawDiscountValue) / 100 : rawDiscountValue),
    );

    const taxAmount = cart.reduce((sum, item) => {
      if (item.tax_type !== 'TAXABLE') return sum;
      const lineSubtotal = item.unit_price * item.quantity;
      const lineShare = subtotal > 0 ? lineSubtotal / subtotal : 0;
      const lineDiscount = discountAmount * lineShare;
      const taxableBase = Math.max(0, lineSubtotal - lineDiscount);
      return sum + (taxableBase * item.tax_percentage) / 100;
    }, 0);

    const shippingAmount = Math.max(0, Number(shipping || 0));
    const total = Math.max(0, subtotal - discountAmount + taxAmount + shippingAmount);
    const effectiveTaxPercent = subtotal > 0 ? (taxAmount / subtotal) * 100 : 0;

    return { quantityTotal, subtotal, discountAmount, taxAmount, shippingAmount, total, effectiveTaxPercent };
  }, [cart, discountType, discountValue, shipping]);

  function buildItemsPayload() {
    const subtotal = totals.subtotal;

    return cart.map((item) => {
      const lineSubtotal = item.unit_price * item.quantity;
      const lineShare = subtotal > 0 ? lineSubtotal / subtotal : 0;
      const lineDiscount = Math.round(totals.discountAmount * lineShare * 100) / 100;

      return {
        product_id: item.product_id,
        quantity: item.quantity,
        unit_price: item.unit_price,
        discount: lineDiscount,
      };
    });
  }

  async function confirmPayment(
    paymentMethodId: number | null,
    tenderedInBase: number | null,
    isPendingPayment = false,
    paymentReference?: string,
    tenderedBase?: number,
    tenderedForeign?: number,
  ) {
    if (!token) return;

    if (!customerId) {
      setPaymentError('Selecciona un cliente antes de cobrar.');
      return;
    }

    if (!warehouseId) {
      setPaymentError('No hay un almacen configurado para esta venta.');
      return;
    }

    setPaymentSubmitting(true);
    setPaymentError('');

    try {
      const change = tenderedInBase !== null ? Math.max(0, tenderedInBase - totals.total) : undefined;

      const response = await apiRequest<SaleSaveResponse>(
        '/sales',
        {
          method: 'POST',
          body: JSON.stringify({
            customer_id: Number(customerId),
            warehouse_id: Number(warehouseId),
            document_type_id: documentTypeId ? Number(documentTypeId) : undefined,
            payment_method_id: paymentMethodId,
            is_pending_payment: isPendingPayment || undefined,
            shipping: totals.shippingAmount || undefined,
            confirm: true,
            payment_reference: paymentReference || undefined,
            amount_tendered_base: tenderedBase ?? undefined,
            amount_tendered_foreign: tenderedForeign ?? undefined,
            change_amount: change,
            exchange_rate: exchangeRate?.rate || undefined,
            items: buildItemsPayload(),
          }),
        },
        token,
      );

      setReceipt({ saleId: response.item.id, variant: 'sale', isPendingPayment });
      setShowPayment(false);
      resetCurrentSale();
      void loadCatalogs(true);
    } catch (err) {
      setPaymentError(getErrorMessage(err));
    } finally {
      setPaymentSubmitting(false);
    }
  }

  // Genera la proforma directamente (sin pasar por el modal de cobro: una
  // cotizacion no se paga). Queda como venta DRAFT, lista para convertirse
  // en Factura de Venta real mas adelante desde el modulo de Facturacion.
  async function handleGenerateProforma() {
    if (!token) return;

    if (!customerId) {
      setError('Selecciona un cliente antes de generar la proforma.');
      return;
    }

    if (!warehouseId) {
      setError('No hay un almacen configurado para esta venta.');
      return;
    }

    try {
      const response = await apiRequest<SaleSaveResponse>(
        '/sales',
        {
          method: 'POST',
          body: JSON.stringify({
            customer_id: Number(customerId),
            warehouse_id: Number(warehouseId),
            document_type_id: documentTypeId ? Number(documentTypeId) : undefined,
            payment_method_id: null,
            shipping: totals.shippingAmount || undefined,
            confirm: false,
            items: buildItemsPayload(),
          }),
        },
        token,
      );

      setReceipt({ saleId: response.item.id, variant: 'proforma', isPendingPayment: false });
      resetCurrentSale();
      void loadCatalogs(true);
    } catch (err) {
      setError(getErrorMessage(err));
    }
  }

  function toggleFullscreen() {
    if (!document.fullscreenElement) {
      containerRef.current?.requestFullscreen().catch(() => undefined);
    } else {
      document.exitFullscreen().catch(() => undefined);
    }
  }

  const inputClass =
    'h-12 rounded-xl border border-stroke bg-white px-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white';

  if (loading) {
    return (
      <div className="flex h-screen items-center justify-center bg-slate-100 dark:bg-boxdark-2">
        <p className="text-sm font-semibold text-slate-500">Cargando punto de venta...</p>
      </div>
    );
  }

  return (
    <div ref={containerRef} className="flex h-screen flex-col overflow-hidden bg-slate-100 dark:bg-boxdark-2">
      <div className="flex flex-col gap-3 p-3 pb-0">
        {/* Barra superior del TPV */}
        <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-stroke bg-white px-4 py-3 shadow-md dark:border-strokedark dark:bg-boxdark">
          <div className="flex items-center gap-2">
            <CustomerSelect customers={customers} value={customerId} onChange={setCustomerId} />
            <button
              type="button"
              onClick={() => setShowQuickCustomer(true)}
              title="Nuevo cliente rapido"
              className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary text-white shadow-sm transition hover:bg-opacity-90 active:scale-95"
            >
              <FiUserPlus className="h-5 w-5" />
            </button>
          </div>

          <div className="relative">
            <FiFileText className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-primary" />
            <select
              value={documentTypeId}
              onChange={(event) => setDocumentTypeId(event.target.value)}
              className={`${inputClass} w-[210px] pl-9 font-bold uppercase ${isProforma ? 'border-violet-300 text-violet-600 dark:border-violet-700' : ''}`}
            >
              {documentTypes.map((type) => (
                <option key={type.id} value={type.id}>{type.name}</option>
              ))}
            </select>
          </div>

          <div className="relative min-w-[220px] flex-1">
            <FiSearch className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              ref={searchInputRef}
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              onKeyDown={handleSearchKeyDown}
              className={`${inputClass} w-full pl-9 pr-20`}
              placeholder="Escanear codigo de barras o escribir codigo exacto"
            />
            <span
              title="Presiona Ctrl para abrir el catalogo completo de productos"
              className="pointer-events-none absolute right-11 top-1/2 hidden -translate-y-1/2 rounded-md border border-stroke bg-slate-50 px-1.5 py-0.5 text-[10px] font-black uppercase tracking-wide text-slate-400 dark:border-strokedark dark:bg-meta-4 md:inline-block"
            >
              Ctrl
            </span>
            <button
              type="button"
              onClick={() => searchInputRef.current?.focus()}
              title="Este campo acepta lectura de codigo de barras"
              className="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary dark:hover:bg-meta-4"
            >
              <TbBarcode className="h-5 w-5" />
            </button>
          </div>

          <div className="ml-auto flex items-center gap-2 rounded-2xl bg-slate-100 p-2 dark:bg-meta-4">
            <button
              type="button"
              onClick={() => setShowProductPanel((current) => !current)}
              title="Catalogo de productos (Ctrl)"
              className={`flex h-12 w-12 items-center justify-center rounded-xl text-white shadow-sm transition hover:brightness-110 active:scale-95 ${
                showProductPanel ? 'bg-primary ring-2 ring-primary/40 ring-offset-2 ring-offset-slate-100 dark:ring-offset-meta-4' : 'bg-indigo-500'
              }`}
            >
              <FiPackage className="h-5 w-5" />
            </button>
            <button
              type="button"
              onClick={() => setShowHistory(true)}
              title="Historial de facturas"
              className="relative flex h-12 w-12 items-center justify-center rounded-xl bg-rose-500 text-white shadow-sm transition hover:brightness-110 active:scale-95"
            >
              <FiList className="h-5 w-5" />
              <span className="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-[10px] font-black text-rose-600 shadow">
                {heldCarts.length}
              </span>
            </button>
            <button
              type="button"
              onClick={() => setShowCashStatus(true)}
              title="Estado de caja"
              className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500 text-white shadow-sm transition hover:brightness-110 active:scale-95"
            >
              <FiShoppingBag className="h-5 w-5" />
            </button>
            <button
              type="button"
              onClick={toggleFullscreen}
              title="Maximizar / minimizar ventana POS"
              className="flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500 text-white shadow-sm transition hover:brightness-110 active:scale-95"
            >
              {isFullscreen ? <FiMinimize className="h-5 w-5" /> : <FiMaximize className="h-5 w-5" />}
            </button>
            <button
              type="button"
              onClick={() => setShowCalculator(true)}
              title="Calculadora"
              className="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-500 text-white shadow-sm transition hover:brightness-110 active:scale-95"
            >
              <BsCalculator className="h-5 w-5" />
            </button>
            <button
              type="button"
              onClick={() => navigate('/')}
              title="Volver al sistema"
              className="flex h-12 w-12 items-center justify-center rounded-xl bg-slate-500 text-white shadow-sm transition hover:bg-red-500 active:scale-95"
            >
              <FiX className="h-5 w-5" />
            </button>
          </div>
        </div>

        {notice && (
          <div className="flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-4 py-2.5 text-xs font-semibold text-green-600">
            {notice}
            <button type="button" onClick={() => setNotice('')}><FiX /></button>
          </div>
        )}
        {error && (
          <div className="flex items-center justify-between rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-semibold text-red-500">
            {error}
            <button type="button" onClick={() => setError('')}><FiX /></button>
          </div>
        )}
      </div>

      {/* Cuerpo: solo el carrito. El catalogo de productos esta oculto por
          defecto (acceso restringido) y se despliega como panel lateral al
          presionar Ctrl o el boton de catalogo en la barra superior. */}
      <div className="flex min-h-0 flex-1 gap-3 p-3">
        <div className="mx-auto w-full max-w-[1800px] overflow-hidden rounded-2xl border border-stroke bg-white shadow-md dark:border-strokedark dark:bg-boxdark">
          <div className="h-full overflow-hidden p-7">
            <CartPanel
              items={cart}
              onIncrement={incrementItem}
              onDecrement={decrementItem}
              onRemove={removeItem}
              discountType={discountType}
              discountValue={discountValue}
              onDiscountTypeChange={setDiscountType}
              onDiscountValueChange={setDiscountValue}
              shipping={shipping}
              onShippingChange={setShipping}
              totals={totals}
              onMantener={handleMantener}
              onReiniciar={handleReiniciar}
              onPagar={() => (isProforma ? void handleGenerateProforma() : setShowPayment(true))}
              isProforma={isProforma}
              heldCount={heldCarts.length}
              onOpenHeld={() => setShowHeldCarts(true)}
            />
          </div>
        </div>
      </div>

      <ProductListPanel
        open={showProductPanel}
        products={products}
        categories={categories}
        brands={brands}
        cartProductIds={cartProductIds}
        onAddProduct={addProductToCart}
        onClose={() => setShowProductPanel(false)}
      />

      {/* Barra de estado del turno: cuando y desde donde. El vendedor ya no
          va aca (era el cajero logueado en la terminal, no necesariamente
          quien vendio cada factura) — esa info vive ahora en el detalle de
          cada factura (SaleDetail.tsx), que es el lugar correcto y
          permanente para consultarla. */}
      <div className="flex flex-wrap items-center gap-x-8 gap-y-1 border-t border-stroke bg-white px-5 py-2.5 text-xs text-slate-500 dark:border-strokedark dark:bg-boxdark">
        <span className="flex items-center gap-2">
          <span className="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-slate-400 dark:bg-meta-4"><FiCalendar className="h-3.5 w-3.5" /></span>
          Fecha <span className="font-bold text-black dark:text-white">{now.toLocaleDateString('es-NI')}</span>
        </span>
        <span className="flex items-center gap-2">
          <span className="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-slate-400 dark:bg-meta-4"><FiClock className="h-3.5 w-3.5" /></span>
          Hora <span className="font-bold text-black dark:text-white">{now.toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' })}</span>
        </span>
        <span className="ml-auto flex items-center gap-2">
          <span className="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-slate-400 dark:bg-meta-4"><FiHome className="h-3.5 w-3.5" /></span>
          Sucursal <span className="font-bold text-black dark:text-white">{user?.branch?.name ?? 'Sin asignar'}</span>
        </span>
      </div>

      {showQuickCustomer && token && (
        <QuickCustomerModal
          token={token}
          onClose={() => setShowQuickCustomer(false)}
          onCreated={(customer) => {
            setCustomers((current) => [...current, customer]);
            setCustomerId(String(customer.id));
            setShowQuickCustomer(false);
          }}
        />
      )}

      {showHistory && token && (
        <HistoryModal token={token} exchangeRate={exchangeRate} onClose={() => setShowHistory(false)} />
      )}
      {showCashStatus && token && <CashStatusModal token={token} onClose={() => setShowCashStatus(false)} />}
      {showCalculator && <CalculatorModal onClose={() => setShowCalculator(false)} />}
      {showHeldCarts && (
        <HeldCartsModal
          carts={heldCarts}
          onResume={handleResumeHeld}
          onDiscard={handleDiscardHeld}
          onClose={() => setShowHeldCarts(false)}
        />
      )}

      {showPayment && (
        <PaymentModal
          total={totals.total}
          paymentMethods={paymentMethods}
          exchangeRate={exchangeRate}
          submitting={paymentSubmitting}
          error={paymentError}
          customer={selectedCustomer}
          customers={customers}
          customerId={customerId}
          token={token ?? undefined}
          onSelectCustomer={(id) => {
            setCustomerId(id);
            setPaymentError('');
          }}
          onCustomerCreated={(customer) => setCustomers((current) => [...current, customer])}
          onClose={() => setShowPayment(false)}
          onConfirm={confirmPayment}
        />
      )}

      {receipt && (
        <ReceiptModal
          saleId={receipt.saleId}
          variant={receipt.variant}
          isPendingPayment={receipt.isPendingPayment}
          token={token ?? undefined}
          onClose={() => setReceipt(null)}
        />
      )}
    </div>
  );
}
