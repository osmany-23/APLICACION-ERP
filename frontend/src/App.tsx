import { Suspense, lazy, useEffect } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';

import Loader from './common/Loader';
import PageLoader from './common/PageLoader';
import { GuestRoute, ProtectedRoute } from './components/Auth/ProtectedRoute';
import { RequirePermission } from './components/Auth/RequirePermission';
import PageTitle from './components/PageTitle';
import useColorMode from './hooks/useColorMode';
import DefaultLayout from './layout/DefaultLayout';

// Cada pagina se carga en su propio chunk de JS, descargado solo cuando el
// usuario navega a esa ruta (en vez de meter las ~40 paginas del sistema en
// un solo bundle inicial de >1.5MB). El Suspense de mas abajo muestra un
// loader mientras el chunk llega.
const SignIn = lazy(() => import('./pages/Authentication/SignIn'));
const Calendar = lazy(() => import('./pages/Calendar'));
const Chart = lazy(() => import('./pages/Chart'));
const ECommerce = lazy(() => import('./pages/Dashboard/ECommerce'));
const FormElements = lazy(() => import('./pages/Form/FormElements'));
const FormLayout = lazy(() => import('./pages/Form/FormLayout'));
const CurrenciesPage = lazy(() => import('./pages/CurrenciesPage'));
const CashDesk = lazy(() => import('./pages/CashRegister/CashDesk'));
const CashMonitorPage = lazy(() => import('./pages/CashRegister/CashMonitorPage'));
const CashMovementReasonsPage = lazy(() => import('./pages/CashRegister/CashMovementReasonsPage'));
const CashRegistersPage = lazy(() => import('./pages/CashRegister/CashRegistersPage'));
const CashSessionDetail = lazy(() => import('./pages/CashRegister/CashSessionDetail'));
const TerminalsPage = lazy(() => import('./pages/CashRegister/TerminalsPage'));
const CustomersPage = lazy(() => import('./pages/Customers'));
const PosTerminal = lazy(() => import('./pages/Pos/PosTerminal'));
const DocumentTypesPage = lazy(() => import('./pages/DocumentTypesPage'));
const PaymentMethodsPage = lazy(() => import('./pages/PaymentMethodsPage'));
const PaymentTermsPage = lazy(() => import('./pages/PaymentTermsPage'));
const PriceTypesPage = lazy(() => import('./pages/PriceTypesPage'));
const ProductCatalogs = lazy(() => import('./pages/ProductCatalogs'));
const Products = lazy(() => import('./pages/Products'));
const ProductDetail = lazy(() => import('./pages/ProductDetail'));
const Profile = lazy(() => import('./pages/Profile'));
const PurchasesPage = lazy(() => import('./pages/Purchases'));
const PurchaseForm = lazy(() => import('./pages/Purchases/PurchaseForm'));
const PurchaseDetail = lazy(() => import('./pages/Purchases/PurchaseDetail'));
const RelationCatalogs = lazy(() => import('./pages/RelationCatalogs'));
const UsersPage = lazy(() => import('./pages/Administration/Users'));
const RolesPage = lazy(() => import('./pages/Administration/Roles'));
const PermissionsPage = lazy(() => import('./pages/Administration/Permissions'));
const EmployeesPage = lazy(() => import('./pages/Administration/Employees'));
const DepartmentsPage = lazy(() => import('./pages/Administration/Departments'));
const PositionsPage = lazy(() => import('./pages/Administration/Positions'));
const SalesPage = lazy(() => import('./pages/Sales'));
const SaleForm = lazy(() => import('./pages/Sales/SaleForm'));
const SaleDetail = lazy(() => import('./pages/Sales/SaleDetail'));
const Settings = lazy(() => import('./pages/Settings'));
const Tables = lazy(() => import('./pages/Tables'));
const Alerts = lazy(() => import('./pages/UiElements/Alerts'));
const Buttons = lazy(() => import('./pages/UiElements/Buttons'));

