import { useEffect, useMemo, useRef, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
  FiBriefcase,
  FiChevronDown,
  FiFileText,
  FiGlobe,
  FiGrid,
  FiLock,
  FiMenu,
  FiPackage,
  FiSettings,
  FiShare2,
  FiShoppingCart,
  FiUsers,
} from 'react-icons/fi';
import { BsPinAngle, BsPinFill } from 'react-icons/bs';
import { useAuth } from '../../context/AuthContext';
import { hasModuleAccess } from '../Auth/RequirePermission';
import { useBranding } from '../../context/BrandingContext';

interface SidebarProps {
  sidebarOpen: boolean;
  setSidebarOpen: (arg: boolean) => void;
}

type NavChild = { label: string; to: string };

// Paleta por item: fondo suave + texto de color a juego, pensada para
// contrastar bien sobre el fondo oscuro fijo del sidebar.
type IconColor =
  | 'sky'
  | 'violet'
  | 'emerald'
  | 'amber'
  | 'cyan'
  | 'rose'
  | 'indigo'
  | 'slate'
  | 'red'
  | 'teal';

const ICON_COLOR_CLASSES: Record<IconColor, string> = {
  sky: 'bg-sky-500/15 text-sky-400 group-hover/icon:bg-sky-500/25',
  violet: 'bg-violet-500/15 text-violet-400 group-hover/icon:bg-violet-500/25',
  emerald: 'bg-emerald-500/15 text-emerald-400 group-hover/icon:bg-emerald-500/25',
  amber: 'bg-amber-500/15 text-amber-400 group-hover/icon:bg-amber-500/25',
  cyan: 'bg-cyan-500/15 text-cyan-400 group-hover/icon:bg-cyan-500/25',
  rose: 'bg-rose-500/15 text-rose-400 group-hover/icon:bg-rose-500/25',
  indigo: 'bg-indigo-500/15 text-indigo-400 group-hover/icon:bg-indigo-500/25',
  slate: 'bg-slate-500/15 text-slate-300 group-hover/icon:bg-slate-500/25',
  red: 'bg-red-500/15 text-red-400 group-hover/icon:bg-red-500/25',
  teal: 'bg-teal-500/15 text-teal-400 group-hover/icon:bg-teal-500/25',
};

type NavLeaf = {
  kind: 'link';
  label: string;
  to: string;
  end?: boolean;
  icon: React.ComponentType<{ className?: string }>;
  color: IconColor;
};

type NavGroup = {
  kind: 'group';
  label: string;
  basePath: string;
  icon: React.ComponentType<{ className?: string }>;
  color: IconColor;
  children: NavChild[];
};

type NavItem = NavLeaf | NavGroup;

// requiresModule: si se define, la seccion solo se muestra cuando el rol del
// usuario tiene acceso a alguno de esos modulos de permisos (ver
// hasModuleAccess). Hoy solo la usa "Administracion" — el resto del menu
// sigue visible para cualquier usuario autenticado, como siempre.
type NavSection = { title: string; items: NavItem[]; requiresModule?: string | string[] };

