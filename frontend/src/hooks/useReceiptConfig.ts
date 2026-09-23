import { useEffect, useState } from 'react';
import { apiRequest } from '../services/api';
import { useBranding } from '../context/BrandingContext';
import { ReceiptConfig } from '../types/sale';

type ReceiptConfigResponse = { data: ReceiptConfig };

/**
 * Configuracion del ticket (Configuracion > Operaciones > Configuracion de
 * Recibos) mas el logo publico de la empresa (useBranding(), que ya esta
 * cargado en toda la app y no exige el permiso de facturacion que si exige
 * GET /sales/receipt-config). La usan ReceiptModal.tsx (POS) y
 * SaleDetail.tsx (reimpresion desde Ventas) para no duplicar este fetch.
 */
export function useReceiptConfig(token?: string | null) {
  const { branding } = useBranding();
  const [config, setConfig] = useState<ReceiptConfig | null>(null);

  useEffect(() => {
    if (!token) return;

    let cancelled = false;

    apiRequest<ReceiptConfigResponse>('/sales/receipt-config', {}, token)
      .then((response) => {
        if (!cancelled) setConfig(response.data);
      })
      .catch(() => {
        if (!cancelled) {
          setConfig({
            company_name: null,
            company_tax_id: null,
            company_phone: null,
            company_address: null,
            show_logo: true,
            display_name: null,
            footer_note: '',
            claim_days: 0,
          });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [token]);

  if (!config) return null;

  return {
    ...config,
    display_name: config.display_name || branding.commercial_name,
    logo_url: branding.logo_url ?? null,
    // Mismo tamano configurable en Configuracion > Empresa > Identidad
    // visual que ya usa el logo del login (branding.logo_height).
    logo_height: branding.logo_height,
  };
}
