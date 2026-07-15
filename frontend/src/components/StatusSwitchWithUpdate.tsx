import React, { useCallback } from 'react';
import { StatusSwitchInline } from './SwitchField';

interface StatusSwitchWithUpdateProps {
  id: number;
  checked: boolean;
  endpoint: string;
  onStatusChange: (id: number, newStatus: boolean, updatedData?: any) => void;
  onError?: (error: string) => void;
  disabled?: boolean;
}

/**
 * StatusSwitchWithUpdate - Componente que combina StatusSwitchInline con actualización automática via API
 * 
 * Uso:
 * <StatusSwitchWithUpdate
 *   id={item.id}
 *   checked={item.is_active}
 *   endpoint={`/api/settings/document-types/${item.id}/status`}
 *   onStatusChange={(id, newStatus) => {
 *     setItems(items.map(i => i.id === id ? { ...i, is_active: newStatus } : i));
 *   }}
 * />
 */
export function StatusSwitchWithUpdate({
  id,
  checked,
  endpoint,
  onStatusChange,
  onError,
  disabled = false,
}: StatusSwitchWithUpdateProps) {
  const [isUpdating, setIsUpdating] = React.useState(false);
  const [localChecked, setLocalChecked] = React.useState(checked);

  const handleChange = useCallback(
    async (newValue: boolean) => {
      setIsUpdating(true);
      setLocalChecked(newValue);

      try {
        const token = localStorage.getItem('api_token');
        const response = await fetch(endpoint, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${token || ''}`,
          },
          body: JSON.stringify({ status: newValue }),
        });

        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          const errorMessage = errorData.message || `Error al actualizar estado`;
          
          // Revertir cambio visual
          setLocalChecked(!newValue);
          onError?.(errorMessage);
          return;
        }

        const data = await response.json();
        
        if (data.success) {
          onStatusChange(id, newValue, data.data);
        } else {
          setLocalChecked(!newValue);
          const errorMessage = data.message || 'Error al actualizar estado';
          onError?.(errorMessage);
        }
      } catch (error) {
        // Revertir cambio visual
        setLocalChecked(!newValue);
        const message = error instanceof Error ? error.message : 'Error de red';
        onError?.(message);
      } finally {
        setIsUpdating(false);
      }
    },
    [id, endpoint, onStatusChange, onError]
  );

  return (
    <StatusSwitchInline
      checked={localChecked}
      onChange={handleChange}
      disabled={disabled || isUpdating}
      ariaLabel={`Estado del registro ${id}`}
    />
  );
}
