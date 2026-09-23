import { useEffect, useMemo, useRef, useState } from 'react';
import { IconType } from 'react-icons';
import { FiAward, FiBox, FiCheck, FiChevronDown, FiFilter, FiHash, FiLayers, FiMapPin, FiPackage, FiSearch, FiTag, FiX, FiZoomIn } from 'react-icons/fi';
import { PosCatalogOption, PosProduct } from '../../../types/pos';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function formatQuantity(value: number) {
  return Number.isInteger(value) ? String(value) : value.toFixed(2);
}

const CHIP_TONES = {
  slate: 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
  blue: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
  violet: 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
  teal: 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300',
} as const;

function InfoChip({ icon: Icon, label, tone }: { icon: IconType; label: string; tone: keyof typeof CHIP_TONES }) {
  return (
    <span className={`inline-flex max-w-full items-center gap-1 rounded-md px-1.5 py-0.5 text-[10.5px] font-bold ${CHIP_TONES[tone]}`}>
      <Icon className="h-2.5 w-2.5 shrink-0" /> <span className="truncate">{label}</span>
    </span>
  );
}

function ProductRow({
  product,
  inCart,
  expanded,
  onToggleExpand,
  onAdd,
  onPreviewImage,
}: {
  product: PosProduct;
  inCart: boolean;
  expanded: boolean;
  onToggleExpand: () => void;
  onAdd: (product: PosProduct) => void;
  onPreviewImage: (product: PosProduct) => void;
}) {
  // Un combo/kit no tiene existencia propia: su disponibilidad real
  // depende de cada componente y se valida en el servidor al vender, asi
  // que nunca se marca "sin stock" aqui por su propio campo stock (que
  // siempre es 0, no se le lleva kardex directo).
  const outOfStock = !product.is_kit && product.stock <= 0;

  function handleActivate() {
    if (outOfStock) return;
    onAdd(product);
  }

  return (
    <div
      className={`border-b border-stroke transition last:border-b-0 dark:border-strokedark ${
        outOfStock ? 'opacity-50' : 'cursor-pointer hover:bg-primary/5 dark:hover:bg-primary/10'
      } ${inCart ? 'bg-primary/5 dark:bg-primary/10' : ''}`}
    >
      <div
        role="button"
        tabIndex={outOfStock ? -1 : 0}
        aria-disabled={outOfStock}
        onClick={handleActivate}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            handleActivate();
          }
        }}
        className="flex items-center gap-3.5 px-4 py-3 outline-none"
      >
        <div className="relative h-14 w-14 shrink-0">
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation();
              if (product.image_url) onPreviewImage(product);
            }}
            title={product.image_url ? 'Ver imagen ampliada' : 'Sin imagen disponible'}
            className={`group/img relative h-full w-full overflow-hidden rounded-xl border border-stroke bg-slate-50 dark:border-strokedark dark:bg-meta-4 ${
              product.image_url ? 'cursor-zoom-in' : 'cursor-default'
            }`}
          >
            {product.image_url ? (
              <>
                <img src={product.image_url} alt={product.name} className="h-full w-full object-contain p-1" />
                <span className="absolute inset-0 flex items-center justify-center bg-black/0 opacity-0 transition group-hover/img:bg-black/40 group-hover/img:opacity-100">
                  <FiZoomIn className="h-4 w-4 text-white" />
                </span>
              </>
            ) : (
              <div className="flex h-full w-full items-center justify-center text-slate-300">
                <FiPackage className="h-5 w-5" />
              </div>
            )}
          </button>
          {inCart && (
            <span className="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-white shadow dark:border-boxdark">
              <FiCheck className="h-3 w-3" />
            </span>
          )}
        </div>

        <div className="min-w-0 flex-1">
          <p className="truncate text-[15px] font-black leading-tight text-black dark:text-white">{product.name}</p>
          <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
            <InfoChip icon={FiHash} label={product.code || 's/c'} tone="slate" />
            <InfoChip icon={FiTag} label={product.category} tone="blue" />
            <InfoChip icon={FiAward} label={product.brand} tone="violet" />
            {product.is_kit ? (
              <span className="inline-flex max-w-full items-center gap-1 rounded-md bg-purple-50 px-1.5 py-0.5 text-[10.5px] font-bold text-purple-600 dark:bg-purple-500/15 dark:text-purple-300">
                <FiLayers className="h-2.5 w-2.5 shrink-0" /> <span className="truncate">Combo/Kit</span>
              </span>
            ) : (
              product.unit && (
                <span
                  className={`inline-flex max-w-full items-center gap-1 rounded-md px-1.5 py-0.5 text-[10.5px] font-bold ${
                    outOfStock
                      ? 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-300'
                      : product.stock <= 5
                        ? 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300'
                        : 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300'
                  }`}
                >
                  <FiLayers className="h-2.5 w-2.5 shrink-0" />{' '}
                  <span className="truncate">
                    {formatQuantity(product.stock)} {product.unit}
                  </span>
                </span>
              )
            )}
          </div>
        </div>

        <div className="flex shrink-0 flex-col items-end">
          <span className="text-lg font-black text-primary">{formatCurrency(product.sale_price)}</span>
        </div>

        <button
          type="button"
          onClick={(event) => {
            event.stopPropagation();
            onToggleExpand();
          }}
          title="Ver mas detalles"
          className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-primary dark:hover:bg-white/10 ${
            expanded ? 'rotate-180 bg-slate-100 text-primary dark:bg-white/10' : ''
          }`}
        >
          <FiChevronDown className="h-4 w-4" />
        </button>
      </div>

      <div
        className={`grid overflow-hidden transition-all duration-200 ease-out ${
          expanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'
        }`}
      >
        <div className="min-h-0">
          <div className="grid grid-cols-2 gap-x-4 gap-y-1.5 border-t border-dashed border-stroke bg-slate-50 px-4 py-2.5 text-xs text-slate-500 dark:border-strokedark dark:bg-white/[0.03] dark:text-slate-400 sm:grid-cols-2">
            <span className="flex items-center gap-1.5"><FiBox className="h-3 w-3 text-slate-400" /> Bodega: <b className="font-semibold text-black dark:text-white">{product.warehouse ?? 'Sin asignar'}</b></span>
            <span className="flex items-center gap-1.5"><FiMapPin className="h-3 w-3 text-slate-400" /> Ubicacion: <b className="font-semibold text-black dark:text-white">{product.physical_location || 'No definida'}</b></span>
            {product.description && <span className="col-span-2">{product.description}</span>}
          </div>
        </div>
      </div>
    </div>
  );
}

export default function ProductListPanel({
  open,
  products,
  categories,
  brands,
  cartProductIds,
  onAddProduct,
  onClose,
}: {
  open: boolean;
  products: PosProduct[];
  categories: PosCatalogOption[];
  brands: PosCatalogOption[];
  cartProductIds: Set<number>;
  onAddProduct: (product: PosProduct) => void;
  onClose: () => void;
}) {
  const [search, setSearch] = useState('');
  const [categoryId, setCategoryId] = useState('');
  const [brandId, setBrandId] = useState('');
  const [onlyInStock, setOnlyInStock] = useState(false);
  const [sortBy, setSortBy] = useState<'name' | 'price' | 'stock'>('name');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [previewProduct, setPreviewProduct] = useState<PosProduct | null>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (open) {
      // Se enfoca al abrir para que el vendedor pueda empezar a teclear de
      // inmediato: es una ventana pensada para busqueda rapida por teclado.
      const timer = window.setTimeout(() => searchRef.current?.focus(), 250);
      return () => window.clearTimeout(timer);
    }
    setSearch('');
    setExpandedId(null);
    setPreviewProduct(null);
    return undefined;
  }, [open]);

  const filtered = useMemo(() => {
    const normalized = search.trim().toLowerCase();

    const list = products.filter((product) => {
      if (categoryId && String(product.category_id) !== categoryId) return false;
      if (brandId && String(product.brand_id) !== brandId) return false;
      if (onlyInStock && !product.is_kit && product.stock <= 0) return false;
      if (normalized) {
        const haystack = `${product.name} ${product.code} ${product.category} ${product.brand}`.toLowerCase();
        if (!haystack.includes(normalized)) return false;
      }
      return true;
    });

    return list.sort((a, b) => {
      if (sortBy === 'price') return b.sale_price - a.sale_price;
      if (sortBy === 'stock') return b.stock - a.stock;
      return a.name.localeCompare(b.name);
    });
  }, [products, search, categoryId, brandId, onlyInStock, sortBy]);

  const hasFilters = Boolean(search || categoryId || brandId || onlyInStock);

  return (
    <>
      {/* Fondo semitransparente: cierra el panel al hacer click fuera */}
      <div
        onClick={onClose}
        aria-hidden={!open}
        className={`fixed inset-0 z-[89998] bg-black/40 transition-opacity duration-300 ${
          open ? 'opacity-100' : 'pointer-events-none opacity-0'
        }`}
      />

      {/* Panel deslizante: cubre la mitad derecha de la pantalla */}
      <div
        role="dialog"
        aria-modal="true"
        aria-hidden={!open}
        className={`fixed inset-y-0 right-0 z-[89999] flex w-full flex-col border-l border-stroke bg-white shadow-2xl transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] will-change-transform dark:border-strokedark dark:bg-boxdark sm:w-1/2 ${
          open ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between gap-3 bg-gradient-to-r from-primary to-[#6577F3] px-5 py-4 text-white">
          <div>
            <p className="flex items-center gap-2 text-sm font-black uppercase tracking-wide"><FiPackage className="h-4 w-4" /> Catalogo de productos</p>
            <p className="text-[11px] font-medium text-white/80">Presiona Ctrl o Esc para cerrar &middot; click en un producto para agregarlo al carrito</p>
          </div>
          <button type="button" onClick={onClose} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="space-y-2.5 border-b border-stroke px-5 py-3.5 dark:border-strokedark">
          <div className="relative">
            <FiSearch className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              ref={searchRef}
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              className="h-11 w-full rounded-xl border border-stroke bg-white pl-9 pr-3 text-sm text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
              placeholder="Buscar por nombre, codigo, categoria o marca"
            />
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <span className="flex items-center gap-1 text-[11px] font-bold uppercase text-slate-400"><FiFilter className="h-3 w-3" /> Filtros:</span>
            <select
              value={categoryId}
              onChange={(event) => setCategoryId(event.target.value)}
              className="h-8 rounded-lg border border-stroke bg-white px-2 text-xs text-black outline-none focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
            >
              <option value="">Todas las categorias</option>
              {categories.map((category) => (
                <option key={category.id} value={category.id}>{category.name}</option>
              ))}
            </select>
            <select
              value={brandId}
              onChange={(event) => setBrandId(event.target.value)}
              className="h-8 rounded-lg border border-stroke bg-white px-2 text-xs text-black outline-none focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
            >
              <option value="">Todas las marcas</option>
              {brands.map((brand) => (
                <option key={brand.id} value={brand.id}>{brand.name}</option>
              ))}
            </select>
            <select
              value={sortBy}
              onChange={(event) => setSortBy(event.target.value as 'name' | 'price' | 'stock')}
              title="Ordenar por"
              className="h-8 rounded-lg border border-stroke bg-white px-2 text-xs text-black outline-none focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
            >
              <option value="name">Ordenar: Nombre A-Z</option>
              <option value="price">Ordenar: Precio mayor</option>
              <option value="stock">Ordenar: Stock mayor</option>
            </select>
            <label className="flex h-8 items-center gap-1.5 rounded-lg border border-stroke px-2 text-xs font-semibold text-slate-500 dark:border-strokedark dark:text-slate-300">
              <input type="checkbox" checked={onlyInStock} onChange={(event) => setOnlyInStock(event.target.checked)} className="h-3.5 w-3.5 accent-primary" />
              Solo con stock
            </label>
            {hasFilters && (
              <button
                type="button"
                onClick={() => { setSearch(''); setCategoryId(''); setBrandId(''); setOnlyInStock(false); }}
                className="ml-auto text-[11px] font-bold text-primary hover:underline"
              >
                Limpiar filtros
              </button>
            )}
          </div>
        </div>

        <div className="flex-1 overflow-y-auto">
          {filtered.length === 0 ? (
            <div className="flex h-full items-center justify-center px-5 text-center text-sm text-slate-400">No hay productos que coincidan con la busqueda.</div>
          ) : (
            filtered.map((product) => (
              <ProductRow
                key={product.id}
                product={product}
                inCart={cartProductIds.has(product.id)}
                expanded={expandedId === product.id}
                onToggleExpand={() => setExpandedId((current) => (current === product.id ? null : product.id))}
                onAdd={onAddProduct}
                onPreviewImage={setPreviewProduct}
              />
            ))
          )}
        </div>

        <div className="border-t border-stroke px-5 py-2.5 text-[11px] font-semibold text-slate-400 dark:border-strokedark">
          {filtered.length} producto{filtered.length === 1 ? '' : 's'} encontrado{filtered.length === 1 ? '' : 's'}
        </div>
      </div>

      {/* Visor de imagen ampliada */}
      {previewProduct && previewProduct.image_url && (
        <div
          className="fixed inset-0 z-[95000] flex items-center justify-center bg-black/75 p-6"
          onClick={() => setPreviewProduct(null)}
        >
          <div
            className="relative max-h-[85vh] w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-boxdark"
            onClick={(event) => event.stopPropagation()}
          >
            <button
              type="button"
              onClick={() => setPreviewProduct(null)}
              className="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/50 text-white transition hover:bg-black/70"
            >
              <FiX className="h-5 w-5" />
            </button>
            <div className="flex max-h-[65vh] items-center justify-center bg-slate-50 dark:bg-meta-4">
              <img src={previewProduct.image_url} alt={previewProduct.name} className="max-h-[65vh] w-full object-contain p-6" />
            </div>
            <div className="border-t border-stroke px-5 py-4 dark:border-strokedark">
              <p className="text-base font-black text-black dark:text-white">{previewProduct.name}</p>
              <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                <InfoChip icon={FiHash} label={previewProduct.code || 's/c'} tone="slate" />
                <InfoChip icon={FiTag} label={previewProduct.category} tone="blue" />
                <InfoChip icon={FiAward} label={previewProduct.brand} tone="violet" />
              </div>
              <p className="mt-2.5 text-xl font-black text-primary">{formatCurrency(previewProduct.sale_price)}</p>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
