import { FiPause, FiPlay, FiTrash2, FiX } from 'react-icons/fi';
import { HeldCart } from '../../../types/pos';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

export default function HeldCartsModal({
  carts,
  onResume,
  onDiscard,
  onClose,
}: {
  carts: HeldCart[];
  onResume: (cart: HeldCart) => void;
  onDiscard: (cart: HeldCart) => void;
  onClose: () => void;
}) {
  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="max-h-[80vh] w-full max-w-lg overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-amber-400 to-amber-500 px-5 py-4 text-amber-900">
          <span className="flex items-center gap-2 text-sm font-black uppercase tracking-wide"><FiPause /> Carritos en espera</span>
          <button type="button" onClick={onClose} className="rounded-full p-1 hover:bg-black/10">
            <FiX />
          </button>
        </div>

        <div className="max-h-[60vh] divide-y divide-stroke overflow-y-auto dark:divide-strokedark">
          {carts.length === 0 && <p className="py-10 text-center text-sm text-slate-500">No hay carritos en espera.</p>}

          {carts.map((cart) => {
            const total = cart.items.reduce((sum, item) => sum + item.unit_price * item.quantity, 0);

            return (
              <div key={cart.id} className="flex items-center justify-between gap-3 px-5 py-3">
                <div>
                  <p className="font-bold text-black dark:text-white">{cart.label}</p>
                  <p className="text-xs text-slate-500">
                    {cart.items.length} producto(s) · {formatCurrency(total)} · {new Date(cart.savedAt).toLocaleTimeString('es-NI', { hour: '2-digit', minute: '2-digit' })}
                  </p>
                </div>
                <div className="flex gap-2">
                  <button type="button" onClick={() => onResume(cart)} title="Reanudar" className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-primary text-primary hover:bg-primary hover:text-white">
                    <FiPlay className="h-4 w-4" />
                  </button>
                  <button type="button" onClick={() => onDiscard(cart)} title="Descartar" className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-500 text-red-500 hover:bg-red-500 hover:text-white">
                    <FiTrash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
