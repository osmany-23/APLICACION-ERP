export type ResidencyType = 'NACIONAL' | 'EXTRANJERO';
export type CustomerGender = 'FEMENINO' | 'MASCULINO' | 'EMPRESA' | 'OTRO';
export type SalesType = 'CONTADO' | 'CREDITO';
export type LateFeePeriodUnit = 'DAYS' | 'WEEKS' | 'MONTHS';

export type Customer = {
  id: number;
  code: string;
  full_name: string;
  business_name: string | null;
  tax_id: string | null;
  tax_id_type: string | null;
  residency_type: ResidencyType;
  gender: CustomerGender | null;
  sales_type: SalesType;
  phone: string | null;
  phone_country_id: number | null;
  phone_display: string | null;
  has_landline: boolean;
  landline_phone: string | null;
  email: string | null;
  address: string | null;
  country_id: number | null;
  city: string | null;
  contact_person: string | null;
  contact_phone: string | null;
  contact_email: string | null;
  payment_term_id: number | null;
  currency_id: number | null;
  price_list_id: number | null;
  credit_limit: number;
  credit_days: number | null;
  applies_late_fee: boolean;
  late_fee_percentage: number | null;
  late_fee_period_unit: LateFeePeriodUnit | null;
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
  registered_at: string | null;
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
