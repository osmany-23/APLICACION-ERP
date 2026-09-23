import { useRef, useState } from 'react';
import { FiImage, FiLoader, FiTrash2, FiUploadCloud, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../services/api';

const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

function validateFile(file: File): string | null {
  if (!ALLOWED_TYPES.includes(file.type)) {
    return 'La imagen debe ser JPG, JPEG, PNG o WEBP.';
  }
  if (file.size > MAX_FILE_SIZE) {
    return 'La imagen no puede superar los 10 MB.';
  }
  return null;
}

function getErrorMessage(error: unknown) {
  if (error instanceof ApiError) {
    const firstFieldError = error.errors ? Object.values(error.errors).flat()[0] : null;
    return firstFieldError || error.message;
  }
  return 'No se pudo completar la operacion.';
}

/**
 * Subida de una sola imagen (categoria, marca, etc.): reemplaza la imagen
 * actual al elegir un archivo nuevo. La entidad debe existir ya (uploadUrl
 * apunta a /catalogs/{catalogo}/{id}/image), por eso se deshabilita
 * mientras se esta creando un registro nuevo.
 */
export default function ImageUploadField({
  imageUrl,
  uploadUrl,
  deleteUrl,
  token,
  disabled = false,
  disabledHint = 'Guarda el registro para poder subir una imagen.',
  onUploaded,
}: {
  imageUrl: string;
  uploadUrl: string;
  deleteUrl: string;
  token: string | null;
  disabled?: boolean;
  disabledHint?: string;
  onUploaded: (imageUrl: string) => void;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  async function handleFileSelected(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || !token) return;

    const validationError = validateFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    setBusy(true);
    setError('');

    try {
      const formData = new FormData();
      formData.append('image', file);

      const response = await apiRequest<{ item: { image_url: string } }>(
        uploadUrl,
        { method: 'POST', body: formData },
        token,
      );
      onUploaded(response.item.image_url || '');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function handleRemove() {
    if (!token || !imageUrl) return;
    if (!window.confirm('¿Quitar la imagen actual?')) return;

    setBusy(true);
    setError('');

    try {
      const response = await apiRequest<{ item: { image_url: string } }>(
        deleteUrl,
        { method: 'DELETE' },
        token,
      );
      onUploaded(response.item.image_url || '');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  if (disabled) {
    return (
      <div className="flex min-h-32 items-center justify-center rounded-lg border border-dashed border-stroke bg-gray-50 p-4 text-center text-sm text-slate-400 dark:border-strokedark dark:bg-meta-4">
        {disabledHint}
      </div>
    );
  }

  return (
    <div>
      <div className="relative flex min-h-32 items-center justify-center overflow-hidden rounded-lg border border-dashed border-stroke bg-gray-50 p-4 dark:border-strokedark dark:bg-meta-4">
        {busy ? (
          <FiLoader className="h-8 w-8 animate-spin text-slate-400" />
        ) : imageUrl ? (
          <img src={imageUrl} alt="Imagen" className="max-h-32 w-full object-contain" />
        ) : (
          <div className="flex flex-col items-center gap-1.5 text-slate-400">
            <FiImage className="h-8 w-8" />
            <span className="text-xs">Sin imagen</span>
          </div>
        )}
      </div>

      <div className="mt-2 flex items-center gap-2">
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={busy}
          className="inline-flex items-center gap-1.5 rounded-lg border border-stroke px-3 py-1.5 text-xs font-semibold text-black transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-60 dark:border-strokedark dark:text-white"
        >
          <FiUploadCloud className="h-3.5 w-3.5" /> {imageUrl ? 'Reemplazar' : 'Subir imagen'}
        </button>
        {imageUrl && (
          <button
            type="button"
            onClick={handleRemove}
            disabled={busy}
            className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-500 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
          >
            <FiTrash2 className="h-3.5 w-3.5" /> Quitar
          </button>
        )}
      </div>

      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/webp"
        className="hidden"
        onChange={handleFileSelected}
      />

      {error && (
        <div className="mt-2 flex items-center justify-between rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">
          {error}
          <button type="button" onClick={() => setError('')}><FiX className="h-3.5 w-3.5" /></button>
        </div>
      )}
    </div>
  );
}
