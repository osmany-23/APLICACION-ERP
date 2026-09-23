import { FiFileText, FiMinus, FiPackage, FiPause, FiPlay, FiPlus, FiRefreshCw, FiShoppingCart, FiSliders, FiTag, FiTrash2, FiTruck } from 'react-icons/fi';
import { PosCartLine } from '../../../types/pos';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

export type CartTotals = {
  quantityTotal: number;
  subtotal: number;
  discountAmount: number;
  taxAmount: number;
  shippingAmount: number;
  total: number;
  effectiveTaxPercent: number;
};

export default function CartPanel({
  items,
  onIncrement,
  onDecrement,
  onRemove,
  discountType,
  discountValue,
  onDiscountTypeChange,
  onDiscountValueChange,
  shipping,
  onShippingChange,
  totals,
  onMantener,
  onReiniciar,
  onPagar,
  isProforma,
  heldCount,
  onOpenHeld,
}: {
  items: PosCartLine[];
  onIncrement: (key: string) => void;
  onDecrement: (key: string) => void;
  onRemove: (key: string) => void;
  discountType: 'fixed' | 'percentage';
  discountValue: string;
  onDiscountTypeChange: (type: 'fixed' | 'percentage') => void;
  onDiscountValueChange: (value: string) => void;
  shipping: string;
  onShippingChange: (value: string) => void;
  totals: CartTotals;
  onMantener: () => void;
  onReiniciar: () => void;
  onPagar: () => void;
  isProforma: boolean;
  heldCount: number;
  onOpenHeld: () => void;
}) {
  return (
    <div className="flex h-full flex-col">
      {heldCount > 0 && (
        <button
          type="button"
          onClick={onOpenHeld}
          className="mb-4 flex items-center justify-between rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2.5 text-xs font-bold text-amber-700 transition hover:bg-amber-100 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300"
        >
          <span className="flex items-center gap-1.5"><FiPause /> Carritos en espera</span>
          <span className="rounded-full bg-amber-500 px-2 py-0.5 text-white">{heldCount}</span>
        </button>
      )}

      <div className="flex min-h-0 flex-1 gap-6">
        {/* Columna izquierda: lista de productos del carrito */}
        <div className="flex min-w-0 flex-[1.7] flex-col">
          <div className="mb-2.5 flex items-center justify-between">
            <p className="text-sm font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Productos en el carrito</p>
            <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-meta-4 dark:text-slate-300">
              {items.length} item{items.length === 1 ? '' : 's'}
            </span>
          </div>

          <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-stroke shadow-sm dark:border-strokedark">
            <div className="flex shrink-0 items-center gap-3 border-b border-stroke bg-slate-50 px-4 py-2.5 text-[11px] font-black uppercase tracking-wide text-slate-500 dark:border-strokedark dark:bg-white/[0.03] dark:text-slate-400">
              <span className="w-11 shrink-0" />
              <span className="min-w-0 flex-1">Producto</span>
              <span className="w-[108px] shrink-0 text-center">Cantidad</span>
              <span className="w-24 shrink-0 text-right">Precio</span>
              <span className="w-28 shrink-0 text-right">Sub total</span>
              <span className="w-9 shrink-0" />
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto bg-white dark:bg-boxdark">
              {items.length === 0 ? (
                <div className="flex h-full flex-col items-center justify-center gap-2 px-4 py-16 text-center text-slate-300 dark:text-slate-600">
                  <FiPackage className="h-9 w-9" />
                  <p className="text-sm font-semibold text-slate-400 dark:text-slate-500">El carrito esta vacio</p>
                  <p className="text-xs text-slate-300 dark:text-slate-600">Presiona Ctrl para abrir el catalogo y agregar productos</p>
                </div>
              ) : (
                items.map((item) => (
                  <div
                    key={item.key}
                    className="flex items-center gap-3 border-b border-stroke px-4 py-2.5 transition last:border-b-0 hover:bg-slate-50 dark:border-strokedark dark:hover:bg-white/[0.03]"
                  >
                    <div className="h-11 w-11 shrink-0 overflow-hidden rounded-lg border border-stroke bg-slate-50 dark:border-strokedark dark:bg-meta-4">
                      {item.image_url ? (
                        <img src={item.image_url} alt={item.name} className="h-full w-full object-contain p-1" />
                      ) : (
                        <div className="flex h-full w-full items-center justify-center text-slate-300">
                          <FiPackage className="h-4.5 w-4.5" />
                        </div>
                      )}
                    </div>

                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-bold text-black dark:text-white">{item.name}</p>
                      <p className="truncate text-[11px] text-slate-400">{item.code}</p>
                    </div>

                    <div className="flex w-[108px] shrink-0 items-center justify-center gap-1 rounded-lg border border-stroke bg-slate-50 p-1 dark:border-strokedark dark:bg-meta-4">
                      <button type="button" onClick={() => onDecrement(item.key)} className="flex h-6.5 w-6.5 items-center justify-center rounded-md bg-white text-slate-500 shadow-sm transition hover:text-primary dark:bg-boxdark">
                        <FiMinus className="h-3 w-3" />
                      </button>
                      <span className="w-6 text-center text-sm font-black text-black dark:text-white">{item.quantity}</span>
                      <button type="button" onClick={() => onIncrement(item.key)} className="flex h-6.5 w-6.5 items-center justify-center rounded-md bg-white text-slate-500 shadow-sm transition hover:text-primary dark:bg-boxdark">
                        <FiPlus className="h-3 w-3" />
                      </button>
                    </div>

                    <div className="w-24 shrink-0 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">{formatCurrency(item.unit_price)}</div>
                    <div className="w-28 shrink-0 text-right text-sm font-black text-black dark:text-white">{formatCurrency(item.unit_price * item.quantity)}</div>

                    <button
                      type="button"
                      onClick={() => onRemove(item.key)}
                      title="Quitar del carrito"
                      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500 transition hover:bg-red-500 hover:text-white dark:bg-red-500/10"
                    >
                      <FiTrash2 className="h-4 w-4" />
                    </button>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>

        {/* Columna derecha: ajustes de venta, totales y acciones. Antes usaba
            justify-between para repartir el espacio, pero en pantallas mas
            bajas el contenido supera el alto disponible y ese espacio "extra"
            se reduce a cero: las tarjetas terminan pegadas/encimadas entre
            si. Con un gap fijo (nunca colapsa) + scroll propio como red de
            seguridad, siempre queda una separacion visible sin importar el
            alto de la ventana. */}
        <div className="flex w-[380px] min-h-0 shrink-0 flex-col gap-5 overflow-y-auto">
          <div className="shrink-0 overflow-hidden rounded-2xl border border-stroke bg-white shadow-sm dark:border-strokedark dark:bg-boxdark">
            <div className="flex items-center gap-2 border-b border-stroke bg-slate-50 px-4 py-3 dark:border-strokedark dark:bg-white/[0.03]">
              <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <FiSliders className="h-3.5 w-3.5" />
              </span>
              <p className="text-sm font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Ajustes de la venta</p>
            </div>

            <div className="space-y-3 p-4">
              <div className="flex items-center gap-3 rounded-xl border border-stroke bg-slate-50/70 px-3 py-2.5 dark:border-strokedark dark:bg-white/[0.03]">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-500 dark:bg-sky-500/20">
                  <FiTruck className="h-4 w-4" />
                </span>
                <span className="flex-1 text-sm font-bold text-slate-700 dark:text-slate-200">Envio</span>
                <input
                  type="number"
                  min="0"
                  value={shipping}
                  onChange={(event) => onShippingChange(event.target.value)}
                  className="h-10 w-28 rounded-lg border border-stroke bg-white px-2.5 text-right text-sm font-bold text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                  placeholder="0"
                />
              </div>

              <div className="rounded-xl border border-stroke bg-slate-50/70 px-3 py-2.5 dark:border-strokedark dark:bg-white/[0.03]">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-500 dark:bg-rose-500/20">
                    <FiTag className="h-4 w-4" />
                  </span>
                  <span className="flex-1 text-sm font-bold text-slate-700 dark:text-slate-200">Descuento</span>
                  <div className="flex items-center gap-0.5 rounded-lg bg-slate-200/70 p-0.5 dark:bg-meta-4">
                    <button
                      type="button"
                      onClick={() => onDiscountTypeChange('fixed')}
                      className={`rounded-md px-2.5 py-1 text-[11px] font-black transition ${
                        discountType === 'fixed'
                          ? 'bg-white text-primary shadow-sm dark:bg-boxdark'
                          : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                      }`}
                    >
                      Fijo
                    </button>
                    <button
                      type="button"
                      onClick={() => onDiscountTypeChange('percentage')}
                      className={`rounded-md px-2.5 py-1 text-[11px] font-black transition ${
                        discountType === 'percentage'
                          ? 'bg-white text-primary shadow-sm dark:bg-boxdark'
                          : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                      }`}
                    >
                      %
                    </button>
                  </div>
                </div>
                <input
                  type="number"
                  min="0"
                  value={discountValue}
                  onChange={(event) => onDiscountValueChange(event.target.value)}
                  className="mt-2.5 h-10 w-full rounded-lg border border-stroke bg-white px-3 text-sm font-bold text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                  placeholder="0"
                />
              </div>
            </div>
          </div>

          <div className="shrink-0 space-y-4">
            <div className="overflow-hidden rounded-2xl border border-stroke bg-white shadow-sm dark:border-strokedark dark:bg-boxdark">
              <div className="border-b border-stroke px-4 py-3 dark:border-strokedark">
                <p className="text-sm font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">Resumen</p>
              </div>
              <div className="divide-y divide-stroke px-4 dark:divide-strokedark">
                <div className="flex items-center justify-between py-2.5 text-sm">
                  <span className="font-semibold text-slate-500 dark:text-slate-400">Productos</span>
                  <span className="font-black text-black dark:text-white">{totals.quantityTotal}</span>
                </div>
                <div className="flex items-center justify-between py-2.5 text-sm">
                  <span className="font-semibold text-slate-500 dark:text-slate-400">Subtotal</span>
                  <span className="font-black text-black dark:text-white">{formatCurrency(totals.subtotal)}</span>
                </div>
                {totals.discountAmount > 0 && (
                  <div className="flex items-center justify-between py-2.5 text-sm">
                    <span className="font-semibold text-slate-500 dark:text-slate-400">Descuento</span>
                    <span className="font-black text-red-500">- {formatCurrency(totals.discountAmount)}</span>
                  </div>
                )}
                {totals.taxAmount > 0 && (
                  <div className="flex items-center justify-between py-2.5 text-sm">
                    <span className="font-semibold text-slate-500 dark:text-slate-400">Impuesto</span>
                    <span className="font-black text-black dark:text-white">+ {formatCurrency(totals.taxAmount)}</span>
                  </div>
                )}
                <div className="flex items-center justify-between py-2.5 text-sm">
                  <span className="font-semibold text-slate-500 dark:text-slate-400">Envio</span>
                  <span className={`font-black ${totals.shippingAmount > 0 ? 'text-emerald-500' : 'text-black dark:text-white'}`}>
                    {totals.shippingAmount > 0 ? '+ ' : ''}
                    {formatCurrency(totals.shippingAmount)}
                  </span>
                </div>
              </div>
            </div>

            <div className="overflow-hidden rounded-2xl bg-gradient-to-br from-primary to-[#4338CA] p-5 text-white shadow-lg">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-xs font-black uppercase tracking-widest text-white/70">Total a pagar</p>
                  <p className="text-5xl font-black leading-tight tracking-tight">{formatCurrency(totals.total)}</p>
                </div>
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15">
                  <FiShoppingCart className="h-6 w-6" />
                </span>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <button
                type="button"
                onClick={onMantener}
                disabled={items.length === 0}
                className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-amber-400 text-sm font-black text-amber-900 shadow-sm transition hover:bg-amber-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <FiPause className="h-4 w-4" /> Mantener
              </button>
              <button
                type="button"
                onClick={onReiniciar}
                disabled={items.length === 0}
                className="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-red-500 text-sm font-black text-white shadow-sm transition hover:bg-red-600 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <FiRefreshCw className="h-4 w-4" /> Reiniciar
              </button>
            </div>

            <button
              type="button"
              onClick={onPagar}
              disabled={items.length === 0}
              className={`inline-flex h-16 w-full items-center justify-center gap-2 rounded-xl text-base font-black text-white shadow-lg transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 ${
                isProforma ? 'bg-violet-500 hover:bg-violet-600' : 'bg-[#0F9F37] hover:bg-[#0c8a2e]'
              }`}
            >
              {isProforma ? <FiFileText className="h-5 w-5" /> : <FiPlay className="h-5 w-5" />}
              {isProforma ? 'Generar proforma' : 'Pagar ahora'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
