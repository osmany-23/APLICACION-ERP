import { useEffect, useState } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';

import Loader from './common/Loader';
import { GuestRoute, ProtectedRoute } from './components/Auth/ProtectedRoute';
import { RequirePermission } from './components/Auth/RequirePermission';
import PageTitle from './components/PageTitle';
import useColorMode from './hooks/useColorMode';
import DefaultLayout from './layout/DefaultLayout';
import SignIn from './pages/Authentication/SignIn';
import Calendar from './pages/Calendar';
import Chart from './pages/Chart';
import ECommerce from './pages/Dashboard/ECommerce';
import FormElements from './pages/Form/FormElements';
import FormLayout from './pages/Form/FormLayout';
import CurrenciesPage from './pages/CurrenciesPage';
import CustomersPage from './pages/Customers';
import DocumentTypesPage from './pages/DocumentTypesPage';
import PaymentMethodsPage from './pages/PaymentMethodsPage';
import PaymentTermsPage from './pages/PaymentTermsPage';
import ProductCatalogs from './pages/ProductCatalogs';
import Products from './pages/Products';
import Profile from './pages/Profile';
import PurchasesPage from './pages/Purchases';
import PurchaseForm from './pages/Purchases/PurchaseForm';
import PurchaseDetail from './pages/Purchases/PurchaseDetail';
import RelationCatalogs from './pages/RelationCatalogs';
import UsersPage from './pages/Administration/Users';
import RolesPage from './pages/Administration/Roles';
import PermissionsPage from './pages/Administration/Permissions';
import EmployeesPage from './pages/Administration/Employees';
import DepartmentsPage from './pages/Administration/Departments';
import PositionsPage from './pages/Administration/Positions';
import SalesPage from './pages/Sales';
import SaleForm from './pages/Sales/SaleForm';
import SaleDetail from './pages/Sales/SaleDetail';
import Settings from './pages/Settings';
import Tables from './pages/Tables';
import Alerts from './pages/UiElements/Alerts';
import Buttons from './pages/UiElements/Buttons';

function ProtectedAppRoutes() {
  return (
    <ProtectedRoute>
      <DefaultLayout>
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
      </DefaultLayout>
    </ProtectedRoute>
  );
}

function App() {
  const [loading, setLoading] = useState<boolean>(true);
  const { pathname } = useLocation();
  // Sincroniza la clase `dark` en <body> desde el primer render, sin
  // importar por que ruta entra el usuario (antes solo se aplicaba si
  // el switcher del Header llegaba a montarse, dejando /auth/signin sin
  // tema oscuro en una carga directa/fria).
  useColorMode();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pathname]);

  useEffect(() => {
    const timer = window.setTimeout(() => setLoading(false), 500);

    return () => window.clearTimeout(timer);
  }, []);

  return loading ? (
    <Loader />
  ) : (
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
      <Route path="/*" element={<ProtectedAppRoutes />} />
    </Routes>
  );
}

export default App;
