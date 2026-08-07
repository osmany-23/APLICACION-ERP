export type Customer = {
  id: number;
  code: string;
  full_name: string;
  business_name: string | null;
  tax_id: string | null;
  tax_id_type: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  contact_person: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  payment_term_id: number | null;
  currency_id: number | null;
  price_list_id: number | null;
  credit_limit: number;
  credit_days: number | null;
  current_balance: number;
  credit_available: number;
  discount_rate: number;
  birthday: string | null;
  salesperson_id: number | null;
  rating: number | null;
  notes: string | null;
  status: number;
  status_label: string;
  sales_count: number;
};

export type CustomerListResponse = {
  data: Customer[];
};

export type CustomerSaveResponse = {
  message?: string;
  item: Customer;
};

export type CustomerDeleteResponse = {
  message: string;
  deleted: boolean;
};
