export type PurchaseStatus = 'DRAFT' | 'PENDING' | 'RECEIVED' | 'CANCELLED';

export type PurchaseItemTax = {
  tax_id: number;
  rate: number;
  taxable_base: number;
  tax_amount: number;
};

export type PurchaseItem = {
  id: number;
  product_id: number;
  product_name: string;
  quantity: number;
  unit_cost: number;
  discount: number;
  tax: number;
  subtotal: number;
  total: number;
  taxes: PurchaseItemTax[];
};

export type Purchase = {
  id: number;
  purchase_number: string;
  purchase_date: string | null;
  status: PurchaseStatus;
  supplier_id: number;
  supplier_name: string | null;
  supplier_code: string | null;
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
  paid_amount: number;
  balance_due: number;
  is_credit: boolean;
  warehouse_id?: number | null;
  branch_id?: number | null;
  payment_method_id?: number | null;
  payment_term_id?: number | null;
  currency_id?: number | null;
  exchange_rate?: number;
  notes?: string | null;
  accounts_payable_id?: number | null;
  journal_entry_id?: number | null;
  items?: PurchaseItem[];
};

export type PurchaseListResponse = {
  data: Purchase[];
  meta: {
    total: number;
    total_amount: number;
    balance_due: number;
  };
};

export type PurchaseSaveResponse = {
  message?: string;
  item: Purchase;
};

export type PurchaseItemDraft = {
  key: string;
  product_id: number | null;
  product_name: string;
  quantity: number;
  unit_cost: number;
  discount: number;
  tax_type: string;
  tax_percentage: number;
  stock: number;
};
