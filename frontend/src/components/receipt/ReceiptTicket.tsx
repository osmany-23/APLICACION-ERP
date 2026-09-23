import { CSSProperties, ReactNode } from 'react';
import { Sale, ReceiptConfig } from '../../types/sale';
import { code39Modules } from '../../utils/code39';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function formatForeign(value: number, symbol: string | null | undefined) {
  return `${symbol ?? '$'}${new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0)}`;
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';

  return new Intl.DateTimeFormat('es-NI', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true,
  }).format(parsed).toUpperCase();
}

function formatDateOnly(value: string | null | undefined) {
  if (!value) return '-';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '-';

  // timeZone: 'UTC' evita que una fecha pura ("2026-06-30", sin hora, que
  // el navegador interpreta como medianoche UTC) se muestre un dia antes
  // en zonas horarias detras de UTC (Nicaragua es UTC-6).
  return new Intl.DateTimeFormat('es-NI', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' }).format(parsed);
}

function addDays(value: string | null | undefined, days: number) {
  if (!value) return null;
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return null;

  // Aritmetica en UTC de punta a punta (no local): "value" es una fecha
  // pura ("2026-01-01") que el navegador interpreta como medianoche UTC,
  // y formatDateOnly() tambien formatea en UTC — si aca se sumaran dias
  // con setDate()/getDate() (que operan en hora local), en una zona detras
  // de UTC (Nicaragua es UTC-6) el resultado quedaria corrido un dia.
  const utc = Date.UTC(parsed.getUTCFullYear(), parsed.getUTCMonth(), parsed.getUTCDate() + days);
  return new Date(utc).toISOString();
}

function tipoLabel(sale: Sale) {
  if (sale.transaction_status === 'PAID') return 'CONTADO';
  if (sale.transaction_status === 'CREDIT') return 'CREDITO';
  return 'PENDIENTE DE PAGO';
}

function lateFeePeriodLabel(unit: 'DAYS' | 'WEEKS' | 'MONTHS' | null | undefined) {
  if (unit === 'DAYS') return 'diario';
  if (unit === 'WEEKS') return 'semanal';
  return 'mensual';
}

// Estilos en linea (no clases de Tailwind) para el borde/fondo negro de
// cada caja: no dependen de que el navegador tenga activado "Graficos de
// fondo" al imprimir (ver print-color-adjust mas abajo) ni de que Tailwind
// haya generado la clase exacta — border-color nunca lo suprime ningun
// navegador, y el fondo negro del titulo queda forzado por
// print-color-adjust: exact en el contenedor raiz.
// <fieldset>/<legend> en vez de un div con una barra superior: el navegador
// ya coloca la "legend" montada sobre el borde superior por defecto (igual
// que en la imagen de referencia, la etiqueta negra queda pegada a la
// izquierda, encima del borde del recuadro), sin necesidad de posicionar
// nada a mano.
function Box({ title, children }: { title: string; children: ReactNode }) {
  return (
    <fieldset className="mb-3 rounded-md px-2.5 pb-2 pt-1" style={{ border: '1.5px solid #000', margin: '0 0 12px' }}>
      <legend
        className="ml-1 px-2 py-0.5 text-left text-[11px] font-bold uppercase tracking-wide"
        style={{ backgroundColor: '#000', color: '#fff' }}
      >
        {title}
      </legend>
      <div className="space-y-1 text-[11px] leading-snug">{children}</div>
    </fieldset>
  );
}

function Barcode({ value }: { value: string }) {
  const modules = code39Modules(value);
  if (!modules) return null;

  const unit = 1.6;
  const height = 40;
  const width = modules.length * unit;

  return (
    <svg
      viewBox={`0 0 ${width} ${height}`}
      width="100%"
      height={height}
      preserveAspectRatio="none"
      className="block"
      role="img"
      aria-label={`Codigo de barra ${value}`}
    >
      <rect x={0} y={0} width={width} height={height} fill="#fff" />
      {modules.split('').map((bit, index) =>
        bit === '1' ? <rect key={index} x={index * unit} y={0} width={unit} height={height} fill="#000" /> : null,
      )}
    </svg>
  );
}

export default function ReceiptTicket({
  sale,
  config,
}: {
  sale: Sale;
  config: ReceiptConfig;
}) {
  const isPaid = sale.transaction_status === 'PAID';
  // "N. Cedula"/"Recibi conforme" solo tienen sentido cuando todavia hay
  // plata pendiente de cobrar (credito o pendiente de pago): en una venta
  // de contado ya cobrada no hace falta que nadie firme un compromiso de
  // pago.
  const requiresSignature = !isPaid;

  const subtotal = Number(sale.subtotal) || 0;
  const discount = Number(sale.discount) || 0;
  const tax = Number(sale.tax) || 0;
  const total = Number(sale.total) || 0;
  const taxableBase = Math.max(subtotal - discount, 0);
  const taxRate = taxableBase > 0 ? Math.round((tax / taxableBase) * 100) : 0;

  const exchangeRate = Number(sale.exchange_rate) || 0;
  const showExchangeRate = exchangeRate > 0 && Math.abs(exchangeRate - 1) > 0.0001;

  const claimDue = addDays(sale.sale_date, config.claim_days);
  const companyName = config.display_name || config.company_name || '';

  const customerLines = [
    sale.customer_tax_id ? { label: 'RUC:', value: sale.customer_tax_id } : null,
    sale.customer_address ? { label: 'DIRECCION:', value: sale.customer_address } : null,
    sale.customer_phone ? { label: 'CELULAR:', value: sale.customer_phone } : null,
  ].filter(Boolean) as { label: string; value: string }[];

  return (
    <div
      className="receipt-ticket mx-auto w-full max-w-[340px] bg-white p-4 text-left text-black"
      style={{
        fontFamily: 'Arial, Helvetica, sans-serif',
        // El modal que envuelve este ticket en el POS (ReceiptModal) trae
        // "text-center" para centrar su encabezado de color — text-align
        // se hereda en CSS, asi que sin este reset explicito todo el
        // contenido del ticket (parrafos, y hasta la posicion del legend
        // dentro de cada fieldset) queda centrado en vez de a la
        // izquierda.
        textAlign: 'left',
        WebkitPrintColorAdjust: 'exact',
        printColorAdjust: 'exact',
        colorAdjust: 'exact',
      } as CSSProperties}
    >
      {/* Los navegadores no imprimen colores de fondo por defecto (el
          usuario tendria que activar "Graficos de fondo" a mano en el
          dialogo de impresion) — "print-color-adjust: exact" fuerza a que
          las barras negras de cada seccion SI se impriman, sin depender de
          esa casilla. */}
      <style>{`
        @media print {
          .receipt-ticket, .receipt-ticket * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
          }
        }
      `}</style>
      <div className="mb-3 text-center">
        {config.show_logo && config.logo_url && (
          <img
            src={config.logo_url}
            alt={companyName}
            className="mx-auto mb-2 max-w-full object-contain"
            style={{ height: config.logo_height ?? 64 }}
          />
        )}
        <h1 className="text-lg font-black uppercase leading-tight">{companyName}</h1>
      </div>

      <div className="mb-3 flex flex-wrap items-center justify-between gap-2 text-[11px] font-semibold">
        <span>FECHA: {formatDateTime(sale.created_at ?? sale.sale_date)}</span>
        <span>TIPO: {tipoLabel(sale)}</span>
      </div>

      <Box title="Datos de la empresa">
        {sale.branch?.name && <p><span className="font-bold">SUCURSAL:</span> {sale.branch.name}</p>}
        {config.company_tax_id && <p><span className="font-bold">RUC:</span> {config.company_tax_id}</p>}
        {(sale.branch?.address || config.company_address) && (
          <p><span className="font-bold">DIRECCION:</span> {sale.branch?.address || config.company_address}</p>
        )}
        {(sale.branch?.phone || config.company_phone) && (
          <p><span className="font-bold">CELULAR:</span> {sale.branch?.phone || config.company_phone}</p>
        )}
        <p><span className="font-bold">VENDEDOR:</span> {sale.salesperson_name ?? sale.created_by_name ?? '-'}</p>
      </Box>

      <Box title="Datos del cliente">
        <p><span className="font-bold">CLIENTE:</span> {sale.customer_business_name || sale.customer_name || '-'}</p>
        {customerLines.map((line) => (
          <p key={line.label}><span className="font-bold">{line.label}</span> {line.value}</p>
        ))}
      </Box>

      <div className="mb-3">
        <table className="w-full text-[10px]">
          <thead>
            <tr className="text-left uppercase" style={{ borderBottom: '2px solid #000' }}>
              <th className="py-1 pr-1">Cant</th>
              <th className="py-1 pr-1">Und</th>
              <th className="py-1 pr-1">Descripcion</th>
              <th className="py-1 pr-1 text-right">Precio</th>
              <th className="py-1 text-right">Total</th>
            </tr>
          </thead>
          <tbody>
            {(sale.items ?? []).map((item) => (
              <tr key={item.id} className="align-top" style={{ borderBottom: '1px dashed #000' }}>
                <td className="py-1 pr-1">{item.quantity}</td>
                <td className="py-1 pr-1">{item.unit || 'UND'}</td>
                <td className="py-1 pr-1">
                  {item.product_name}
                  {item.has_warranty && (
                    <span className="mt-0.5 block text-[9px] font-semibold normal-case text-black">
                      Garantia{item.warranty_type ? ` (${item.warranty_type})` : ''}
                      {item.warranty_expires_at ? ` - vence ${formatDateOnly(item.warranty_expires_at)}` : ''}
                    </span>
                  )}
                </td>
                <td className="py-1 pr-1 text-right">{formatCurrency(item.unit_price)}</td>
                <td className="py-1 text-right">{formatCurrency(item.total)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mb-3 space-y-1 rounded-md px-2.5 py-2 text-[11px]" style={{ border: '1.5px solid #000' }}>
        <div className="flex justify-between"><span>CANTIDAD TOTAL:</span><span className="font-semibold">{formatCurrency(subtotal)}</span></div>
        {discount > 0 && <div className="flex justify-between"><span>DESCUENTO:</span><span className="font-semibold">-{formatCurrency(discount)}</span></div>}
        <div className="flex justify-between"><span>IVA ({taxRate}%):</span><span className="font-semibold">{formatCurrency(tax)}</span></div>
        <div className="flex justify-between pt-1 text-[13px] font-black" style={{ borderTop: '1.5px solid #000' }}><span>GRAN TOTAL:</span><span>{formatCurrency(total)}</span></div>
      </div>

      <Box title="Informacion del pago">
        {showExchangeRate && <div className="flex justify-between"><span>TIPO DE CAMBIO:</span><span>{formatCurrency(exchangeRate)}</span></div>}
        {isPaid && (
          <div className="flex justify-between"><span>PAGADO CON:</span><span className="font-semibold">{sale.payment_method_name?.toUpperCase() ?? '-'}</span></div>
        )}
        {!isPaid && (
          <div className="flex justify-between"><span>CONDICION:</span><span className="font-semibold">{tipoLabel(sale)}</span></div>
        )}
        {sale.payment_reference && (
          <div className="flex justify-between"><span>CODIGO DE REFERENCIA:</span><span className="font-semibold">{sale.payment_reference}</span></div>
        )}
        {(sale.amount_tendered_base != null || sale.amount_tendered_foreign != null) && (
          <>
            <div className="flex justify-between"><span>MONTO C$:</span><span>{formatCurrency(total)}</span></div>
            {showExchangeRate && (
              <div className="flex justify-between"><span>MONTO $:</span><span>{formatForeign(total / exchangeRate, '$')}</span></div>
            )}
            {/* Un cliente puede pagar con una mezcla de billetes en las dos
                monedas (ej. un billete de $10 y uno de C$500 en la misma
                venta): se muestra cada uno por separado cuando aplica. */}
            {sale.amount_tendered_base != null && (
              <div className="flex justify-between">
                <span>RECIBIDO C$:</span><span>{formatCurrency(sale.amount_tendered_base)}</span>
              </div>
            )}
            {sale.amount_tendered_foreign != null && (
              <div className="flex justify-between">
                <span>RECIBIDO $:</span><span>{formatForeign(sale.amount_tendered_foreign, '$')}</span>
              </div>
            )}
            {sale.change_amount != null && (
              <div className="flex justify-between font-semibold"><span>VUELTO:</span><span>{formatCurrency(sale.change_amount)}</span></div>
            )}
          </>
        )}
      </Box>

      {sale.transaction_status === 'CREDIT' && (
        <Box title="Politica de credito">
          {sale.due_date && (
            <div className="flex justify-between">
              <span>FECHA DE VENCIMIENTO:</span>
              <span className="font-semibold">{formatDateOnly(sale.due_date)}</span>
            </div>
          )}
          {sale.customer_applies_late_fee && sale.customer_late_fee_percentage ? (
            <p className="mt-1 text-[10px] font-semibold leading-snug">
              PASADA LA FECHA DE VENCIMIENTO SE APLICARA UN RECARGO POR MORA DEL {sale.customer_late_fee_percentage}%{' '}
              {lateFeePeriodLabel(sale.customer_late_fee_period_unit)} SOBRE EL SALDO PENDIENTE.
            </p>
          ) : (
            <p className="mt-1 text-[10px] font-semibold leading-snug">
              FAVOR CANCELAR ANTES DE LA FECHA DE VENCIMIENTO PARA EVITAR RECARGOS.
            </p>
          )}
        </Box>
      )}

      {config.footer_note && (
        <div className="mb-3">
          <div
            className="mb-1 inline-block px-2 py-0.5 text-[10px] font-bold uppercase"
            style={{ backgroundColor: '#000', color: '#fff' }}
          >
            Nota
          </div>
          <p className="text-[10px] font-semibold leading-snug">{config.footer_note}</p>
          {claimDue && (
            <p className="mt-2 text-[10px] font-semibold">
              VENCIMIENTO DE ESTE RECIBO: {formatDateOnly(claimDue)}
            </p>
          )}
        </div>
      )}

      {requiresSignature && (
        <div className="mb-4 mt-6 space-y-6 text-[10px]">
          <p>N. CEDULA: ________________________________</p>
          <div className="text-center">
            <p>________________________________</p>
            <p className="font-bold">RECIBI CONFORME</p>
          </div>
        </div>
      )}

      <div className="mt-4 text-center">
        <Barcode value={sale.sale_number} />
        <p className="mt-1 text-xs font-black">N. FACTURA: {sale.sale_number}</p>
      </div>
    </div>
  );
}
