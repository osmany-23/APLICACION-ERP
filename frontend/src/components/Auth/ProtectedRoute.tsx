import { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import Loader from '../../common/Loader';
import { useAuth } from '../../context/AuthContext';

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const location = useLocation();
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return <Loader />;
  }

  if (!isAuthenticated) {
    return (
      <Navigate to="/auth/signin" replace state={{ from: location.pathname }} />
    );
  }

  return <>{children}</>;
}

export function GuestRoute({ children }: { children: ReactNode }) {
  const location = useLocation();
  const { isAuthenticated, loading } = useAuth();
  const state = location.state as { from?: string } | null;

  if (loading) {
    return <Loader />;
  }

  if (isAuthenticated) {
    return <Navigate to={state?.from || '/'} replace />;
  }

  return <>{children}</>;
}
