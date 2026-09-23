import { FiPackage } from 'react-icons/fi';
import { PosCatalogOption, PosProduct } from '../../../types/pos';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function formatQuantity(value: number) {
  return Number.isInteger(value) ? String(value) : value.toFixed(2);
}

function ProductCard({ product, inCart, onAdd }: { product: PosProduct; inCart: boolean; onAdd: (product: PosProduct) => void }) {
  // Un combo/kit no tiene existencia propia (su stock disponible depende
  // de sus componentes): nunca se bloquea aqui por "stock <= 0" — la
  // disponibilidad real de cada componente se valida en el servidor al
  // confirmar la venta.
  const outOfStock = !product.is_kit && product.stock <= 0;

  return (
    <button
      type="button"
      onClick={() => onAdd(product)}
      disabled={outOfStock}
      className={`group relative flex flex-col overflow-hidden rounded-xl border bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 dark:bg-boxdark ${
        inCart ? 'border-primary ring-2 ring-primary/40' : 'border-stroke dark:border-strokedark'
      }`}
    >
      <div className="relative aspect-[4/3] w-full bg-slate-50 dark:bg-meta-4">
        <span className="absolute left-1.5 top-1.5 z-10 rounded bg-primary px-1.5 py-0.5 text-[10px] font-black text-white shadow">
          {formatCurrency(product.sale_price)}
        </span>
        <span
          className={`absolute right-1.5 top-1.5 z-10 rounded px-1.5 py-0.5 text-[10px] font-black text-white shadow ${
            product.is_kit ? 'bg-purple-500' : outOfStock ? 'bg-red-500' : product.stock <= 5 ? 'bg-amber-500' : 'bg-emerald-500'
          }`}
        >
          {product.is_kit ? 'COMBO' : `${formatQuantity(product.stock)} UND`}
        </span>

        {product.image_url ? (
          <img src={product.image_url} alt={product.name} className="h-full w-full object-contain p-2.5 transition-transform group-hover:scale-105" />
        ) : (
          <div className="flex h-full w-full items-center justify-center text-slate-300">
            <FiPackage className="h-8 w-8" />
          </div>
        )}
      </div>

      <div className="px-2.5 py-2">
        <p className="line-clamp-2 min-h-[2em] text-xs font-bold leading-tight text-black dark:text-white">{product.name}</p>
        <p className="truncate text-[10px] leading-tight text-slate-400">{product.code}</p>
      </div>
    </button>
  );
}

export default function ProductGrid({
  products,
  categories,
  brands,
  categoryId,
  brandId,
  cartProductIds,
  onCategoryChange,
  onBrandChange,
  onAddProduct,
}: {
  products: PosProduct[];
  categories: PosCatalogOption[];
  brands: PosCatalogOption[];
  categoryId: string;
  brandId: string;
  cartProductIds: Set<number>;
  onCategoryChange: (id: string) => void;
  onBrandChange: (id: string) => void;
  onAddProduct: (product: PosProduct) => void;
}) {
  return (
    <div className="flex h-full flex-col">
      <div className="space-y-2.5 bg-slate-50/70 px-5 py-3.5 dark:bg-white/[0.03]">
        <div className="flex gap-2.5 overflow-x-auto pb-0.5">
          <button
            type="button"
            onClick={() => onCategoryChange('')}
            className={`shrink-0 rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wide shadow-sm transition ${
              categoryId === '' ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-boxdark dark:text-slate-300 dark:hover:bg-meta-4'
            }`}
          >
            Todas las categorias
          </button>
          {categories.map((category) => (
            <button
              key={category.id}
              type="button"
              onClick={() => onCategoryChange(String(category.id))}
              className={`shrink-0 rounded-lg px-4 py-2 text-xs font-bold uppercase tracking-wide shadow-sm transition ${
                categoryId === String(category.id) ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:bg-slate-100 dark:bg-boxdark dark:text-slate-300 dark:hover:bg-meta-4'
              }`}
            >
              {category.name}
            </button>
          ))}
        </div>

        <div className="flex gap-2.5 overflow-x-auto pb-0.5">
          <button
            type="button"
            onClick={() => onBrandChange('')}
            className={`shrink-0 rounded-lg px-4 py-1.5 text-xs font-bold shadow-sm transition ${
              brandId === '' ? 'bg-primary text-white' : 'bg-white text-slate-500 hover:bg-slate-100 dark:bg-boxdark dark:text-slate-300 dark:hover:bg-meta-4'
            }`}
          >
            Todas las marcas
          </button>
          {brands.map((brand) => (
            <button
              key={brand.id}
              type="button"
              onClick={() => onBrandChange(String(brand.id))}
              className={`shrink-0 rounded-lg px-4 py-1.5 text-xs font-bold shadow-sm transition ${
                brandId === String(brand.id) ? 'bg-primary text-white' : 'bg-white text-slate-500 hover:bg-slate-100 dark:bg-boxdark dark:text-slate-300 dark:hover:bg-meta-4'
              }`}
            >
              {brand.name}
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-5">
        {products.length === 0 ? (
          <div className="flex h-full items-center justify-center text-sm text-slate-400">No hay productos que coincidan con la busqueda.</div>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} inCart={cartProductIds.has(product.id)} onAdd={onAddProduct} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
