import { useState, useCallback } from 'react';

interface StatusUpdateOptions {
  endpoint: string; // ej: '/api/settings/currencies/1/status'
  onSuccess?: (data: any) => void;
  onError?: (error: string) => void;
}

/**
 * Hook para actualizar el estado de registros via API
 * 
 * Uso:
 * const { updateStatus, isLoading } = useStatusUpdate({
 *   endpoint: `/api/settings/currencies/${id}/status`,
 *   onSuccess: (data) => {
 *     setItem({ ...item, is_active: data.status });
 *     showNotification('Estado actualizado correctamente');
 *   }
 * });
 * 
 * await updateStatus(newValue);
 */
export function useStatusUpdate(options: StatusUpdateOptions) {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const updateStatus = useCallback(
    async (status: boolean) => {
      setIsLoading(true);
      setError(null);

      try {
        const response = await fetch(options.endpoint, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${localStorage.getItem('api_token') || ''}`,
          },
          body: JSON.stringify({ status }),
        });

        if (!response.ok) {
          const errorData = await response.json().catch(() => ({}));
          const errorMessage = errorData.message || `Error al actualizar estado (${response.status})`;
          setError(errorMessage);
          options.onError?.(errorMessage);
          throw new Error(errorMessage);
        }

        const data = await response.json();
        
        if (data.success) {
          options.onSuccess?.(data.data || data);
          return data.data || data;
        } else {
          const errorMessage = data.message || 'Error desconocido al actualizar estado';
          setError(errorMessage);
          options.onError?.(errorMessage);
          throw new Error(errorMessage);
        }
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Error de red';
        setError(message);
        options.onError?.(message);
        throw err;
      } finally {
        setIsLoading(false);
      }
    },
    [options]
  );

  return { updateStatus, isLoading, error };
}
