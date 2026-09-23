import { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import ReactApexChart from 'react-apexcharts';
import useColorMode from '../../hooks/useColorMode';
import { CHART_PALETTE, formatCurrencyFull, formatCurrencyShort } from './chartUtils';

export type BarDatum = { label: string; value: number };

/**
 * Barras verticales: ventas por categoria/sucursal, stock bajo, utilidad
 * por categoria, etc. Cada barra puede tener su propio color (por defecto
 * usa la paleta rotando) o un solo color fijo si se pasa "color".
 */
export default function SimpleBarChart({
  data,
  height = 300,
  valueType = 'currency',
  color,
  emptyMessage = 'Sin datos en este periodo.',
}: {
  data: BarDatum[];
  height?: number;
  valueType?: 'currency' | 'number';
  color?: string;
  emptyMessage?: string;
}) {
  const [colorMode] = useColorMode();
  const isDark = colorMode === 'dark';

  const formatValue = (value: number) => (valueType === 'currency' ? formatCurrencyShort(value) : new Intl.NumberFormat('es-NI').format(value));

  // El useMemo va ANTES de cualquier "return" temprano: los Hooks de React
  // deben llamarse siempre en el mismo orden en cada render, y "data" puede
  // pasar de vacio a tener contenido entre un render y el siguiente.
  const options: ApexOptions = useMemo(() => {
    const colors = color ? [color] : data.map((_, index) => CHART_PALETTE[index % CHART_PALETTE.length]);

    return {
      chart: {
        fontFamily: 'Satoshi, sans-serif',
        height,
        type: 'bar',
        toolbar: { show: false },
        foreColor: isDark ? '#AEB7C0' : '#64748B',
      },
      colors,
      plotOptions: {
        bar: {
          borderRadius: 6,
          columnWidth: data.length > 8 ? '70%' : '45%',
          distributed: true,
        },
      },
      dataLabels: { enabled: false },
      legend: { show: false },
      grid: {
        borderColor: isDark ? '#2E3A47' : '#F1F5F9',
        strokeDashArray: 4,
      },
      xaxis: {
        categories: data.map((d) => d.label),
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
          style: { fontSize: '11px' },
          rotate: -35,
          trim: true,
          hideOverlappingLabels: true,
        },
      },
      yaxis: {
        labels: { style: { fontSize: '11px' }, formatter: formatValue },
      },
      tooltip: {
        theme: isDark ? 'dark' : 'light',
        y: { formatter: (value: number) => (valueType === 'currency' ? formatCurrencyFull(value) : new Intl.NumberFormat('es-NI').format(value)) },
      },
      // eslint-disable-next-line react-hooks/exhaustive-deps
    };
  }, [data, height, isDark, color, valueType]);

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
      series={[{ name: 'Valor', data: data.map((d) => d.value) }]}
      type="bar"
      height={height}
    />
  );
}
