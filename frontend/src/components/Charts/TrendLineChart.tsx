import { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import ReactApexChart from 'react-apexcharts';
import useColorMode from '../../hooks/useColorMode';
import { CHART_PALETTE, formatCurrencyFull, formatCurrencyShort } from './chartUtils';

export type TrendSeries = {
  name: string;
  data: number[];
  color?: string;
};

/**
 * Grafico de lineas/area para todas las tendencias del panel (ventas,
 * compras, utilidad, flujo de caja, margen %). Se le puede pasar mas de
 * una serie para comparativas (p. ej. "Ventas vs compras") o para mostrar
 * el periodo actual junto al periodo anterior cuando el usuario activa
 * "Comparar periodos".
 */
export default function TrendLineChart({
  categories,
  series,
  height = 300,
  valueType = 'currency',
  area = true,
}: {
  categories: string[];
  series: TrendSeries[];
  height?: number;
  valueType?: 'currency' | 'percent' | 'number';
  area?: boolean;
}) {
  const [colorMode] = useColorMode();
  const isDark = colorMode === 'dark';
  const colors = series.map((s, index) => s.color ?? CHART_PALETTE[index % CHART_PALETTE.length]);

  const formatValue = (value: number) => {
    if (valueType === 'percent') return `${value.toFixed(1)}%`;
    if (valueType === 'number') return new Intl.NumberFormat('es-NI').format(value);
    return formatCurrencyShort(value);
  };

  const hasData = series.some((s) => s.data.some((v) => v !== 0));

  // Memoizado: sin esto, cada re-render (incluso por cosas ajenas al
  // grafico, como colapsar el sidebar) crea un objeto "options" nuevo y
  // fuerza a ApexCharts a re-procesar todo internamente en cada pasada.
  const options: ApexOptions = useMemo(
    () => ({
      chart: {
        fontFamily: 'Satoshi, sans-serif',
        height,
        type: area ? 'area' : 'line',
        toolbar: { show: false },
        zoom: { enabled: false },
        foreColor: isDark ? '#AEB7C0' : '#64748B',
      },
      colors,
      stroke: { curve: 'smooth', width: series.map(() => 2.5) },
      // "fill" siempre necesita un objeto valido (ApexCharts truena si es
      // undefined): para graficos de linea simple, "solid" no se nota
      // visualmente pero evita el crash.
      fill: area
        ? { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02, shadeIntensity: 1, stops: [0, 90, 100] } }
        : { type: 'solid' },
      dataLabels: { enabled: false },
      grid: {
        borderColor: isDark ? '#2E3A47' : '#F1F5F9',
        strokeDashArray: 4,
        yaxis: { lines: { show: true } },
      },
      markers: { size: 0, hover: { size: 5 } },
      xaxis: {
        categories,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { fontSize: '11px' }, rotate: categories.length > 12 ? -45 : 0 },
      },
      yaxis: {
        labels: { style: { fontSize: '11px' }, formatter: formatValue },
      },
      tooltip: {
        theme: isDark ? 'dark' : 'light',
        y: { formatter: (value: number) => (valueType === 'currency' ? formatCurrencyFull(value) : formatValue(value)) },
      },
      legend: {
        show: series.length > 1,
        position: 'top',
        horizontalAlign: 'left',
        fontSize: '12px',
        labels: { colors: isDark ? '#AEB7C0' : '#64748B' },
      },
      noData: { text: 'Sin datos en este periodo' },
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }),
    [categories, colors, isDark, height, area, valueType, series.length],
  );

  if (!hasData) {
    return (
      <div className="flex items-center justify-center text-sm text-bodydark2" style={{ height }}>
        Sin datos en este periodo.
      </div>
    );
  }

  return <ReactApexChart options={options} series={series} type={area ? 'area' : 'line'} height={height} />;
}