// Estructura del menu: agrupada por flujo de negocio (General -> Operaciones
// -> Configuracion) para que el orden se lea de forma profesional y logica.
const NAV_SECTIONS: NavSection[] = [
  {
    title: 'General',
    items: [
      { kind: 'link', label: 'Panel de Control', to: '/', end: true, icon: FiGrid, color: 'sky' },
      { kind: 'link', label: 'Clientes', to: '/customers', icon: FiUsers, color: 'violet' },
    ],
  },
  {
    title: 'Operaciones',
    items: [
      {
        kind: 'group',
        label: 'Ventas / Facturación',
        basePath: '/sales',
        icon: FiFileText,
        color: 'emerald',
        children: [
          { label: 'Facturas', to: '/sales' },
          { label: 'Nueva factura', to: '/sales/new' },
        ],
      },
      {
        kind: 'group',
        label: 'Compras',
        basePath: '/purchases',
        icon: FiShoppingCart,
        color: 'amber',
        children: [
          { label: 'Compras', to: '/purchases' },
          { label: 'Nueva compra', to: '/purchases/new' },
        ],
      },
      {
        kind: 'group',
        label: 'Productos',
        basePath: '/products',
        icon: FiPackage,
        color: 'cyan',
        children: [
          { label: 'Productos', to: '/products' },
          { label: 'Marcas', to: '/products/brands' },
          { label: 'Categorías', to: '/products/categories' },
          { label: 'Subcategorías', to: '/products/subcategories' },
          { label: 'Unidades de medida', to: '/products/units' },
        ],
      },
      {
        kind: 'group',
        label: 'Relaciones',
        basePath: '/relations',
        icon: FiShare2,
        color: 'rose',
        children: [
          { label: 'Proveedores', to: '/relations/suppliers' },
          { label: 'Almacenes', to: '/relations/warehouses' },
          { label: 'Tiendas / Sucursales', to: '/relations/branches' },
          { label: 'Empresa', to: '/relations/company' },
        ],
      },
    ],
  },
  {
    title: 'Configuración',
    items: [
      {
        kind: 'group',
        label: 'Catálogos Globales',
        basePath: '/settings/',
        icon: FiGlobe,
        color: 'indigo',
        children: [
          { label: 'Monedas', to: '/settings/currencies' },
          { label: 'Términos de Pago', to: '/settings/payment-terms' },
          { label: 'Métodos de Pago', to: '/settings/payment-methods' },
          { label: 'Tipos de Documento', to: '/settings/document-types' },
        ],
      },
      { kind: 'link', label: 'Configuración', to: '/settings', icon: FiSettings, color: 'slate' },
    ],
  },
  {
    title: 'Administración',
    requiresModule: ['usuarios', 'roles', 'permisos', 'empleados'],
    items: [
      {
        kind: 'group',
        label: 'Usuarios y Accesos',
        basePath: '/administration',
        icon: FiLock,
        color: 'red',
        children: [
          { label: 'Usuarios', to: '/administration/users' },
          { label: 'Roles', to: '/administration/roles' },
          { label: 'Permisos', to: '/administration/permissions' },
        ],
      },
      {
        kind: 'group',
        label: 'Empleados',
        basePath: '/administration/employees',
        icon: FiBriefcase,
        color: 'teal',
        children: [
          { label: 'Empleados', to: '/administration/employees' },
          { label: 'Departamentos', to: '/administration/departments' },
          { label: 'Cargos', to: '/administration/positions' },
        ],
      },
    ],
  },
];

// Coincide contra las rutas hijas reales, no solo contra basePath: dos
// grupos pueden compartir el mismo prefijo largo (ej. "Usuarios y Accesos" y
// "Empleados" viven ambos bajo /administration) y basePath solo no alcanza
// para distinguirlos sin ambiguedad.
function groupMatchesPath(item: NavGroup, pathname: string): boolean {
  if (item.children.some((child) => pathname === child.to || pathname.startsWith(`${child.to}/`))) {
    return true;
  }

  return pathname.startsWith(item.basePath) && !NAV_SECTIONS.some((section) =>
    section.items.some(
      (other) =>
        other.kind === 'group'
        && other !== item
        && other.children.some((child) => pathname === child.to || pathname.startsWith(`${child.to}/`)),
    ),
  );
}

function findGroupForPath(pathname: string): string | null {
  for (const section of NAV_SECTIONS) {
    for (const item of section.items) {
      if (item.kind === 'group' && groupMatchesPath(item, pathname)) {
        return item.label;
      }
    }
  }
  return null;
}

