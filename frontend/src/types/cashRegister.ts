export type CashRegisterStatus = 'ACTIVA' | 'INACTIVA' | 'MANTENIMIENTO';
export type CashSessionStatus = 'ABIERTA' | 'EN_ARQUEO' | 'CERRADA' | 'CANCELADA';
export type CashMovementType = 'INGRESO' | 'EGRESO';
export type CashMovementStatus = 'ACTIVO' | 'ANULADO' | 'PENDIENTE_AUTORIZACION' | 'RECHAZADO';

export type CashBreakdown = Record<string, number>;

export type CashRegister = {
  id: number;
  code: string;
  name: string;
  description: string | null;
  branch_id: number;
  branch_name: string | null;
  default_currency_id: number;
  currency_code: string | null;
  status: CashRegisterStatus;
  authorization_threshold: number | null;
  authorized_users: { id: number; full_name: string }[];
  authorized_user_ids: number[];
  can_operate: boolean;
  is_open: boolean;
  open_session_id: number | null;
};

export type CashRegisterListResponse = { data: CashRegister[] };
export type CashRegisterSaveResponse = { message?: string; item: CashRegister };

export type Terminal = {
  id: number;
  code: string;
  name: string | null;
  device_name: string | null;
  branch_id: number | null;
  branch_name: string | null;
  cash_register_id: number | null;
  cash_register_name: string | null;
  ip_address: string | null;
  os_info: string | null;
  browser_info: string | null;
  status: 'ACTIVA' | 'INACTIVA';
  last_seen_at: string | null;
  current_user: { id: number; full_name: string } | null;
};

export type TerminalListResponse = { data: Terminal[] };

export type CashMovementReason = {
  id: number;
  company_id: number | null;
  type: CashMovementType;
  name: string;
  requires_note: boolean;
  is_system: boolean;
  is_active: boolean;
  sort_order: number;
};

export type CashMovementReasonListResponse = { data: CashMovementReason[] };

export type CashSessionSummary = {
  opening_amount: number;
  cash_sales: number;
  card_sales: number;
  transfer_sales: number;
  other_sales: number;
  credit_sales: number;
  credit_collections: number;
  net_sales: number;
  discounts: number;
  taxes: number;
  returns: number;
  cost_of_goods: number;
  gross_profit: number;
  net_profit: number;
  manual_income: number;
  manual_expense: number;
  pending_authorization_count: number;
  balance_actual: number;
  sales_count: number;
};

export type CashSessionRef = { id: number; name: string; code?: string } | null;

export type CashSession = {
  id: number;
  status: CashSessionStatus;
  cash_register: CashSessionRef;
  branch: { id: number; name: string } | null;
  terminal: { id: number; name: string } | null;
  opened_by: { id: number; full_name: string } | null;
  closed_by: { id: number; full_name: string } | null;
  opening_amount: number;
  opened_at: string | null;
  closed_at: string | null;
  expected_cash: number | null;
  counted_cash: number | null;
  cash_difference: number | null;
  opening_note?: string | null;
  opening_breakdown?: CashBreakdown | null;
  closing_note?: string | null;
  closing_breakdown?: CashBreakdown | null;
  currency_id?: number;
  summary?: CashSessionSummary;
};

export type CashSessionResponse = { message?: string; item: CashSession | null };
export type CashSessionListResponse = { data: CashSession[] };
export type CashSessionSummaryResponse = { item: CashSessionSummary };

export type CashMovement = {
  id: number;
  cash_session_id: number;
  type: CashMovementType;
  amount: number;
  reason_id: number | null;
  reason_name: string | null;
  reason_text: string | null;
  reference: string | null;
  observation: string | null;
  status: CashMovementStatus;
  requires_authorization: boolean;
  user: { id: number; full_name: string } | null;
  authorized_by: { id: number; full_name: string } | null;
  authorized_at: string | null;
  cancelled_by: { id: number; full_name: string } | null;
  cancelled_at: string | null;
  cancellation_reason: string | null;
  created_at: string | null;
};

export type CashMovementListResponse = { data: CashMovement[] };
export type CashMovementResponse = { message?: string; item: CashMovement };

export type CashMonitorRow = {
  id: number;
  status: CashSessionStatus;
  cash_register: { id: number; name: string; code: string } | null;
  branch: { id: number; name: string } | null;
  opened_by: { id: number; full_name: string } | null;
  terminal: { id: number; name: string } | null;
  opened_at: string | null;
  closed_at: string | null;
  opening_amount: number;
  balance_actual: number;
  net_sales: number;
  cash_difference: number | null;
};

export type CashMonitorResponse = {
  sessions: CashMonitorRow[];
  totals: {
    sessions_count?: number;
    open_count: number;
    total_cash: number;
    total_opening: number;
    total_sales: number;
  };
};
