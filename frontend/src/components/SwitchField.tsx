import React from 'react';

interface StatusSwitchProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  ariaLabel?: string;
}

/**
 * StatusSwitch - Componente Premium y Reutilizable
 * 
 * Diseño inspirado en iOS, Microsoft Fluent UI y Material Design 3
 * - Compacto y elegante
 * - Sin texto interno (ON/OFF)
 * - Colores semánticos: Verde #22C55E (Activo) / Rojo #EF4444 (Inactivo)
 * - Animación fluida de 200ms
 * - Completamente accesible (ARIA, Keyboard, Screen Readers)
 * 
 * Uso:
 * <StatusSwitch 
 *   checked={isActive} 
 *   onChange={(value) => setIsActive(value)}
 *   ariaLabel="Activo/Inactivo"
 * />
 */
export function StatusSwitch({ 
  checked, 
  onChange, 
  disabled = false,
  ariaLabel = 'Estado'
}: StatusSwitchProps) {
  const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      !disabled && onChange(!checked);
    }
  };

  return (
    <div className="flex flex-col items-start gap-3">
      {/* Switch Button */}
      <button
        type="button"
        onClick={() => !disabled && onChange(!checked)}
        onKeyDown={handleKeyDown}
        disabled={disabled}
        role="switch"
        aria-checked={checked}
        aria-label={ariaLabel}
        className={`relative inline-flex h-8 w-16 shrink-0 items-center rounded-full transition-all duration-200 ${
          checked 
            ? 'bg-green-500 hover:bg-green-600 active:bg-green-700' 
            : 'bg-red-500 hover:bg-red-600 active:bg-red-700'
        } ${
          disabled 
            ? 'cursor-not-allowed opacity-50' 
            : 'cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-slate-900'
        }`}
      >
        {/* Circle Toggle */}
        <span
          className={`absolute inline-block h-6 w-6 rounded-full bg-white shadow-md transition-transform duration-200 ${
            checked ? 'translate-x-9' : 'translate-x-1'
          }`}
          aria-hidden="true"
        />
      </button>

      {/* Status Label */}
      <span className={`text-xs font-semibold flex items-center gap-1.5 ${
        checked 
          ? 'text-green-600 dark:text-green-400' 
          : 'text-red-600 dark:text-red-400'
      }`}>
        <span className="text-lg">{checked ? '🟢' : '🔴'}</span>
        {checked ? 'Activo' : 'Inactivo'}
      </span>
    </div>
  );
}

/**
 * StatusSwitchInline - Versión compacta para tablas y listados
 * Muestra solo el switch sin etiqueta descriptiva
 */
export function StatusSwitchInline({ 
  checked, 
  onChange, 
  disabled = false,
  ariaLabel = 'Estado'
}: StatusSwitchProps) {
  const handleKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      !disabled && onChange(!checked);
    }
  };

  return (
    <button
      type="button"
      onClick={() => !disabled && onChange(!checked)}
      onKeyDown={handleKeyDown}
      disabled={disabled}
      role="switch"
      aria-checked={checked}
      aria-label={ariaLabel}
      title={checked ? 'Activo' : 'Inactivo'}
      className={`relative inline-flex h-7 w-14 shrink-0 items-center rounded-full transition-all duration-200 ${
        checked 
          ? 'bg-green-500 hover:bg-green-600 active:bg-green-700' 
          : 'bg-red-500 hover:bg-red-600 active:bg-red-700'
      } ${
        disabled 
          ? 'cursor-not-allowed opacity-50' 
          : 'cursor-pointer focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:focus:ring-offset-slate-900'
      }`}
    >
      <span
        className={`absolute inline-block h-5 w-5 rounded-full bg-white shadow-md transition-transform duration-200 ${
          checked ? 'translate-x-7' : 'translate-x-1'
        }`}
        aria-hidden="true"
      />
    </button>
  );
}

// Alias para compatibilidad con código existente
interface SwitchFieldProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
  label?: string;
  ariaLabel?: string;
}

export function SwitchField({ 
  checked, 
  onChange, 
  disabled = false,
  label,
  ariaLabel
}: SwitchFieldProps) {
  return <StatusSwitch checked={checked} onChange={onChange} disabled={disabled} ariaLabel={ariaLabel || label || 'Estado'} />;
}

export default StatusSwitch;
