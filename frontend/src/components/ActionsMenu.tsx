import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { FiMoreVertical } from 'react-icons/fi';
import ClickOutside from './ClickOutside';

export type ActionMenuItem = {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  onClick: () => void;
  variant?: 'default' | 'danger';
};

const MENU_WIDTH = 208; // w-52

/**
 * Menu de acciones compacto ("..." -> lista desplegable) para filas de
 * tabla, pensado para reemplazar una fila de botones sueltos (ver/editar/
 * kardex/eliminar) por un solo control y optimizar espacio horizontal.
 *
 * Se renderiza via portal a document.body y se posiciona con
 * getBoundingClientRect(): las tablas de este proyecto viven dentro de
 * contenedores con overflow-x-auto, que por la propia spec de CSS terminan
 * clipeando tambien el eje vertical (overflow-x no-visible fuerza
 * overflow-y a 'auto') — un dropdown posicionado con simple CSS absoluto
 * quedaria cortado. El portal evita ese problema por completo.
 */
export default function ActionsMenu({
  items,
  disabled = false,
  ariaLabel = 'Mas opciones',
}: {
  items: ActionMenuItem[];
  disabled?: boolean;
  ariaLabel?: string;
}) {
  const [open, setOpen] = useState(false);
  const [position, setPosition] = useState<{ top: number; left: number } | null>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);

  function toggle() {
    if (disabled) return;

    if (!open && triggerRef.current) {
      const rect = triggerRef.current.getBoundingClientRect();
      const estimatedMenuHeight = items.length * 42 + 16;
      const openUpward = rect.bottom + estimatedMenuHeight > window.innerHeight - 12;

      setPosition({
        top: openUpward ? rect.top - estimatedMenuHeight - 6 : rect.bottom + 6,
        left: Math.max(8, rect.right - MENU_WIDTH),
      });
    }

    setOpen((current) => !current);
  }

  // Un dropdown fijo por coordenadas no sigue el scroll: mas simple y
  // predecible cerrarlo que tratar de recalcular su posicion en cada frame.
  useEffect(() => {
    if (!open) return;

    function closeOnScroll() {
      setOpen(false);
    }

    window.addEventListener('scroll', closeOnScroll, true);
    window.addEventListener('resize', closeOnScroll);

    return () => {
      window.removeEventListener('scroll', closeOnScroll, true);
      window.removeEventListener('resize', closeOnScroll);
    };
  }, [open]);

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        onClick={toggle}
        disabled={disabled}
        aria-label={ariaLabel}
        aria-haspopup="menu"
        aria-expanded={open}
        className={`inline-flex h-10 w-10 items-center justify-center rounded-lg border transition disabled:cursor-not-allowed disabled:opacity-60 ${
          open
            ? 'border-primary bg-primary text-white'
            : 'border-stroke text-slate-600 hover:border-primary hover:bg-primary/5 hover:text-primary dark:border-strokedark dark:text-bodydark'
        }`}
      >
        <FiMoreVertical className="h-5 w-5" />
      </button>

      {open &&
        position &&
        createPortal(
          <ClickOutside onClick={() => setOpen(false)} exceptionRef={triggerRef}>
            <AnimatePresence>
              <motion.div
                role="menu"
                initial={{ opacity: 0, scale: 0.96, y: -4 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.96, y: -4 }}
                transition={{ duration: 0.12, ease: 'easeOut' }}
                style={{ position: 'fixed', top: position.top, left: position.left, width: MENU_WIDTH }}
                className="z-[100000] overflow-hidden rounded-xl border border-stroke bg-white py-1.5 shadow-elevated dark:border-strokedark dark:bg-boxdark"
              >
                {items.map((item, index) => {
                  const isDanger = item.variant === 'danger';
                  const startsNewGroup = isDanger && items[index - 1]?.variant !== 'danger';

                  return (
                    <div key={item.label}>
                      {startsNewGroup && <div className="my-1 border-t border-stroke dark:border-strokedark" />}
                      <button
                        type="button"
                        role="menuitem"
                        onClick={() => {
                          setOpen(false);
                          item.onClick();
                        }}
                        className={`flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm font-semibold transition ${
                          isDanger
                            ? 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20'
                            : 'text-black hover:bg-gray-2 dark:text-white dark:hover:bg-meta-4'
                        }`}
                      >
                        <item.icon className="h-4 w-4 shrink-0" />
                        {item.label}
                      </button>
                    </div>
                  );
                })}
              </motion.div>
            </AnimatePresence>
          </ClickOutside>,
          document.body,
        )}
    </>
  );
}
