export type SaleStatus = 'DRAFT' | 'PENDING' | 'COMPLETED' | 'CANCELLED';

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
  quantity: number;
  unit_price: number;
  unit_cost: number;
  discount: number;
  tax: number;
  subtotal: number;
  total: number;
  taxes: SaleItemTax[];
};

export type Sale = {
  id: number;
  sale_number: string;
  sale_date: string | null;
  status: SaleStatus;
  customer_id: number;
  customer_name: string | null;
  customer_code: string | null;
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  is_credit: boolean;
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

export type SaleItemDraft = {
  key: string;
  product_id: number | null;
  product_name: string;
  quantity: number;
  unit_price: number;
  discount: number;
  tax_type: string;
  tax_percentage: number;
  stock: number;
};
