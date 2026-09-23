import { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import ReactApexChart from 'react-apexcharts';
import useColorMode from '../../hooks/useColorMode';
import { formatCurrencyFull, formatCurrencyShort } from './chartUtils';
import type { BarDatum } from './SimpleBarChart';

/**
 * Barras horizontales: rankings tipo "Top 10 productos/clientes",
 * "Ventas por vendedor", "Utilidad por producto". El orden que traiga
 * "data" se respeta tal cual (el backend ya lo manda de mayor a menor).
 */
export default function HorizontalBarChart({
  data,
  height,
  valueType = 'currency',
  color = '#155EEF',
  emptyMessage = 'Sin datos en este periodo.',
}: {
  data: BarDatum[];
  height?: number;
  valueType?: 'currency' | 'number' | 'percent';
  color?: string;
  emptyMessage?: string;
}) {
  const [colorMode] = useColorMode();
  const isDark = colorMode === 'dark';
  const resolvedHeight = height ?? Math.max(220, data.length * 42);

  const formatValue = (value: number) => {
    if (valueType === 'percent') return `${value.toFixed(1)}%`;
    if (valueType === 'number') return new Intl.NumberFormat('es-NI').format(value);
    return formatCurrencyShort(value);
  };

  // ApexCharts dibuja las barras horizontales de abajo hacia arriba, asi
  // que se invierte el orden para que el #1 (primer elemento de "data")
  // quede arriba, como en cualquier ranking.
  const ordered = useMemo(() => [...data].reverse(), [data]);

  // useMemo antes de cualquier return temprano (ver nota en SimpleBarChart).
  const options: ApexOptions = useMemo(
    () => ({
      chart: {
        fontFamily: 'Satoshi, sans-serif',
        height: resolvedHeight,
        type: 'bar',
        toolbar: { show: false },
        foreColor: isDark ? '#AEB7C0' : '#64748B',
      },
      colors: [color],
      plotOptions: {
        bar: {
          horizontal: true,
          borderRadius: 5,
          barHeight: '55%',
          distributed: false,
        },
      },
      dataLabels: {
        enabled: true,
        formatter: formatValue,
        style: { fontSize: '11px', colors: [isDark ? '#E2E8F0' : '#1E293B'] },
        offsetX: 6,
      },
      grid: {
        borderColor: isDark ? '#2E3A47' : '#F1F5F9',
        strokeDashArray: 4,
        xaxis: { lines: { show: true } },
        yaxis: { lines: { show: false } },
      },
      xaxis: {
        categories: ordered.map((d) => d.label),
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { fontSize: '11px' }, formatter: formatValue },
      },
      yaxis: {
        labels: { style: { fontSize: '12px' } },
      },
      tooltip: {
        theme: isDark ? 'dark' : 'light',
        y: { formatter: (value: number) => (valueType === 'currency' ? formatCurrencyFull(value) : formatValue(value)) },
      },
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }),
    [ordered, resolvedHeight, isDark, color, valueType],
  );

  if (data.length === 0) {
    return (
      <div className="flex items-center justify-center text-sm text-bodydark2" style={{ height: resolvedHeight }}>
        {emptyMessage}
      </div>
    );
  }

  return (
    <ReactApexChart
      options={options}
      series={[{ name: 'Valor', data: ordered.map((d) => d.value) }]}
      type="bar"
      height={resolvedHeight}
    />
  );
}
