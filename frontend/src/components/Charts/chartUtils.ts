// Paleta compartida por todos los graficos del panel de control, tomada de
// los mismos colores de marca ya definidos en tailwind.config.cjs (primary,
// secondary, success, warning, danger) mas un par de tonos adicionales para
// series/categorias que necesiten mas de 5 colores distintos.
export const CHART_PALETTE = [
  '#155EEF', // primary
  '#7C6FF0', // secondary
  '#12B76A', // success
  '#F79009', // warning
  '#F04438', // danger
  '#06AED4', // cyan
  '#EE46BC', // pink
  '#84CC16', // lima
];

export function formatCurrencyShort(value: number): string {
  const abs = Math.abs(value);
  if (abs >= 1_000_000) return `C$${(value / 1_000_000).toFixed(1)}M`;
  if (abs >= 1_000) return `C$${(value / 1_000).toFixed(1)}K`;
  return `C$${value.toFixed(0)}`;
}

export function formatCurrencyFull(value: number): string {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}
