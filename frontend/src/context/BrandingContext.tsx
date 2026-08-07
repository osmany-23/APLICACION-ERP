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

export type Branding = {
  company_name: string;
  commercial_name?: string | null;
  slogan?: string | null;
  welcome_text: string;
  logo_url?: string | null;
  logo_dark_url?: string | null;
  favicon_url?: string | null;
  notifications_enabled?: boolean;
};

type BrandingResponse = {
  data: Branding;
};

type BrandingContextValue = {
  branding: Branding;
  loading: boolean;
  initials: string;
  logoUrl: string | null;
  loginLogoUrl: string | null;
  refreshBranding: () => Promise<void>;
  updateBranding: (branding: Branding) => void;
};

const defaultBranding: Branding = {
  company_name: 'Sistema ERP',
  commercial_name: 'Sistema ERP',
  slogan: null,
  welcome_text: 'Bienvenido',
  logo_url: null,
  logo_dark_url: null,
  favicon_url: null,
  notifications_enabled: true,
};

const BrandingContext = createContext<BrandingContextValue | undefined>(
  undefined,
);

function normalizeBranding(branding?: Partial<Branding> | null): Branding {
  const companyName = (branding?.company_name || '').trim();

  return {
    ...defaultBranding,
    ...branding,
    company_name: companyName || defaultBranding.company_name,
    commercial_name:
      branding?.commercial_name || companyName || defaultBranding.company_name,
    welcome_text:
      branding?.welcome_text ||
      (companyName ? `Bienvenido a ${companyName}` : defaultBranding.welcome_text),
    notifications_enabled: branding?.notifications_enabled !== false,
  };
}

function initialsFrom(name: string): string {
  const parts = name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2);

  if (parts.length === 0) {
    return 'ERP';
  }

  return parts.map((part) => part[0]?.toUpperCase()).join('');
}

function setFavicon(href?: string | null) {
  if (!href) {
    return;
  }

  let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]');

  if (!link) {
    link = document.createElement('link');
    link.rel = 'icon';
    document.head.appendChild(link);
  }

  link.href = href;
}

export function BrandingProvider({ children }: { children: ReactNode }) {
  const [branding, setBranding] = useState<Branding>(defaultBranding);
  const [loading, setLoading] = useState(true);

  const updateBranding = useCallback((nextBranding: Branding) => {
    setBranding(normalizeBranding(nextBranding));
  }, []);

  const refreshBranding = useCallback(async () => {
    setLoading(true);

    try {
      const response = await apiRequest<BrandingResponse>('/public/branding');
      updateBranding(response.data);
    } catch {
      setBranding(defaultBranding);
    } finally {
      setLoading(false);
    }
  }, [updateBranding]);

  useEffect(() => {
    void refreshBranding();
  }, [refreshBranding]);

  useEffect(() => {
    setFavicon(branding.favicon_url);
  }, [branding.favicon_url]);

  const value = useMemo<BrandingContextValue>(
    () => ({
      branding,
      loading,
      initials: initialsFrom(branding.company_name),
      logoUrl: branding.logo_url || null,
      loginLogoUrl: branding.logo_dark_url || branding.logo_url || null,
      refreshBranding,
      updateBranding,
    }),
    [branding, loading, refreshBranding, updateBranding],
  );

  return (
    <BrandingContext.Provider value={value}>
      {children}
    </BrandingContext.Provider>
  );
}

export function useBranding() {
  const context = useContext(BrandingContext);

  if (!context) {
    throw new Error('useBranding debe usarse dentro de BrandingProvider.');
  }

  return context;
}
