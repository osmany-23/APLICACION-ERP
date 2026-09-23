import { Component, ReactNode } from 'react';
import { FiAlertTriangle } from 'react-icons/fi';

/**
 * El panel de control renderiza ~20 graficos de ApexCharts de forma
 * independiente. Sin este limite de error, un fallo puntual en UN SOLO
 * grafico (p. ej. un hiccup interno de ApexCharts al remontar, comun bajo
 * React.StrictMode en desarrollo) tumba el arbol de React completo y deja
 * TODO el panel en blanco. Con esto, si un grafico especifico falla, solo
 * esa tarjeta muestra un aviso y el resto del panel sigue funcionando.
 */
export default class ChartErrorBoundary extends Component<{ children: ReactNode }, { hasError: boolean }> {
  constructor(props: { children: ReactNode }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error: unknown) {
    // eslint-disable-next-line no-console
    console.error('Error al renderizar un grafico del panel:', error);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="flex flex-col items-center justify-center gap-2 py-10 text-center text-sm text-bodydark2">
          <FiAlertTriangle className="h-6 w-6 text-warning" />
          No se pudo mostrar este gráfico.
        </div>
      );
    }

    return this.props.children;
  }
}