function ProtectedAppRoutes() {
  return (
    <ProtectedRoute>
      <DefaultLayout>
        {/* Boundary propio (ademas del de App()): el sidebar/header de
            DefaultLayout ya estan montados, asi que al navegar entre
            paginas del panel solo el area de contenido muestra el loader
            liviano, en vez de que toda la pantalla parpadee de nuevo. */}
        <Suspense fallback={<PageLoader />}>
        <Routes>
          <Route
            index
            element={
              <>
                <PageTitle title="Dashboard | Sistema ERP" />
                <ECommerce />
              </>
            }
          />
          <Route
            path="/calendar"
            element={
              <>
                <PageTitle title="Calendario | Sistema ERP" />
                <Calendar />
              </>
            }
          />
          <Route
            path="/products"
            element={
              <>
                <PageTitle title="Productos | Sistema ERP" />
                <Products />
              </>
            }
          />
          <Route
            path="/products/detail/:id"
            element={
              <>
                <PageTitle title="Detalle de producto | Sistema ERP" />
                <ProductDetail />
              </>
            }
          />
          <Route
            path="/products/brands"
            element={
              <>
                <PageTitle title="Marcas | Sistema ERP" />
                <ProductCatalogs catalog="brands" />
              </>
            }
          />
          <Route
            path="/products/categories"
            element={
              <>
                <PageTitle title="Categorias | Sistema ERP" />
                <ProductCatalogs catalog="categories" />
              </>
            }
          />
          <Route
            path="/products/subcategories"
            element={
              <>
                <PageTitle title="Subcategorias | Sistema ERP" />
                <ProductCatalogs catalog="subcategories" />
              </>
            }
          />
          <Route
            path="/products/units"
            element={
              <>
                <PageTitle title="Unidades | Sistema ERP" />
                <ProductCatalogs catalog="units" />
              </>
            }
          />
          <Route
            path="/customers"
            element={
              <>
                <PageTitle title="Clientes | Sistema ERP" />
                <CustomersPage />
              </>
            }
          />
          <Route
            path="/sales"
            element={
              <>
                <PageTitle title="Facturacion | Sistema ERP" />
                <SalesPage />
              </>
            }
          />
          <Route
            path="/sales/new"
            element={
              <>
                <PageTitle title="Nueva Factura | Sistema ERP" />
                <SaleForm />
              </>
            }
          />
          <Route
            path="/sales/:id"
            element={
              <>
                <PageTitle title="Detalle de Factura | Sistema ERP" />
                <SaleDetail />
              </>
            }
          />
          <Route
            path="/purchases"
            element={
              <>
                <PageTitle title="Compras | Sistema ERP" />
                <PurchasesPage />
              </>
            }
          />
          <Route
            path="/purchases/new"
            element={
              <>
                <PageTitle title="Nueva Compra | Sistema ERP" />
                <PurchaseForm />
              </>
            }
          />
          <Route
            path="/purchases/:id"
            element={
              <>
                <PageTitle title="Detalle de Compra | Sistema ERP" />
                <PurchaseDetail />
              </>
            }
          />
          <Route
            path="/relations/suppliers"
            element={
              <>
                <PageTitle title="Proveedores | Sistema ERP" />
                <RelationCatalogs relation="suppliers" />
              </>
            }
          />
          <Route
            path="/relations/warehouses"
            element={
              <>
                <PageTitle title="Almacenes | Sistema ERP" />
                <RelationCatalogs relation="warehouses" />
              </>
            }
          />
          <Route
            path="/relations/branches"
            element={
              <>
                <PageTitle title="Sucursales | Sistema ERP" />
                <RelationCatalogs relation="branches" />
              </>
            }
          />
          <Route
            path="/relations/company"
            element={
              <>
                <PageTitle title="Empresa | Sistema ERP" />
                <RelationCatalogs relation="companies" />
              </>
            }
          />
          <Route
            path="/administration/users"
            element={
              <RequirePermission module="usuarios">
                <PageTitle title="Usuarios | Sistema ERP" />
                <UsersPage />
              </RequirePermission>
            }
          />
          <Route
            path="/administration/roles"
            element={
              <RequirePermission module="roles">
                <PageTitle title="Roles | Sistema ERP" />
                <RolesPage />
              </RequirePermission>
            }
          />
          <Route
            path="/administration/permissions"
            element={
              <RequirePermission module="permisos">
                <PageTitle title="Permisos | Sistema ERP" />
                <PermissionsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/administration/employees"
            element={
              <RequirePermission module="empleados">
                <PageTitle title="Empleados | Sistema ERP" />
                <EmployeesPage />
              </RequirePermission>
            }
          />
          <Route
            path="/administration/departments"
            element={
              <RequirePermission module="empleados">
                <PageTitle title="Departamentos | Sistema ERP" />
                <DepartmentsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/administration/positions"
            element={
              <RequirePermission module="empleados">
                <PageTitle title="Cargos | Sistema ERP" />
                <PositionsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/cash-register/desk"
            element={
              <RequirePermission module="cajas">
                <PageTitle title="Mi Caja | Sistema ERP" />
                <CashDesk />
              </RequirePermission>
            }
          />
          <Route
            path="/cash-register/monitor"
            element={
              <RequirePermission module="cajas">
                <PageTitle title="Monitor de Cajas | Sistema ERP" />
                <CashMonitorPage />
              </RequirePermission>
            }
          />
          <Route
            path="/cash-register/registers"
            element={
              <RequirePermission module="cajas">
                <PageTitle title="Cajas | Sistema ERP" />
                <CashRegistersPage />
              </RequirePermission>
            }
          />
          <Route
            path="/cash-register/terminals"
            element={
              <RequirePermission module="cajas">
                <PageTitle title="Terminales | Sistema ERP" />
                <TerminalsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/cash-register/reasons"
            element={
              <RequirePermission module="cajas">
                <PageTitle title="Motivos de Movimiento | Sistema ERP" />
                <CashMovementReasonsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/cash-register/sessions/:id"
            element={
              <RequirePermission module="cajas">
                <PageTitle title="Detalle de Apertura | Sistema ERP" />
                <CashSessionDetail />
              </RequirePermission>
            }
          />
          <Route
            path="/settings/currencies"
            element={
              <>
                <PageTitle title="Monedas | Sistema ERP" />
                <CurrenciesPage />
              </>
            }
          />
          <Route
            path="/settings/payment-terms"
            element={
              <>
                <PageTitle title="Términos de Pago | Sistema ERP" />
                <PaymentTermsPage />
              </>
            }
          />
          <Route
            path="/settings/payment-methods"
            element={
              <>
                <PageTitle title="Métodos de Pago | Sistema ERP" />
                <PaymentMethodsPage />
              </>
            }
          />
          <Route
            path="/settings/document-types"
            element={
              <>
                <PageTitle title="Tipos de Documento | Sistema ERP" />
                <DocumentTypesPage />
              </>
            }
          />
          <Route
            path="/settings/price-types"
            element={
              <>
                <PageTitle title="Tipos de Precio | Sistema ERP" />
                <PriceTypesPage />
              </>
            }
          />
          <Route
            path="/profile"
            element={
              <>
                <PageTitle title="Perfil | Sistema ERP" />
                <Profile />
              </>
            }
          />
          <Route
            path="/forms/form-elements"
            element={
              <>
                <PageTitle title="Elementos de Formulario | Sistema ERP" />
                <FormElements />
              </>
            }
          />
          <Route
            path="/forms/form-layout"
            element={
              <>
                <PageTitle title="Diseno de Formulario | Sistema ERP" />
                <FormLayout />
              </>
            }
          />
          <Route
            path="/tables"
            element={
              <>
                <PageTitle title="Tablas | Sistema ERP" />
                <Tables />
              </>
            }
          />
          <Route
            path="/settings"
            element={
              <>
                <PageTitle title="Configuracion | Sistema ERP" />
                <Settings />
              </>
            }
          />
          <Route
            path="/chart"
            element={
              <>
                <PageTitle title="Graficas | Sistema ERP" />
                <Chart />
              </>
            }
          />
          <Route
            path="/ui/alerts"
            element={
              <>
                <PageTitle title="Alertas | Sistema ERP" />
                <Alerts />
              </>
            }
          />
          <Route
            path="/ui/buttons"
            element={
              <>
                <PageTitle title="Botones | Sistema ERP" />
                <Buttons />
              </>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        </Suspense>
      </DefaultLayout>
    </ProtectedRoute>
  );
}

function App() {
  const { pathname } = useLocation();
  // Sincroniza la clase `dark` en <body> desde el primer render, sin
  // importar por que ruta entra el usuario (antes solo se aplicaba si
  // el switcher del Header llegaba a montarse, dejando /auth/signin sin
  // tema oscuro en una carga directa/fria).
  useColorMode();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pathname]);

  return (
    <Suspense fallback={<Loader />}>
      <Routes>
        <Route
          path="/auth/signin"
          element={
            <GuestRoute>
              <PageTitle title="Login | Sistema ERP" />
              <SignIn />
            </GuestRoute>
          }
        />
        <Route path="/auth/signup" element={<Navigate to="/auth/signin" replace />} />
        {/* TPV: pantalla completa, sin sidebar/header del sistema (fuera de DefaultLayout a proposito). */}
        <Route
          path="/pos"
          element={
            <ProtectedRoute>
              <PageTitle title="TPV | Sistema ERP" />
              <PosTerminal />
            </ProtectedRoute>
          }
        />
        <Route path="/*" element={<ProtectedAppRoutes />} />
      </Routes>
    </Suspense>
  );
}

export default App;
