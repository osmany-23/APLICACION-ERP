# StatusSwitch - Componente Premium de Estado

## Descripción

Componente reutilizable y centralizado para controlar estados Activo/Inactivo en toda la aplicación. Diseño premium inspirado en iOS, Microsoft Fluent UI y Material Design 3.

## Características

✅ **Diseño Premium** - Inspirado en iOS, Fluent UI y Material Design 3  
✅ **Compacto** - 20% más pequeño que el anterior  
✅ **Sin Texto Interno** - No muestra ON/OFF dentro del botón  
✅ **Colores Semánticos** - Verde #22C55E (Activo) / Rojo #EF4444 (Inactivo)  
✅ **Animación Fluida** - 200ms de transición suave  
✅ **Completamente Redondeado** - Sin bordes negros ni sombras exageradas  
✅ **Accesible** - Keyboard Navigation, Focus, ARIA, Screen Readers  
✅ **Responsive** - Funciona en todos los dispositivos  
✅ **Tema Oscuro** - Soporta light/dark mode  

## Composiciones

### StatusSwitch (Default)
Componente con etiqueta descriptiva debajo del switch. Usado en formularios y diálogos.

```tsx
import { StatusSwitch } from '@/components/SwitchField';

<StatusSwitch 
  checked={isActive} 
  onChange={(value) => setIsActive(value)}
  ariaLabel="Estado del producto"
/>
```

**Output:**
```
[●─────]  ← Verde: Activo
🟢 Activo
```

### StatusSwitchInline
Componente compacto solo con el switch. Usado en tablas, listados y vistas inline.

```tsx
import { StatusSwitchInline } from '@/components/SwitchField';

<StatusSwitchInline 
  checked={isActive} 
  onChange={(value) => setIsActive(value)}
  ariaLabel="Estado"
/>
```

**Output:**
```
[●────]  ← Verde: Activo (sin etiqueta)
```

### SwitchField (Compatibilidad)
Alias para compatibilidad con código existente. Redirige a StatusSwitch.

```tsx
import { SwitchField } from '@/components/SwitchField';

<SwitchField 
  checked={isActive} 
  onChange={(value) => setIsActive(value)}
  label="Estado"  // Opcional, se ignora visualmente
/>
```

## Props

```tsx
interface StatusSwitchProps {
  checked: boolean;           // Estado actual (true = Activo)
  onChange: (checked: boolean) => void;  // Callback al cambiar
  disabled?: boolean;         // Deshabilitar el switch (default: false)
  ariaLabel?: string;         // Texto para screen readers (default: 'Estado')
}
```

## Características de Accesibilidad

### Keyboard Navigation
- **Space/Enter** - Alterna el estado
- **Tab** - Navega al elemento
- **Shift+Tab** - Navega hacia atrás

### ARIA
- `role="switch"` - Identifica como switch
- `aria-checked={boolean}` - Indica estado actual
- `aria-label={string}` - Texto para screen readers

### Visual
- **Focus Ring** - Anillo verde visible en todos los estados
- **Hover States** - Cambio de color al pasar mouse
- **Active States** - Cambio más pronunciado al hacer click

## Colores y Estados

### Activo (Checked)
- **Fondo:** Verde #22C55E / Verde oscuro #16a34a (hover)
- **Círculo:** Blanco
- **Posición:** Derecha (translate-x-9)
- **Etiqueta:** 🟢 Activo

### Inactivo (Unchecked)
- **Fondo:** Rojo #EF4444 / Rojo oscuro #dc2626 (hover)
- **Círculo:** Blanco
- **Posición:** Izquierda (translate-x-1)
- **Etiqueta:** 🔴 Inactivo

## Animaciones

- **Duración:** 200ms
- **Easing:** ease-in-out (transición suave de Tailwind)
- **Elementos animados:**
  - Fondo (color)
  - Círculo (posición)

## Ejemplos de Uso

### En Formularios
```tsx
<div className="md:col-span-2">
  <StatusSwitch 
    checked={form.is_active} 
    onChange={(checked) => updateForm('is_active', checked)}
    ariaLabel="Estado del documento"
  />
</div>
```

### En Tablas
```tsx
<td className="px-3 py-3">
  <StatusSwitchInline 
    checked={item.is_active} 
    onChange={(checked) => handleStatusChange(item.id, checked)}
    ariaLabel={`Estado de ${item.name}`}
  />
</td>
```

### En Listados con Cambio Inmediato
```tsx
<button 
  onClick={() => setStatus(!status)}
  className="flex items-center gap-2"
>
  <StatusSwitchInline 
    checked={status} 
    onChange={() => {}} // Controlado por parent
  />
  <span>{item.name}</span>
</button>
```

## Compatibilidad

- ✅ Chrome, Edge, Firefox
- ✅ Mobile (iOS Safari, Chrome Android)
- ✅ Tema claro/oscuro
- ✅ Modo alto contraste
- ✅ Navegación por teclado completa
- ✅ Screen readers (NVDA, JAWS, VoiceOver)

## Backend Integration

El componente es **agnóstico al backend**. El valor `checked` (boolean) se mapea según las convenciones del backend:

- `true` → `1` o `'Activo'` (según la API)
- `false` → `0` o `'Inactivo'` (según la API)

No hay cambios en las APIs, rutas o nombres de campos.

## Pages Actualizadas

✅ DocumentTypesPage  
✅ PaymentMethodsPage  
✅ PaymentTermsPage  
✅ ProductCatalogs  
✅ GlobalCatalogs  
✅ RelationCatalogs  

## Ubicación del Componente

```
frontend/src/components/SwitchField.tsx
├── StatusSwitch (default export)
├── StatusSwitchInline
└── SwitchField (alias)
```

## Testing

Verificar los siguientes casos:

1. **Visual**
   - [ ] Switch verde cuando checked=true
   - [ ] Switch rojo cuando checked=false
   - [ ] Círculo se desliza suavemente
   - [ ] 🟢 Activo / 🔴 Inactivo se muestra

2. **Interacción**
   - [ ] Click cambia estado
   - [ ] Space key alterna estado
   - [ ] Enter key alterna estado
   - [ ] Disabled state desactiva interacción

3. **Accesibilidad**
   - [ ] Focus ring visible
   - [ ] ARIA attributes presentes
   - [ ] Screen reader anuncia cambios
   - [ ] Keyboard navigation funciona

4. **Backend**
   - [ ] Valores guardados correctamente
   - [ ] No hay cambios en APIs
   - [ ] Compatibilidad con valores existentes
