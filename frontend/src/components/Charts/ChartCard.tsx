import { ReactNode } from 'react';
import ChartErrorBoundary from './ChartErrorBoundary';

/**
 * Contenedor comun para todas las tarjetas de grafico del panel de
 * control: mismo look que PanelCard (Dashboard/ECommerce.tsx) pero con
 * espacio para subtitulo y una accion a la derecha del titulo (p. ej. un
 * badge con el total del periodo).
 */
export default function ChartCard({
  title,
  subtitle,
  action,
  children,
  className = '',
}: {
  title: string;
  subtitle?: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={`rounded-xl border border-stroke bg-white p-5 shadow-card dark:border-strokedark dark:bg-boxdark ${className}`}>
      <div className="mb-4 flex items-start justify-between gap-3">
        <div>
          <h3 className="text-sm font-bold text-black dark:text-white">{title}</h3>
          {subtitle && <p className="mt-0.5 text-xs text-bodydark2">{subtitle}</p>}
        </div>
        {action}
      </div>
      <ChartErrorBoundary>{children}</ChartErrorBoundary>
    </div>
  );
}
