import { useEffect, useState } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';

import Loader from './common/Loader';
import { GuestRoute, ProtectedRoute } from './components/Auth/ProtectedRoute';
import PageTitle from './components/PageTitle';
import DefaultLayout from './layout/DefaultLayout';
import SignIn from './pages/Authentication/SignIn';
import Calendar from './pages/Calendar';
import Chart from './pages/Chart';
import ECommerce from './pages/Dashboard/ECommerce';
import FormElements from './pages/Form/FormElements';
import FormLayout from './pages/Form/FormLayout';
import Profile from './pages/Profile';
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
