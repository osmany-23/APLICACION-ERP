import { FiRefreshCw } from 'react-icons/fi';

export type DashboardPeriod = 'today' | '7d' | '30d' | 'month' | 'year';

const OPTIONS: { key: DashboardPeriod; label: string }[] = [
  { key: 'today', label: 'Hoy' },
  { key: '7d', label: 'Últimos 7 días' },
  { key: '30d', label: 'Últimos 30 días' },
  { key: 'month', label: 'Este mes' },
  { key: 'year', label: 'Este año' },
];

export default function PeriodSelector({
  period,
  onPeriodChange,
  compare,
  onCompareChange,
  loading,
}: {
  period: DashboardPeriod;
  onPeriodChange: (period: DashboardPeriod) => void;
  compare: boolean;
  onCompareChange: (compare: boolean) => void;
  loading?: boolean;
}) {
  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="inline-flex flex-wrap items-center gap-1 rounded-lg bg-whiter p-1 dark:bg-meta-4">
        {OPTIONS.map((option) => (
          <button
            key={option.key}
            type="button"
            onClick={() => onPeriodChange(option.key)}
            className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${
              period === option.key
                ? 'bg-white text-primary shadow-card dark:bg-boxdark'
                : 'text-black hover:bg-white/60 dark:text-white dark:hover:bg-boxdark/60'
            }`}
          >
            {option.label}
          </button>
        ))}
      </div>

      <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-stroke px-3 py-1.5 text-xs font-semibold text-black dark:border-strokedark dark:text-white">
        <input
          type="checkbox"
          checked={compare}
          onChange={(event) => onCompareChange(event.target.checked)}
          className="h-3.5 w-3.5 accent-primary"
        />
        Comparar periodos
      </label>

      {loading && <FiRefreshCw className="h-4 w-4 animate-spin text-primary" />}
    </div>
  );
}
