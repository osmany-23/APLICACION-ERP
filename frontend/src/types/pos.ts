import { PriceTier } from '../utils/pricing';

export type { PriceTier };

export type PosProduct = {
  id: number;
  code: string;
  barcode?: string | null;
  name: string;
  description?: string | null;
  category_id: number | null;
  category: string;
  brand_id: number | null;
  brand: string;
  unit?: string;
  sale_price: number;
  price_tiers: PriceTier[];
  tax_type: string;
  tax_percentage: number;
  stock: number;
  warehouse?: string | null;
  branch?: string | null;
  physical_location?: string | null;
  image_url: string | null;
  allow_sale: boolean;
  status: number;
  // Un combo/kit no tiene existencia propia (stock siempre 0): su
  // disponibilidad real depende de sus componentes y se valida en el
  // servidor al confirmar la venta, no aqui en el POS.
  is_kit: boolean;
};

export type PosCartLine = {
  key: string;
  product_id: number;
  code: string;
  name: string;
  image_url: string | null;
  unit_price: number;
  sale_price: number;
  price_tiers: PriceTier[];
  quantity: number;
  stock: number;
  tax_type: string;
  tax_percentage: number;
  is_kit: boolean;
};

export type PosCatalogOption = { id: number; name: string };
export type PosWarehouse = { id: number; name: string };
export type PosPaymentMethod = {
  id: number;
  name: string;
  cash: boolean;
  card: boolean;
  bank: boolean;
  allow_change: boolean;
  requires_reference: boolean;
};
export type PosPaymentTerm = { id: number; name: string; days: number };
export type PosDocumentType = { id: number; code: string; name: string };

export type PosExchangeRate = {
  rate: number;
  date: string;
  base_currency: { id: number; code: string; symbol: string };
  foreign_currency: { id: number; code: string; symbol: string };
};

export type HeldCart = {
  id: string;
  savedAt: string;
  label: string;
  customerId: string;
  documentTypeId: string;
  items: PosCartLine[];
  discountType: 'fixed' | 'percentage';
  discountValue: string;
  shipping: string;
  notes: string;
};