function IconBadge({
  icon: Icon,
  color,
}: {
  icon: React.ComponentType<{ className?: string }>;
  color: IconColor;
}) {
  return (
    <span
      className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors duration-200 ${ICON_COLOR_CLASSES[color]}`}
    >
      <Icon className="h-5.5 w-5.5 transition-transform duration-200 group-hover/icon:scale-110" />
    </span>
  );
}

const Sidebar = ({ sidebarOpen, setSidebarOpen }: SidebarProps) => {
  const location = useLocation();
  const { pathname } = location;
  const { branding, initials, loginLogoUrl, logoUrl } = useBranding();
  const { user } = useAuth();

  // Secciones visibles para este usuario: todo lo que no exige un modulo, mas
  // "Administracion" solo si su rol tiene permiso sobre 'usuarios' (o
  // superior). No oculta nada por si sola: el backend vuelve a validar cada
  // endpoint, esto es solo para no mostrar un menu que llevaria a un 403.
  const visibleSections = useMemo(
    () => NAV_SECTIONS.filter((section) => !section.requiresModule || hasModuleAccess(user, section.requiresModule)),
    [user],
  );

  const trigger = useRef<any>(null);
  const sidebar = useRef<any>(null);

  // Fijado: si esta activo, el sidebar se queda desplegado siempre.
  // Persiste entre sesiones.
  const [pinned, setPinned] = useState<boolean>(
    () => localStorage.getItem('sidebar-pinned') === 'true',
  );
  // Mientras no este fijado, el mouse sobre el sidebar lo despliega
  // temporalmente; al salir, vuelve a modo compacto (solo iconos).
  const [hovering, setHovering] = useState(false);
  const collapsed = !pinned && !hovering;

  // Unico grupo desplegable abierto a la vez (acordeon), inicia en el que
  // corresponda a la ruta actual.
  const [openGroup, setOpenGroup] = useState<string | null>(() => findGroupForPath(pathname));

  useEffect(() => {
    localStorage.setItem('sidebar-pinned', pinned ? 'true' : 'false');
  }, [pinned]);

  // Si la navegacion cambia hacia un grupo distinto, lo abre automaticamente.
  useEffect(() => {
    const match = findGroupForPath(pathname);
    if (match) {
      setOpenGroup(match);
    }
  }, [pathname]);

  // close on click outside
  useEffect(() => {
    const clickHandler = ({ target }: MouseEvent) => {
      if (!sidebar.current || !trigger.current) return;
      if (
        !sidebarOpen ||
        sidebar.current.contains(target) ||
        trigger.current.contains(target)
      )
        return;
      setSidebarOpen(false);
    };
    document.addEventListener('click', clickHandler);
    return () => document.removeEventListener('click', clickHandler);
  });

  // close if the esc key is pressed
  useEffect(() => {
    const keyHandler = ({ keyCode }: KeyboardEvent) => {
      if (!sidebarOpen || keyCode !== 27) return;
      setSidebarOpen(false);
    };
    document.addEventListener('keydown', keyHandler);
    return () => document.removeEventListener('keydown', keyHandler);
  });

  function handleSidebarMouseEnter() {
    if (window.innerWidth < 1024) return; // el hover-expand es solo de escritorio
    setHovering(true);
  }

  function handleSidebarMouseLeave() {
    setHovering(false);
  }

  function toggleGroup(label: string) {
    setOpenGroup((current) => (current === label ? null : label));
  }

  // Transiciones cortas y con "ease-out" (arranca rapido, frena suave) para
  // que el despliegue se sienta instantaneo y fluido, no lento.
  const labelFade = `overflow-hidden text-ellipsis whitespace-nowrap transition-[opacity,max-width,margin] duration-150 ease-out ${
    collapsed ? 'lg:max-w-0 lg:opacity-0 lg:ml-0' : 'max-w-[220px] opacity-100 ml-0'
  }`;

  // Los titulos de sección (GENERAL/OPERACIONES/...) tambien deben colapsar
  // su alto, no solo desvanecerse: si no, dejan un hueco vacio entre grupos
  // de iconos cuando el menu esta cerrado.
  const sectionTitleFade = `ml-4 overflow-hidden text-sm font-semibold text-bodydark2 transition-[opacity,max-height,margin] duration-150 ease-out ${
    collapsed ? 'lg:mb-0 lg:max-h-0 lg:opacity-0' : 'mb-4 max-h-6 opacity-100'
  }`;

  return (
    <aside
      ref={sidebar}
      onMouseEnter={handleSidebarMouseEnter}
      onMouseLeave={handleSidebarMouseLeave}
      className={`absolute left-0 top-0 z-9999 flex h-screen w-72.5 flex-col overflow-y-hidden bg-black transition-[transform,width] duration-200 ease-out will-change-[width] dark:bg-boxdark lg:static lg:translate-x-0 ${
        sidebarOpen ? 'translate-x-0' : '-translate-x-full'
      } ${collapsed ? 'lg:w-20' : 'lg:w-72.5'}`}
    >
      {/* <!-- SIDEBAR HEADER --> */}
      <div
        className={`flex items-center justify-between gap-2 border-b border-white/[0.06] px-6 py-5.5 lg:py-6.5 ${
          collapsed ? 'lg:justify-center lg:gap-1 lg:px-0' : ''
        }`}
      >
        <NavLink
          to="/"
          className={`flex min-w-0 items-center gap-3 ${collapsed ? 'lg:gap-0' : ''}`}
        >
          {logoUrl || loginLogoUrl ? (
            <img
              src={logoUrl || loginLogoUrl || ''}
              alt={branding.company_name}
              className={`max-h-12 max-w-[180px] shrink-0 object-contain transition-all duration-200 hover:scale-105 ${
                collapsed ? 'lg:max-h-10' : ''
              }`}
            />
          ) : (
            <span
              className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-black text-white transition-all duration-200 hover:scale-105 ${
                collapsed ? 'lg:h-10 lg:w-10 lg:text-sm' : ''
              }`}
            >
              {initials}
            </span>
          )}
          <span className={`truncate text-base font-black text-white ${labelFade}`}>
            {branding.company_name}
          </span>
        </NavLink>

        {/* Hamburguesa movil: abre/cierra el sidebar como overlay */}
        <button
          ref={trigger}
          onClick={() => setSidebarOpen(!sidebarOpen)}
          aria-controls="sidebar"
          aria-expanded={sidebarOpen}
          aria-label={sidebarOpen ? 'Cerrar menú' : 'Abrir menú'}
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-bodydark1 transition-colors duration-200 hover:bg-white/10 hover:text-white lg:hidden"
        >
          <FiMenu size={20} />
        </button>

        {/* Fijar sidebar: si esta fijado, se queda desplegado aunque el
            mouse salga. Si no, el menu se despliega solo al pasar el mouse.
            Solo tiene sentido mostrarlo cuando el menu ya esta desplegado
            (por hover o fijado) — con el menu cerrado (solo iconos) se oculta. */}
        <button
          type="button"
          onClick={() => setPinned((current) => !current)}
          title={pinned ? 'Dejar de fijar el menú' : 'Fijar el menú desplegado'}
          aria-label={pinned ? 'Dejar de fijar el menú' : 'Fijar el menú desplegado'}
          aria-pressed={pinned}
          className={`hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-all duration-200 hover:scale-105 hover:bg-white/10 ${
            collapsed ? 'lg:hidden' : 'lg:flex'
          } ${pinned ? 'text-primary' : 'text-bodydark1 hover:text-white'}`}
        >
          {pinned ? <BsPinFill size={16} /> : <BsPinAngle size={16} />}
        </button>
      </div>
      {/* <!-- SIDEBAR HEADER --> */}

      <div className="no-scrollbar flex flex-col overflow-y-auto duration-200 ease-out">
        <nav
          className={`mt-5 px-4 py-4 transition-[margin] duration-150 ease-out lg:mt-9 ${
            collapsed ? 'lg:px-2' : 'lg:px-6'
          } ${collapsed ? 'lg:mt-3' : ''}`}
        >
          {visibleSections.map((section) => (
            <div
              key={section.title}
              className={`mb-6 transition-[margin] duration-150 ease-out ${collapsed ? 'lg:mb-2' : ''}`}
            >
              <h3 className={sectionTitleFade}>{section.title.toUpperCase()}</h3>

              <ul className="flex flex-col gap-1.5">
                {section.items.map((item) =>
                  item.kind === 'link' ? (
                    <li key={item.to}>
                      <NavLink
                        to={item.to}
                        end={item.end}
                        className={({ isActive }) =>
                          `group/icon relative flex items-center gap-2.5 rounded-md px-2.5 py-2 font-medium text-bodydark1 duration-200 ease-in-out hover:bg-white/[0.06] hover:text-white ${
                            collapsed ? 'lg:justify-center lg:gap-0 lg:px-0' : ''
                          } ${isActive ? 'bg-primary/15 text-white' : ''}`
                        }
                      >
                        {({ isActive }) => (
                          <>
                            {isActive && (
                              <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-primary" />
                            )}
                            <IconBadge icon={item.icon} color={item.color} />
                            <span className={labelFade}>{item.label}</span>
                          </>
                        )}
                      </NavLink>
                    </li>
                  ) : (
                    <li key={item.label}>
                      <button
                        type="button"
                        onClick={() => toggleGroup(item.label)}
                        className={`group/icon relative flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 font-medium text-bodydark1 duration-200 ease-in-out hover:bg-white/[0.06] hover:text-white ${
                          collapsed ? 'lg:justify-center lg:gap-0 lg:px-0' : ''
                        } ${groupMatchesPath(item, pathname) ? 'bg-primary/15 text-white' : ''}`}
                      >
                        {groupMatchesPath(item, pathname) && (
                          <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-primary" />
                        )}
                        <IconBadge icon={item.icon} color={item.color} />
                        <span className={`flex-1 text-left ${labelFade}`}>{item.label}</span>
                        <FiChevronDown
                          className={`h-4 w-4 shrink-0 transition-[transform,opacity] duration-200 ${
                            openGroup === item.label ? 'rotate-180' : ''
                          } ${labelFade}`}
                        />
                      </button>

                      {/* Acordeon — animado con grid-template-rows. Solo se
                          muestra cuando el sidebar esta desplegado (fijado o
                          en hover), ya que en modo compacto no hay espacio. */}
                      <div
                        className={`grid transition-[grid-template-rows,opacity] duration-200 ease-out ${
                          !collapsed && openGroup === item.label
                            ? 'grid-rows-[1fr] opacity-100'
                            : 'grid-rows-[0fr] opacity-0'
                        }`}
                      >
                        <ul className="mb-1 mt-1 flex min-h-0 flex-col gap-1 overflow-hidden pl-[3.75rem]">
                          {item.children.map((child) => (
                            <li key={child.to}>
                              <NavLink
                                to={child.to}
                                end
                                className={({ isActive }) =>
                                  'block rounded-md px-3 py-1.5 text-sm font-medium text-bodydark2 duration-200 ease-in-out hover:text-white ' +
                                  (isActive ? '!text-white' : '')
                                }
                              >
                                {child.label}
                              </NavLink>
                            </li>
                          ))}
                        </ul>
                      </div>
                    </li>
                  ),
                )}
              </ul>
            </div>
          ))}
        </nav>
      </div>
    </aside>
  );
};

export default Sidebar;
