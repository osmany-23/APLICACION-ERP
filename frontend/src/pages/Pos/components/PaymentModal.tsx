import { useEffect, useState } from 'react';
import { FiCheckCircle, FiClock, FiCreditCard, FiDollarSign, FiLayers, FiRefreshCw, FiRepeat, FiUserPlus, FiX } from 'react-icons/fi';
import { PosExchangeRate, PosPaymentMethod } from '../../../types/pos';
import { Customer } from '../../../types/customer';
import CustomerPickerModal from './CustomerPickerModal';

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-NI', { style: 'currency', currency: 'NIO' }).format(value || 0);
}

function methodIcon(method: PosPaymentMethod) {
  if (method.cash) return FiDollarSign;
  if (method.card) return FiCreditCard;
  if (method.bank) return FiRefreshCw;
  return FiLayers;
}

function formatForeign(value: number, symbol: string | null | undefined) {
  return `${symbol ?? '$'}${new Intl.NumberFormat('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0)}`;
}

export default function PaymentModal({
  total,
  paymentMethods,
  exchangeRate,
  submitting,
  error,
  customer,
  customers,
  customerId,
  token,
  onSelectCustomer,
  onCustomerCreated,
  onClose,
  onConfirm,
}: {
  total: number;
  paymentMethods: PosPaymentMethod[];
  exchangeRate: PosExchangeRate | null;
  submitting: boolean;
  error: string;
  customer?: Customer | null;
  customers: Customer[];
  customerId: string;
  token?: string;
  onSelectCustomer: (customerId: string) => void;
  onCustomerCreated?: (customer: Customer) => void;
  onClose: () => void;
  onConfirm: (
    paymentMethodId: number | null,
    tenderedInBase: number | null,
    isPendingPayment: boolean,
    paymentReference?: string,
    tenderedBase?: number,
    tenderedForeign?: number,
  ) => void;
}) {
  const [paymentMethodId, setPaymentMethodId] = useState<number | null>(paymentMethods.find((m) => m.cash)?.id ?? null);
  const [showCustomerPicker, setShowCustomerPicker] = useState(false);
  const [reference, setReference] = useState('');
  const [referenceError, setReferenceError] = useState('');

  // Si intentan cobrar sin cliente seleccionado, en vez de dejar solo el
  // aviso en rojo y obligarlos a cerrar "Cobrar factura" para ir a
  // elegirlo arriba, se abre aca mismo la ventana amplia de clientes.
  useEffect(() => {
    if (error && !customer) {
      setShowCustomerPicker(true);
    }
  }, [error, customer]);
  // Un cliente puede pagar con una mezcla de billetes en las dos monedas a
  // la vez (ej. un billete de $10 y uno de C$500 en la misma venta): se
  // capturan por separado, no como una eleccion excluyente de "una sola
  // moneda". La venta siempre queda contabilizada en la moneda base — el
  // monto en dolares solo se convierte para saber cuanto vuelto dar.
  const [tenderedBase, setTenderedBase] = useState('');
  const [tenderedForeign, setTenderedForeign] = useState('');
  // "Pendiente de pago" (contra entrega): se factura sin metodo de pago
  // todavia, igual que Credito, pero es un concepto distinto (no es
  // credito real del cliente) — por eso es un estado aparte, no una
  // reutilizacion de paymentMethodId === null.
  const [isPendingPayment, setIsPendingPayment] = useState(false);

  const selectedMethod = paymentMethods.find((method) => method.id === paymentMethodId) ?? null;
  const isCash = selectedMethod?.cash ?? false;
  const canPayForeign = isCash && Boolean(exchangeRate);
  // Tarjeta o cualquier metodo marcado "requires_reference" (transferencia,
  // cheque, deposito) exige un codigo de referencia — igual regla que ya
  // valida el backend (ver SalesService::createSale()).
  const needsReference = Boolean(selectedMethod?.card || selectedMethod?.requires_reference);

  const tenderedBaseAmount = Number(tenderedBase || 0);
  const tenderedForeignAmount = Number(tenderedForeign || 0);
  const tenderedForeignInBase = canPayForeign && exchangeRate ? tenderedForeignAmount * exchangeRate.rate : 0;
  const tenderedInBase = tenderedBaseAmount + tenderedForeignInBase;
  const change = Math.max(0, tenderedInBase - total);
  const canConfirm = !submitting && (!isCash || tenderedInBase >= total);

  function handlePaymentMethodChange(id: number | null) {
    setPaymentMethodId(id);
    setIsPendingPayment(false);
    setTenderedBase('');
    setTenderedForeign('');
    setReference('');
    setReferenceError('');
  }

  function handlePendingPaymentSelected() {
    setPaymentMethodId(null);
    setIsPendingPayment(true);
    setTenderedBase('');
    setTenderedForeign('');
    setReference('');
    setReferenceError('');
  }

  function handleConfirm() {
    if (isPendingPayment) {
      onConfirm(null, null, true);
      return;
    }

    if (needsReference && !reference.trim()) {
      setReferenceError('Ingresa el codigo de referencia del pago.');
      return;
    }

    if (!isCash) {
      onConfirm(paymentMethodId, null, false, reference.trim() || undefined);
      return;
    }

    onConfirm(
      paymentMethodId,
      tenderedInBase,
      false,
      reference.trim() || undefined,
      tenderedBaseAmount || undefined,
      tenderedForeignAmount || undefined,
    );
  }

  return (
    <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 px-4" onClick={onClose}>
      <div
        className="w-full max-w-4xl overflow-hidden rounded-2xl border border-stroke bg-white shadow-2xl dark:border-strokedark dark:bg-boxdark"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="flex items-center justify-between bg-gradient-to-r from-[#0F9F37] to-emerald-500 px-6 py-5 text-white">
          <span className="flex items-center gap-2.5 text-base font-black uppercase tracking-wide"><FiDollarSign className="h-5 w-5" /> Cobrar factura</span>
          <button type="button" onClick={onClose} className="rounded-full p-1.5 transition hover:bg-white/10">
            <FiX className="h-5 w-5" />
          </button>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-5">
          {/* Columna izquierda: eleccion del metodo de pago */}
          <div className="border-b border-stroke p-6 lg:col-span-3 lg:border-b-0 lg:border-r dark:border-strokedark">
            <p className="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">1. Metodo de pago</p>
            <div className="grid grid-cols-3 gap-3">
              <button
                type="button"
                onClick={() => handlePaymentMethodChange(null)}
                className={`flex h-24 flex-col items-center justify-center gap-2 rounded-xl border-2 text-xs font-bold transition ${
                  paymentMethodId === null && !isPendingPayment
                    ? 'border-primary bg-primary/10 text-primary shadow-sm'
                    : 'border-stroke text-slate-500 hover:border-primary/50 hover:bg-primary/5 dark:border-strokedark'
                }`}
              >
                <FiRepeat className="h-6 w-6" /> Credito
              </button>
              <button
                type="button"
                onClick={handlePendingPaymentSelected}
                className={`flex h-24 flex-col items-center justify-center gap-2 rounded-xl border-2 px-2 text-center text-xs font-bold leading-tight transition ${
                  isPendingPayment
                    ? 'border-indigo-500 bg-indigo-50 text-indigo-600 shadow-sm dark:bg-indigo-500/10'
                    : 'border-stroke text-slate-500 hover:border-indigo-400/50 hover:bg-indigo-50/50 dark:border-strokedark'
                }`}
              >
                <FiClock className="h-6 w-6" /> Pendiente de pago
              </button>
              {paymentMethods.map((method) => {
                const Icon = methodIcon(method);
                return (
                  <button
                    key={method.id}
                    type="button"
                    onClick={() => handlePaymentMethodChange(method.id)}
                    className={`flex h-24 flex-col items-center justify-center gap-2 rounded-xl border-2 px-2 text-center text-xs font-bold leading-tight transition ${
                      paymentMethodId === method.id
                        ? 'border-primary bg-primary/10 text-primary shadow-sm'
                        : 'border-stroke text-slate-500 hover:border-primary/50 hover:bg-primary/5 dark:border-strokedark'
                    }`}
                  >
                    <Icon className="h-6 w-6" /> {method.name}
                  </button>
                );
              })}
            </div>

            {paymentMethodId === null && !isPendingPayment && (
              <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
                Sin metodo de pago seleccionado, la factura se registrara a credito.
              </p>
            )}

            {isPendingPayment && (
              <p className="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2.5 text-xs font-semibold text-indigo-700 dark:border-indigo-900 dark:bg-indigo-900/20 dark:text-indigo-300">
                Esta factura quedara Pendiente de pago: el inventario se descuenta ahora, pero la venta no entra a
                caja ni a reportes hasta que se confirme el cobro (Ventas &gt; Confirmar pago recibido).
              </p>
            )}

            {needsReference && (
              <label className="mt-4 block">
                <span className="mb-1.5 block text-xs font-semibold text-slate-500">Codigo de referencia</span>
                <input
                  value={reference}
                  onChange={(event) => {
                    setReference(event.target.value);
                    setReferenceError('');
                  }}
                  className="h-11 w-full rounded-lg border border-stroke bg-white px-3 text-sm font-semibold text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                  placeholder="N. de autorizacion, voucher o transferencia"
                />
                {referenceError && <p className="mt-1.5 text-xs font-semibold text-red-500">{referenceError}</p>}
              </label>
            )}
          </div>

          {/* Columna derecha: total, moneda del pago y calculo del cambio */}
          <div className="flex flex-col gap-4 bg-slate-50 p-6 lg:col-span-2 dark:bg-white/[0.02]">
            <div>
              <p className="mb-3 text-xs font-black uppercase tracking-wide text-slate-500">2. Total y pago</p>
              <div className="rounded-xl bg-[#E8F0FF] px-4 py-4 text-center dark:bg-primary/10">
                <p className="text-xs font-bold uppercase text-primary">Total a pagar</p>
                <p className="text-3xl font-black text-primary">{formatCurrency(total)}</p>
                {exchangeRate && (
                  <p className="mt-1 text-sm font-bold text-primary/70">
                    ≈ {formatForeign(total / exchangeRate.rate, exchangeRate.foreign_currency.symbol)} {exchangeRate.foreign_currency.code}
                  </p>
                )}
              </div>
            </div>

            {paymentMethodId !== null && customer?.ir_withholding_agent && (
              <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300">
                Este cliente retiene IR ({customer.ir_withholding_rate}%): vas a recibir aproximadamente{' '}
                {formatCurrency(total * (1 - customer.ir_withholding_rate / 100))} en efectivo/transferencia — el
                resto queda como retencion a favor de la empresa, no se le sigue cobrando al cliente.
              </div>
            )}

            {isCash && (
              <div className="space-y-3">
                {/* El cliente puede pagar con una mezcla de billetes en las
                    dos monedas (ej. un billete de $10 y uno de C$500 en la
                    misma venta): ambos campos se capturan a la vez, no como
                    una eleccion de "una sola moneda". */}
                <div className={canPayForeign ? 'grid grid-cols-2 gap-3' : ''}>
                  <label className="block">
                    <span className="mb-1.5 block text-xs font-semibold text-slate-500">
                      Recibido en {exchangeRate?.base_currency.code ?? 'C$'}
                    </span>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      autoFocus
                      value={tenderedBase}
                      onChange={(event) => setTenderedBase(event.target.value)}
                      className="h-12 w-full rounded-lg border border-stroke bg-white px-3 text-base font-bold text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                      placeholder="0.00"
                    />
                  </label>

                  {canPayForeign && exchangeRate && (
                    <label className="block">
                      <span className="mb-1.5 block text-xs font-semibold text-slate-500">
                        Recibido en {exchangeRate.foreign_currency.code}
                      </span>
                      <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={tenderedForeign}
                        onChange={(event) => setTenderedForeign(event.target.value)}
                        className="h-12 w-full rounded-lg border border-stroke bg-white px-3 text-base font-bold text-black outline-none transition focus:border-primary dark:border-strokedark dark:bg-boxdark dark:text-white"
                        placeholder="0.00"
                      />
                    </label>
                  )}
                </div>

                {canPayForeign && exchangeRate && (
                  <div className="space-y-1 rounded-lg border border-dashed border-primary/40 bg-primary/5 px-3 py-2 text-xs font-semibold text-primary">
                    <div className="flex items-center gap-1.5"><FiRefreshCw className="h-3 w-3" /> 1 {exchangeRate.foreign_currency.code} = {formatCurrency(exchangeRate.rate)}</div>
                    <div className="flex items-center justify-between">
                      <span>Total recibido:</span>
                      <span>
                        {formatCurrency(tenderedInBase)}
                        {tenderedForeignAmount > 0 && (
                          <>
                            {' '}({formatCurrency(tenderedBaseAmount)} + {formatForeign(tenderedForeignAmount, exchangeRate.foreign_currency.symbol)})
                          </>
                        )}
                      </span>
                    </div>
                  </div>
                )}

                <div>
                  <span className="mb-1.5 block text-xs font-semibold text-slate-500">Cambio a entregar (en {exchangeRate?.base_currency.code ?? 'C$'})</span>
                  <div className="flex h-14 items-center justify-center rounded-lg border-2 border-[#0F9F37]/30 bg-[#0F9F37]/10 px-3 text-2xl font-black text-[#0F9F37]">
                    {formatCurrency(change)}
                  </div>
                  {canPayForeign && exchangeRate && change > 0 && (
                    <p className="mt-1 text-center text-xs font-semibold text-[#0F9F37]">
                      ≈ {formatForeign(change / exchangeRate.rate, exchangeRate.foreign_currency.symbol)} {exchangeRate.foreign_currency.code}
                    </p>
                  )}
                </div>
              </div>
            )}

            <div className="mt-auto" />
          </div>
        </div>

        <div className="border-t border-stroke p-5 dark:border-strokedark">
          {error && (
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-semibold text-red-500 dark:border-red-900 dark:bg-red-900/20">
              <span>{error}</span>
              {!customer && (
                <button
                  type="button"
                  onClick={() => setShowCustomerPicker(true)}
                  className="inline-flex items-center gap-1.5 rounded-lg bg-red-500 px-3 py-1.5 text-white transition hover:bg-red-600"
                >
                  <FiUserPlus className="h-3.5 w-3.5" /> Elegir cliente
                </button>
              )}
            </div>
          )}

          <button
            type="button"
            disabled={!canConfirm}
            onClick={handleConfirm}
            className="inline-flex h-14 w-full items-center justify-center gap-2 rounded-xl bg-[#0F9F37] text-base font-black text-white shadow-sm transition hover:bg-[#0c8a2e] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50"
          >
            <FiCheckCircle className="h-5 w-5" /> {submitting ? 'Procesando...' : 'Confirmar pago'}
          </button>
        </div>
      </div>

      {showCustomerPicker && (
        <CustomerPickerModal
          customers={customers}
          selectedId={customerId}
          token={token}
          onSelect={onSelectCustomer}
          onCreated={onCustomerCreated}
          onClose={() => setShowCustomerPicker(false)}
        />
      )}
    </div>
  );
}
