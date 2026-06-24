import {
  ReactNode,
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { apiRequest } from '../services/api';
import {
  AuthSession,
  AuthUser,
  LoginCredentials,
  LoginResponse,
  MeResponse,
} from '../types/auth';

type AuthContextValue = {
  user: AuthUser | null;
  token: string | null;
  loading: boolean;
  isAuthenticated: boolean;
  login: (credentials: LoginCredentials) => Promise<LoginResponse>;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
};

const STORAGE_KEY = 'erp_auth_session';

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

function readStoredSession(): AuthSession | null {
  const rawSession = localStorage.getItem(STORAGE_KEY);

  if (!rawSession) {
    return null;
  }

  try {
    const session = JSON.parse(rawSession) as AuthSession;
    const hasExpired = new Date(session.expiresAt).getTime() <= Date.now();

    if (!session.token || !session.user || hasExpired) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }

    return session;
  } catch {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<AuthSession | null>(readStoredSession);
  const [loading, setLoading] = useState(true);

  const clearSession = useCallback(() => {
    localStorage.removeItem(STORAGE_KEY);
    setSession(null);
  }, []);

  const persistSession = useCallback((nextSession: AuthSession) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(nextSession));
    setSession(nextSession);
  }, []);

  const refreshProfile = useCallback(async () => {
    const currentSession = readStoredSession();

    if (!currentSession) {
      clearSession();
      return;
    }

    const response = await apiRequest<MeResponse>(
      '/auth/me',
      {},
      currentSession.token,
    );

    persistSession({
      ...currentSession,
      user: response.user,
    });
  }, [clearSession, persistSession]);

  const login = useCallback(
    async (credentials: LoginCredentials) => {
      const response = await apiRequest<LoginResponse>('/auth/login', {
        method: 'POST',
        body: JSON.stringify(credentials),
      });

      persistSession({
        token: response.token,
        tokenType: response.token_type,
        expiresAt: response.expires_at,
        user: response.user,
      });

      return response;
    },
    [persistSession],
  );

  const logout = useCallback(async () => {
    const currentToken = session?.token;

    if (currentToken) {
      await apiRequest('/auth/logout', { method: 'POST' }, currentToken).catch(
        () => undefined,
      );
    }

    clearSession();
  }, [clearSession, session?.token]);

  useEffect(() => {
    async function bootSession() {
      const currentSession = readStoredSession();

      if (!currentSession) {
        clearSession();
        setLoading(false);
        return;
      }

      try {
        const response = await apiRequest<MeResponse>(
          '/auth/me',
          {},
          currentSession.token,
        );

        persistSession({
          ...currentSession,
          user: response.user,
        });
      } catch {
        clearSession();
      } finally {
        setLoading(false);
      }
    }

    void bootSession();
  }, [clearSession, persistSession]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user: session?.user ?? null,
      token: session?.token ?? null,
      loading,
      isAuthenticated: Boolean(session?.token),
      login,
      logout,
      refreshProfile,
    }),
    [loading, login, logout, refreshProfile, session?.token, session?.user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth debe usarse dentro de AuthProvider.');
  }

  return context;
}
