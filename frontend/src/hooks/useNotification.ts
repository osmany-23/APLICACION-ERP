import { useState, useCallback } from 'react';

export type NotificationType = 'success' | 'error' | 'info' | 'warning';

export interface Notification {
  id: string;
  message: string;
  type: NotificationType;
  duration?: number;
}

/**
 * Hook para manejar notificaciones
 * 
 * Uso:
 * const { notify, notifications } = useNotification();
 * 
 * notify('Estado actualizado', 'success');
 */
export function useNotification() {
  const [notifications, setNotifications] = useState<Notification[]>([]);

  const notify = useCallback(
    (message: string, type: NotificationType = 'info', duration: number = 3000) => {
      const id = Date.now().toString();
      const notification: Notification = { id, message, type, duration };

      setNotifications((prev) => [...prev, notification]);

      if (duration > 0) {
        setTimeout(() => {
          setNotifications((prev) => prev.filter((n) => n.id !== id));
        }, duration);
      }

      return id;
    },
    []
  );

  const removeNotification = useCallback((id: string) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  }, []);

  return { notify, notifications, removeNotification };
}
