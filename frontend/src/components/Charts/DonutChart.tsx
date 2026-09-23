import { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import ReactApexChart from 'react-apexcharts';
import useColorMode from '../../hooks/useColorMode';
import { CHART_PALETTE, formatCurrencyFull } from './chartUtils';
import type { BarDatum } from './SimpleBarChart';

/**
 * Dona: metodos de pago, estado de cuentas por cobrar. Muestra el total
 * general en el centro (comportamiento nativo de ApexCharts para donut
 * con dataLabels.total habilitado).
 */
export default function DonutChart({
  data,
  height = 300,
  emptyMessage = 'Sin datos en este periodo.',
}: {
  data: BarDatum[];
  height?: number;
  emptyMessage?: string;
}) {
  const [colorMode] = useColorMode();
  const isDark = colorMode === 'dark';

  // useMemo antes de cualquier return temprano (ver nota en SimpleBarChart):
  // los Hooks deben llamarse siempre en el mismo orden en cada render.
  const options: ApexOptions = useMemo(
    () => ({
      chart: {
        fontFamily: 'Satoshi, sans-serif',
        type: 'donut',
      },
      colors: CHART_PALETTE,
      labels: data.map((d) => d.label),
      dataLabels: {
        enabled: true,
        formatter: (val: number) => `${val.toFixed(0)}%`,
        style: { fontSize: '11px' },
      },
      legend: {
        position: 'bottom',
        fontSize: '12px',
        labels: { colors: isDark ? '#AEB7C0' : '#64748B' },
      },
      stroke: { colors: [isDark ? '#24303F' : '#FFFFFF'] },
      plotOptions: {
        pie: {
          donut: {
            size: '65%',
            labels: {
              show: true,
              total: {
                show: true,
                label: 'Total',
                color: isDark ? '#E2E8F0' : '#1E293B',
                formatter: (w) => formatCurrencyFull(w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0)),
              },
              value: {
                color: isDark ? '#E2E8F0' : '#1E293B',
                formatter: (val: string) => formatCurrencyFull(Number(val)),
              },
            },
          },
        },
      },
      tooltip: {
        theme: isDark ? 'dark' : 'light',
        y: { formatter: (value: number) => formatCurrencyFull(value) },
      },
      responsive: [{ breakpoint: 480, options: { chart: { width: 260 }, legend: { position: 'bottom' } } }],
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }),
    [data, isDark],
  );

  if (data.length === 0) {
    return (
      <div className="flex items-center justify-center text-sm text-bodydark2" style={{ height }}>
        {emptyMessage}
      </div>
    );
  }

  return (
    <ReactApexChart
      options={options}
      series={data.map((d) => d.value)}
      type="donut"
      height={height}
    />
  );
}
