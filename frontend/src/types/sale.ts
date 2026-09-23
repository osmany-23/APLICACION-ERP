import { PriceTier } from '../utils/pricing';

export type SaleStatus = 'DRAFT' | 'PENDING' | 'COMPLETED' | 'CANCELLED';

// Como quedo la plata de la venta (distinto del workflow status de arriba):
// PENDING_PAYMENT = pendiente de pago / contra entrega, todavia sin
// confirmar. CREDIT = credito real del cliente (cuenta por cobrar). PAID =
// ya cobrada (de contado, o pendiente de pago ya confirmada).
export type TransactionStatus = 'PENDING_PAYMENT' | 'CREDIT' | 'PAID';

export type SaleItemTax = {
  tax_id: number;
  rate: number;
  taxable_base: number;
  tax_amount: number;
};

export type SaleItem = {
  id: number;
  product_id: number;
  product_name: string;
  unit?: string;
  // Snapshot de la garantia del producto tal como estaba al momento de
  // vender (no cambia si despues se edita la garantia del producto en el
  // catalogo) — warranty_expires_at ya viene calculado (sale_date +
  // duracion) desde el backend.
  has_warranty?: boolean;
  warranty_type?: string | null;
  warranty_expires_at?: string | null;
  quantity: number;
  unit_price: number;
  unit_cost: number;
  discount: number;
  tax: number;
  subtotal: number;
  total: number;
  taxes: SaleItemTax[];
};

// Un abono sobre una factura "Pendiente de pago" (ver SalesService::
// registerPendingPayment en el backend) — puede haber varios por factura.
export type SalePaymentSummary = {
  id: number;
  amount: number;
  payment_method_name: string | null;
  reference: string | null;
  created_at: string | null;
  created_by_name: string | null;
};

export type CreditNoteSummary = {
  id: number;
  number: string;
  total: number;
  reason: string | null;
  created_at: string | null;
};

export type DebitNoteSummary = {
  id: number;
  number: string;
  total: number;
  reason: string | null;
  created_at: string | null;
};

export type Sale = {
  id: number;
  sale_number: string;
  sale_date: string | null;
  created_at?: string | null;
  status: SaleStatus;
  customer_id: number;
  customer_name: string | null;
  customer_code: string | null;
  // Solo vienen si el cliente los tiene cargados: un "cliente rapido" creado
  // al vuelo desde el POS normalmente no trae RUC/direccion, un cliente
  // registrado con esos datos si los trae — el ticket (ReceiptTicket) los
  // muestra unicamente cuando no son null/vacios.
  customer_business_name?: string | null;
  customer_tax_id?: string | null;
  customer_phone?: string | null;
  customer_address?: string | null;
  branch?: { id: number; name: string; address: string | null; phone: string | null } | null;
  payment_method_name?: string | null;
  payment_method_flags?: { cash: boolean; card: boolean; bank: boolean } | null;
  payment_reference?: string | null;
  // Un cliente puede pagar con una mezcla de billetes en ambas monedas (ej.
  // un billete de $10 y uno de C$500 en la misma venta): se guardan por
  // separado en vez de forzarlos a una sola moneda "dominante".
  amount_tendered_base?: number | null;
  amount_tendered_foreign?: number | null;
  change_amount?: number | null;
  // Politicas de credito/mora del cliente — solo tienen sentido cuando
  // transaction_status es 'CREDIT' (ver ReceiptTicket.tsx).
  due_date?: string | null;
  customer_credit_days?: number | null;
  customer_applies_late_fee?: boolean;
  customer_late_fee_percentage?: number | null;
  customer_late_fee_period_unit?: 'DAYS' | 'WEEKS' | 'MONTHS' | null;
  subtotal: number;
  discount: number;
  shipping: number;
  tax: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  ir_withholding_rate?: number | null;
  ir_withholding_amount?: number;
  is_credit: boolean;
  transaction_status: TransactionStatus;
  created_by?: number | null;
  created_by_name?: string | null;
  salesperson_id?: number | null;
  salesperson_name?: string | null;
  warehouse_id?: number | null;
  branch_id?: number | null;
  payment_method_id?: number | null;
  payment_term_id?: number | null;
  currency_id?: number | null;
  exchange_rate?: number;
  notes?: string | null;
  accounts_receivable_id?: number | null;
  journal_entry_id?: number | null;
  payment_confirmed_at?: string | null;
  payment_confirmed_by_name?: string | null;
  cancelled_at?: string | null;
  cancelled_by_name?: string | null;
  cancellation_authorized_by_name?: string | null;
  payments?: SalePaymentSummary[];
  credit_notes?: CreditNoteSummary[];
  debit_notes?: DebitNoteSummary[];
  items?: SaleItem[];
};

export type SaleListResponse = {
  data: Sale[];
  meta: {
    total: number;
    total_amount: number;
    balance_due: number;
  };
};

export type SaleSaveResponse = {
  message?: string;
  item: Sale;
};

export type CreditNoteSaveResponse = {
  message?: string;
  item: CreditNoteSummary;
};

export type DebitNoteSaveResponse = {
  message?: string;
  item: DebitNoteSummary;
};

// Lo que necesita ReceiptModal para abrir el ticket justo despues de
// facturar en el POS: solo el id (ReceiptModal carga la venta completa con
// GET /sales/{id}, la misma fuente de verdad que usa SaleDetail al
// reimprimir) mas un par de banderas puramente de UI que no vienen del
// modelo Sale.
export type ReceiptRequest = {
  saleId: number;
  variant: 'sale' | 'proforma';
  isPendingPayment: boolean;
};

// Configuracion del ticket (Configuracion > Operaciones > Configuracion de
// Recibos) — GET /sales/receipt-config.
export type ReceiptConfig = {
  company_name: string | null;
  company_tax_id: string | null;
  company_phone: string | null;
  company_address: string | null;
  show_logo: boolean;
  display_name: string | null;
  footer_note: string;
  claim_days: number;
  // No vienen de GET /sales/receipt-config: quien arma este objeto los
  // completa con datos de branding.logo_url/logo_height (useBranding()),
  // que ya son publicos y no exigen el permiso de facturacion que si exige
  // ese endpoint.
  logo_url?: string | null;
  logo_height?: number;
};

export type SaleItemDraft = {
  key: string;
  product_id: number | null;
  product_name: string;
  quantity: number;
  unit_price: number;
  sale_price: number;
  price_tiers: PriceTier[];
  discount: number;
  tax_type: string;
  tax_percentage: number;
  stock: number;
  // Un combo/kit no lleva stock propio (depende de sus componentes): el
  // aviso de "cantidad mayor al stock disponible" no aplica a estas lineas.
  is_kit: boolean;
};
