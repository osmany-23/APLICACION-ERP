export type DashboardTrends = {
  labels: string[];
  sales: number[];
  purchases: number[];
  gross_profit: number[];
  cost: number[];
  margin_percent: number[];
  cash_flow: number[];
};

export type DashboardRankingItem = { label: string; value: number };

export type DashboardLowStockItem = { label: string; value: number; minimum: number };

export type DashboardStagnantItem = { label: string; value: number | null; never_sold: boolean };

export type DashboardAnalytics = {
  period: {
    key: string;
    label: string;
    start: string;
    end: string;
    granularity: 'day' | 'month';
  };
  trends: DashboardTrends;
  sales_by_category: DashboardRankingItem[];
  sales_by_branch: DashboardRankingItem[];
  sales_by_seller: DashboardRankingItem[];
  top_products: DashboardRankingItem[];
  top_customers: DashboardRankingItem[];
  payment_methods: DashboardRankingItem[];
  receivables_status: DashboardRankingItem[];
  low_stock_products: DashboardLowStockItem[];
  stagnant_products: DashboardStagnantItem[];
  profitability: {
    by_category: DashboardRankingItem[];
    by_product: DashboardRankingItem[];
    most_profitable: DashboardRankingItem[];
  };
  comparison?: {
    period: { label: string; start: string; end: string };
    trends: DashboardTrends;
  };
};
