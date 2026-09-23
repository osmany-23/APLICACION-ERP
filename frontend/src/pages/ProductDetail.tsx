import { ReactNode, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import {
  FiAlertTriangle,
  FiArrowLeft,
  FiAward,
  FiBox,
  FiChevronDown,
  FiChevronLeft,
  FiChevronRight,
  FiGrid,
  FiHash,
  FiImage,
  FiLayers,
  FiMapPin,
  FiMaximize2,
  FiPackage,
  FiPercent,
  FiShield,
  FiTruck,
} from 'react-icons/fi';
import { useAuth } from '../context/AuthContext';
import { ApiError, apiRequest } from '../services/api';
import { ProductImageMeta } from '../components/ProductImagesManager';
import {
  ProductRecord,
  ProductShowResponse,
  formatCurrency,
  formatQuantity,
  mapProduct,
} from './Products';

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }

  return 'La solicitud no pudo completarse.';
}

// Mismo lenguaje visual que el resto del sistema (Products.tsx, SaleDetail):
// tarjeta blanca, borde y sombra estandar — no glassmorphism ni fondos con
// degradado, que es lo que desentonaba en el intento anterior.
const cardClass =
  'rounded-[10px] border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark';

type ChipTone = 'blue' | 'purple' | 'amber' | 'teal' | 'rose' | 'slate';

const chipIconTones: Record<ChipTone, string> = {
  blue: 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400',
  purple: 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400',
  amber: 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
  teal: 'bg-teal-50 text-teal-600 dark:bg-teal-500/10 dark:text-teal-400',
  rose: 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
  slate: 'bg-slate-100 text-slate-600 dark:bg-white/10 dark:text-slate-300',
};

