import { useRef, useState } from 'react';
import { FiImage, FiLoader, FiStar, FiTrash2, FiUploadCloud, FiX } from 'react-icons/fi';
import { ApiError, apiRequest } from '../services/api';

export type ProductImageMeta = {
  id: number;
  uuid: string;
  url: string;
  width: number | null;
  height: number | null;
  size_bytes: number;
  is_primary: boolean;
  sort_order: number;
};

// Imagen todavia no subida: se elige mientras se esta CREANDO un producto
// (sin id todavia), se guarda en memoria con una vista previa local, y se
// sube de verdad recien cuando el producto se crea (ver Products.tsx).
export type StagedImage = {
  id: string;
  file: File;
  previewUrl: string;
};

export const MAX_PRODUCT_IMAGES = 5;
const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const MAX_FILE_SIZE = 10 * 1024 * 1024;

export function createStagedImage(file: File): StagedImage {
  return {
    id: `staged-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    file,
    previewUrl: URL.createObjectURL(file),
  };
}

export function revokeStagedImages(images: StagedImage[]) {
  images.forEach((image) => URL.revokeObjectURL(image.previewUrl));
}

function formatSize(bytes: number) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

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

export default function ProductImagesManager({
  productId,
  images,
  onImagesChange,
  stagedImages,
  onStagedImagesChange,
  token,
}: {
  productId: number | null;
  images: ProductImageMeta[];
  onImagesChange: (images: ProductImageMeta[]) => void;
  stagedImages: StagedImage[];
  onStagedImagesChange: (images: StagedImage[]) => void;
  token: string | null;
}) {
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState<number | string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Sin producto guardado todavia: se trabaja "en memoria" (staged) y se
  // sube todo junto cuando se guarde el producto. Con producto ya
  // existente, cada accion pega directo a la API.
  const isStaged = !productId;
  const count = isStaged ? stagedImages.length : images.length;
  const canAddMore = count < MAX_PRODUCT_IMAGES;

  async function handleFileSelected(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    const validationError = validateFile(file);
    if (validationError) {
      setError(validationError);
      return;
    }

    setError('');

    if (isStaged) {
      onStagedImagesChange([...stagedImages, createStagedImage(file)]);
      return;
    }

    if (!productId || !token) return;

    setUploading(true);

    try {
      const formData = new FormData();
      formData.append('image', file);

      const response = await apiRequest<{ images: ProductImageMeta[] }>(
        `/products/${productId}/images`,
        { method: 'POST', body: formData },
        token,
      );
      onImagesChange(response.images);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setUploading(false);
    }
  }

  function handleDeleteStaged(item: StagedImage) {
    URL.revokeObjectURL(item.previewUrl);
    onStagedImagesChange(stagedImages.filter((image) => image.id !== item.id));
  }

  function handleMakeFirstStaged(item: StagedImage) {
    if (stagedImages[0]?.id === item.id) return;
    onStagedImagesChange([item, ...stagedImages.filter((image) => image.id !== item.id)]);
  }

  async function handleDelete(image: ProductImageMeta) {
    if (!productId || !token) return;
    if (!window.confirm('¿Eliminar esta imagen del producto?')) return;

    setBusyId(image.id);
    setError('');

    try {
      const response = await apiRequest<{ images: ProductImageMeta[] }>(
        `/products/${productId}/images/${image.id}`,
        { method: 'DELETE' },
        token,
      );
      onImagesChange(response.images);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  async function handleSetPrimary(image: ProductImageMeta) {
    if (!productId || !token || image.is_primary) return;

    setBusyId(image.id);
    setError('');

    try {
      const response = await apiRequest<{ images: ProductImageMeta[] }>(
        `/products/${productId}/images/${image.id}/primary`,
        { method: 'POST' },
        token,
      );
      onImagesChange(response.images);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <span className="text-sm font-semibold text-black dark:text-white">Imagenes del producto</span>
        <span className="text-xs font-medium text-slate-400">{count}/{MAX_PRODUCT_IMAGES}</span>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
        {isStaged
          ? stagedImages.map((item, index) => (
              <div
                key={item.id}
                className={`group relative aspect-square overflow-hidden rounded-lg border bg-gray-50 dark:bg-meta-4 ${
                  index === 0 ? 'border-primary ring-2 ring-primary/40' : 'border-stroke dark:border-strokedark'
                }`}
              >
                <img src={item.previewUrl} alt="Imagen del producto (pendiente de subir)" className="h-full w-full object-contain p-1" />

                {index === 0 && (
                  <span className="absolute left-1 top-1 flex items-center gap-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white shadow">
                    <FiStar className="h-2.5 w-2.5" /> Principal
                  </span>
                )}

                <div className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-black/50 py-1 opacity-0 transition group-hover:opacity-100">
                  {index !== 0 && (
                    <button
                      type="button"
                      onClick={() => handleMakeFirstStaged(item)}
                      title="Marcar como principal"
                      className="flex h-6 w-6 items-center justify-center rounded-md bg-white/20 text-white hover:bg-white/30"
                    >
                      <FiStar className="h-3.5 w-3.5" />
                    </button>
                  )}
                  <button
                    type="button"
                    onClick={() => handleDeleteStaged(item)}
                    title="Quitar imagen"
                    className="flex h-6 w-6 items-center justify-center rounded-md bg-white/20 text-white hover:bg-red-500"
                  >
                    <FiTrash2 className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            ))
          : images.map((image) => (
              <div
                key={image.id}
                className={`group relative aspect-square overflow-hidden rounded-lg border bg-gray-50 dark:bg-meta-4 ${
                  image.is_primary ? 'border-primary ring-2 ring-primary/40' : 'border-stroke dark:border-strokedark'
                }`}
              >
                <img src={image.url} alt="Imagen del producto" className="h-full w-full object-contain p-1" />

                {image.is_primary && (
                  <span className="absolute left-1 top-1 flex items-center gap-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white shadow">
                    <FiStar className="h-2.5 w-2.5" /> Principal
                  </span>
                )}

                {busyId === image.id ? (
                  <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                    <FiLoader className="h-5 w-5 animate-spin text-white" />
                  </div>
                ) : (
                  <div className="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-black/50 py-1 opacity-0 transition group-hover:opacity-100">
                    {!image.is_primary && (
                      <button
                        type="button"
                        onClick={() => handleSetPrimary(image)}
                        title="Marcar como principal"
                        className="flex h-6 w-6 items-center justify-center rounded-md bg-white/20 text-white hover:bg-white/30"
                      >
                        <FiStar className="h-3.5 w-3.5" />
                      </button>
                    )}
                    <button
                      type="button"
                      onClick={() => handleDelete(image)}
                      title="Eliminar imagen"
                      className="flex h-6 w-6 items-center justify-center rounded-md bg-white/20 text-white hover:bg-red-500"
                    >
                      <FiTrash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                )}
              </div>
            ))}

        {canAddMore && (
          <button
            type="button"
            onClick={() => inputRef.current?.click()}
            disabled={uploading}
            className="flex aspect-square flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-stroke text-slate-400 transition hover:border-primary hover:text-primary disabled:cursor-not-allowed disabled:opacity-60 dark:border-strokedark"
          >
            {uploading ? (
              <FiLoader className="h-6 w-6 animate-spin" />
            ) : (
              <>
                <FiUploadCloud className="h-6 w-6" />
                <span className="text-[11px] font-semibold">Agregar</span>
              </>
            )}
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

      {count === 0 && !uploading && (
        <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-400">
          <FiImage className="h-3.5 w-3.5" /> Sin imagenes todavia. JPG, PNG o WEBP, hasta 10 MB cada una.
        </p>
      )}

      {error && (
        <div className="mt-2 flex items-center justify-between rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-500">
          {error}
          <button type="button" onClick={() => setError('')}><FiX className="h-3.5 w-3.5" /></button>
        </div>
      )}

      {isStaged && count > 0 && (
        <p className="mt-2 text-[11px] text-slate-400">
          Estas imagenes se subiran y optimizaran (WEBP) al guardar el producto. La marcada con <FiStar className="inline h-2.5 w-2.5" /> quedara como principal.
        </p>
      )}

      {!isStaged && count > 0 && (
        <p className="mt-2 text-[11px] text-slate-400">
          La imagen marcada con <FiStar className="inline h-2.5 w-2.5" /> es la que se muestra en listados, catalogo y punto de venta. {formatSize(images.reduce((sum, img) => sum + img.size_bytes, 0))} en total.
        </p>
      )}
    </div>
  );
}