function InfoChip({
  icon: Icon,
  tone,
  label,
  value,
  wrap = false,
}: {
  icon: typeof FiHash;
  tone: ChipTone;
  label: string;
  value: ReactNode;
  // Medidas en formato libre pueden ser mas largas que un dato corto tipico
  // ("Altura: 30cm, Largo: 66cm, Grosor: 5cm") — no queremos truncarlas y
  // esconder lo que el usuario escribio.
  wrap?: boolean;
}) {
  return (
    <div className="flex items-center gap-3 rounded-lg border border-stroke bg-slate-50 px-3.5 py-3 dark:border-strokedark dark:bg-white/5">
      <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${chipIconTones[tone]}`}>
        <Icon className="h-5 w-5" />
      </span>
      <div className="min-w-0">
        <p className="text-[11px] font-bold uppercase tracking-wide text-slate-400">{label}</p>
        <p className={`text-sm font-bold text-black dark:text-white ${wrap ? '' : 'truncate'}`}>{value}</p>
      </div>
    </div>
  );
}

/**
 * "Ver detalle" de un producto, pagina completa (se abre en una pestana
 * nueva desde Products.tsx). Una sola tarjeta principal (imagen + datos)
 * que usa todo el ancho del contenedor estandar de la app, con el mismo
 * borde/sombra/radio que ya usan Products.tsx y SaleDetail.tsx, para que no
 * desentone visualmente con el resto del sistema.
 */
export default function ProductDetail() {
  const { id } = useParams();
  const { token } = useAuth();
  const [product, setProduct] = useState<ProductRecord | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [imageIndex, setImageIndex] = useState(0);
  const [moreOpen, setMoreOpen] = useState(false);

  useEffect(() => {
    if (!token || !id) return;

    let cancelled = false;
    setLoading(true);
    setError('');

    apiRequest<ProductShowResponse>(`/products/${id}`, {}, token)
      .then((response) => {
        if (cancelled) return;
        const apiProduct = response.product || response.data;
        if (!apiProduct) {
          throw new ApiError(500, 'La API no devolvio el producto solicitado.');
        }
        setProduct(mapProduct(apiProduct));
      })
      .catch((loadError) => {
        if (!cancelled) setError(getErrorMessage(loadError));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [token, id]);

  if (loading) {
    return <div className={`${cardClass} p-8 text-center text-sm text-slate-500`}>Cargando producto...</div>;
  }

  if (error || !product) {
    return (
      <div className={`${cardClass} p-8`}>
        <p className="text-sm text-red-500">{error || 'Producto no encontrado.'}</p>
      </div>
    );
  }

  const images: ProductImageMeta[] =
    product.images.length > 0
      ? product.images
      : product.imageUrl
        ? [
            {
              id: 0,
              uuid: 'primary',
              url: product.imageUrl,
              width: null,
              height: null,
              size_bytes: 0,
              is_primary: true,
              sort_order: 0,
            },
          ]
        : [];
  const safeIndex = images.length ? Math.min(imageIndex, images.length - 1) : 0;
  const activeImage = images[safeIndex] ?? null;
  const isActive = product.status === 'Activo';

  const typeLabel = product.isKit ? 'Combo / Kit' : product.isService ? 'Servicio' : 'Producto';
  const warrantyLabel = product.hasWarranty
    ? `${product.warrantyDuration ?? '-'} ${
        product.warrantyPeriodUnit === 'MONTHS' ? 'meses' : product.warrantyPeriodUnit === 'YEARS' ? 'anos' : 'dias'
      }`
    : null;

  const moreFields: [string, ReactNode][] = [
    ['Modelo', product.model || '-'],
    ['Ubicacion fisica', product.physicalLocation || 'Sin ubicacion'],
    [
      'Descuento maximo',
      product.maxDiscountType
        ? product.maxDiscountType === 'PERCENTAGE'
          ? `${product.maxDiscountValue}%`
          : formatCurrency(product.maxDiscountValue || 0)
        : 'Sin limite propio',
    ],
    ['Tipo de garantia', product.hasWarranty ? product.warrantyType || 'Con garantia' : 'Sin garantia'],
    ...(product.managesLots
      ? ([
          ['Numero de lote', product.lotNumber || '-'],
          ['Fecha de fabricacion', product.manufacturingDate || 'Sin registrar'],
          ['Fecha de vencimiento', product.expirationDate || 'No vence'],
          [
            'Alerta de vencimiento',
            product.expirationAlertDays ? `${product.expirationAlertDays} dias antes` : 'Sin configurar',
          ],
          [
            'Costo de compra del lote',
            product.lotPurchasePrice !== null ? formatCurrency(product.lotPurchasePrice) : 'Igual al costo del producto',
          ],
        ] as [string, ReactNode][])
      : []),
    ...(product.isKit
      ? ([
          [
            'Componentes del combo/kit',
            product.kitItems.length > 0 ? (
              <div className="flex flex-col gap-0.5">
                {product.kitItems.map((item) => (
                  <span key={item.id}>
                    {formatQuantity(item.quantity)} x {item.product_name}
                  </span>
                ))}
              </div>
            ) : (
              'Sin componentes configurados'
            ),
          ],
        ] as [string, ReactNode][])
      : []),
    ['Notas internas', product.notes || 'Sin notas'],
    ['Observaciones', product.observations || 'Sin observaciones'],
  ];

  function showPreviousImage() {
    setImageIndex(safeIndex === 0 ? images.length - 1 : safeIndex - 1);
  }

  function showNextImage() {
    setImageIndex(safeIndex === images.length - 1 ? 0 : safeIndex + 1);
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-2xl font-black text-black dark:text-white">{product.name}</h2>
            <span
              className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-bold ${
                isActive
                  ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                  : 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
              }`}
            >
              {product.status}
            </span>
          </div>
          <p className="text-sm text-slate-500">Codigo: {product.code || 'Sin codigo'}</p>
        </div>
        <button
          type="button"
          onClick={() => window.close()}
          className="inline-flex items-center gap-2 self-start rounded-lg border border-stroke px-4 py-2.5 text-sm font-semibold text-black transition hover:border-primary hover:text-primary dark:border-strokedark dark:text-white"
        >
          <FiArrowLeft className="h-4 w-4" /> Volver
        </button>
      </div>

      {/* Tarjeta principal: imagen + datos en una sola grilla que usa todo
          el ancho disponible. items-start (no stretch): cada columna toma
          su propio alto natural, asi la mas corta no deja espacio vacio
          reservado por la mas larga. */}
      <div className={`${cardClass} grid grid-cols-1 items-start p-5 lg:grid-cols-12 lg:gap-6 lg:p-6`}>
        <div className="lg:col-span-7">
          <div className="mb-4 flex flex-wrap items-center gap-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900 dark:bg-emerald-900/10">
            <div>
              <p className="text-xs font-bold uppercase text-emerald-700 dark:text-emerald-400">Precio de venta</p>
              <p className="text-xl font-black text-emerald-600">{formatCurrency(product.salePrice)}</p>
            </div>
            <div>
              <p className="text-xs font-bold uppercase text-emerald-700 dark:text-emerald-400">Costo</p>
              <p className="text-xl font-black text-emerald-800 dark:text-emerald-300">{formatCurrency(product.cost)}</p>
            </div>
            {warrantyLabel && (
              <span className="ml-auto inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                <FiShield className="h-3.5 w-3.5" /> Garantia {warrantyLabel}
              </span>
            )}
          </div>

          <div className="flex flex-col gap-2.5">
            <InfoChip icon={FiHash} tone="slate" label="Codigo" value={product.code || 'Sin codigo'} />
            <InfoChip icon={FiBox} tone="teal" label="Tipo de producto" value={typeLabel} />
            <InfoChip icon={FiGrid} tone="blue" label="Categoria" value={product.category} />
            <InfoChip
              icon={FiLayers}
              tone="blue"
              label="Subcategoria"
              value={product.subcategory || 'Sin subcategoria'}
            />
            <InfoChip icon={FiAward} tone="purple" label="Marca" value={product.brand} />
            <InfoChip icon={FiPackage} tone="purple" label="Unidad" value={product.unit} />
            {product.measurement && (
              <InfoChip icon={FiMaximize2} tone="teal" label="Medida" value={product.measurement} wrap />
            )}
            <InfoChip icon={FiTruck} tone="amber" label="Proveedor" value={product.supplier} />
            <InfoChip
              icon={FiPercent}
              tone="amber"
              label="Impuesto"
              value={product.taxType === 'TAXABLE' ? `${product.taxPercentage}%` : 'Exento'}
            />
            <InfoChip
              icon={FiAlertTriangle}
              tone="rose"
              label="Alerta de existencias"
              value={formatQuantity(product.minStock)}
            />
          </div>
        </div>

        <div className="mt-5 lg:col-span-5 lg:mt-0">
          {/* Tamano fijo de "ventana" para ver la imagen (no crece con el
              alto de la columna de al lado): una imagen mas grande se
              reduce para entrar ahi (object-contain), una mas chica se ve
              a su tamano real, centrada — siempre la misma caja. */}
          <div className="group relative h-[320px] w-full overflow-hidden">
            {activeImage ? (
              <img src={activeImage.url} alt={product.name} className="h-full w-full object-contain" />
            ) : (
              <div className="flex h-full w-full items-center justify-center rounded-lg border border-dashed border-stroke text-slate-300 dark:border-strokedark dark:text-slate-600">
                <FiImage className="h-20 w-20" />
              </div>
            )}
            {images.length > 1 && (
              <>
                <button
                  type="button"
                  onClick={showPreviousImage}
                  className="absolute left-3 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-black opacity-0 shadow transition hover:bg-white group-hover:opacity-100 dark:bg-boxdark/90 dark:text-white"
                  aria-label="Imagen anterior"
                >
                  <FiChevronLeft className="h-5 w-5" />
                </button>
                <button
                  type="button"
                  onClick={showNextImage}
                  className="absolute right-3 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-black opacity-0 shadow transition hover:bg-white group-hover:opacity-100 dark:bg-boxdark/90 dark:text-white"
                  aria-label="Siguiente imagen"
                >
                  <FiChevronRight className="h-5 w-5" />
                </button>
                <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5 rounded-full bg-black/30 px-2.5 py-1.5 backdrop-blur">
                  {images.map((image, index) => (
                    <button
                      key={image.id}
                      type="button"
                      onClick={() => setImageIndex(index)}
                      className={`h-2 w-2 rounded-full transition ${index === safeIndex ? 'bg-white' : 'bg-white/40'}`}
                      aria-label={`Ver imagen ${index + 1}`}
                    />
                  ))}
                </div>
              </>
            )}
          </div>

          {/* Nota y disponibilidad viven bajo la imagen (no como secciones
              aparte de ancho completo mas abajo) para que esta columna
              llene el mismo alto que la de los campos, en vez de dejar un
              hueco en blanco debajo de la foto. */}
          <div className="mt-4 rounded-lg border border-stroke bg-slate-50 px-4 py-3 dark:border-strokedark dark:bg-white/5">
            <p className="text-xs font-bold uppercase text-slate-400">Nota</p>
            <p className="mt-1 text-sm font-semibold text-black dark:text-white">
              {product.description || 'Sin nota registrada.'}
            </p>
          </div>

          <div className="mt-4 rounded-lg border border-stroke bg-slate-50 px-4 py-3 dark:border-strokedark dark:bg-white/5">
            <p className="mb-2 flex items-center gap-2 text-xs font-bold uppercase text-slate-400">
              <FiMapPin className="h-3.5 w-3.5 text-primary" /> Disponibilidad por sucursal
            </p>
            {product.stockByBranch.length > 0 ? (
              <div className="flex flex-col gap-2">
                {product.stockByBranch.map((row) => (
                  <div
                    key={row.branch_id ?? row.branch_name}
                    className="flex items-center justify-between rounded-lg border border-stroke bg-white px-3.5 py-2.5 dark:border-strokedark dark:bg-boxdark"
                  >
                    <div>
                      <p className="text-sm font-bold text-black dark:text-white">{row.branch_name}</p>
                      <p className="text-xs text-slate-500">Existencia: {formatQuantity(row.quantity)}</p>
                    </div>
                    <span
                      className={`rounded-full px-3 py-1 text-sm font-black ${
                        row.available_quantity > 0
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                          : 'bg-slate-200 text-slate-500 dark:bg-white/10 dark:text-slate-400'
                      }`}
                    >
                      {formatQuantity(row.available_quantity)}
                    </span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-sm text-slate-500">No hay sucursales con almacenes configurados.</p>
            )}
          </div>
        </div>
      </div>

      <div className={cardClass}>
        <button
          type="button"
          onClick={() => setMoreOpen((current) => !current)}
          className="flex w-full items-center justify-between px-5 py-3.5 text-left text-sm font-bold text-black dark:text-white lg:px-6"
        >
          Mas informacion
          <FiChevronDown className={`h-4 w-4 transition-transform ${moreOpen ? 'rotate-180' : ''}`} />
        </button>
        {moreOpen && (
          <div className="grid grid-cols-1 gap-x-6 gap-y-1 border-t border-stroke px-5 py-3 dark:border-strokedark sm:grid-cols-2 lg:px-6">
            {moreFields.map(([label, value]) => (
              <div key={label} className="flex items-baseline gap-3 py-1.5">
                <span className="w-36 shrink-0 text-xs font-bold uppercase text-slate-400">{label}</span>
                <span className="text-sm font-semibold text-black dark:text-white">{value}</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
