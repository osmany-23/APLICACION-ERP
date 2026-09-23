<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Convierte cualquier imagen subida (JPG, PNG o WEBP) en un binario WEBP
 * optimizado, listo para guardarse como BLOB en MySQL.
 *
 * Reglas de negocio (ver spec del modulo de imagenes):
 *  - Nunca confia en la extension ni en el "Content-Type" que manda el
 *    navegador: siempre valida el MIME real leyendo el contenido del
 *    archivo (fileinfo/getimagesize).
 *  - Redimensiona (sin deformar) si excede 1600x1600, preservando aspect
 *    ratio; nunca agranda una imagen mas pequeña.
 *  - Re-codifica siempre a WEBP calidad ~85, incluso si ya era WEBP, para
 *    garantizar un peso optimizado y consistente.
 *  - Corrige la orientacion EXIF de fotos JPG tomadas con celular antes de
 *    redimensionar, para que no queden "acostadas".
 */
class ImageProcessingService
{
    public const MAX_DIMENSION = 1600;

    public const WEBP_QUALITY = 85;

    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Procesa un archivo subido por HTTP (multipart/form-data).
     *
     * @return array{data:string,mime_type:string,width:int,height:int,size_bytes:int,checksum:string,original_filename:string}
     */
    public function process(UploadedFile $file): array
    {
        // getMimeType() usa la extension fileinfo sobre el contenido real
        // del archivo temporal (no la extension ni el header que mando el
        // navegador), que es exactamente la validacion "MIME real" pedida.
        $mime = (string) $file->getMimeType();
        $binary = file_get_contents($file->getRealPath());

        if ($binary === false) {
            throw ValidationException::withMessages(['image' => 'No se pudo leer el archivo subido.']);
        }

        return $this->processBinary($binary, $mime, $file->getClientOriginalName());
    }

    /**
     * Procesa binario crudo ya en memoria (usado tambien por el backfill de
     * imagenes legadas, que descarga desde una URL externa en vez de recibir
     * un UploadedFile de un formulario).
     *
     * @return array{data:string,mime_type:string,width:int,height:int,size_bytes:int,checksum:string,original_filename:string}
     */
    public function processBinary(string $binary, ?string $declaredMime, string $originalFilename = 'imagen'): array
    {
        // No confiamos en $declaredMime (puede venir de un header HTTP o de
        // la extension del archivo): se vuelve a detectar desde el propio
        // contenido con finfo, que es la unica fuente confiable.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->buffer($binary) ?: $declaredMime;

        if (! $realMime || ! in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'image' => 'El archivo debe ser una imagen valida en formato JPG, JPEG, PNG o WEBP.',
            ]);
        }

        // Doble verificacion con getimagesizefromstring(): un archivo puede
        // tener bytes iniciales que "parezcan" una imagen pero estar
        // corrupto o truncado; getimagesize confirma que es decodificable.
        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw ValidationException::withMessages([
                'image' => 'El archivo no es una imagen valida o esta corrupto.',
            ]);
        }

        // ALLOWED_MIME_TYPES ya sirvio para validar que el MIME real sea
        // uno de los 3 soportados; para decodificar en si se usa la
        // variante *fromstring de GD, universal para JPG/PNG/WEBP.
        $source = @imagecreatefromstring($binary);

        if (! $source) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo procesar la imagen. Verifica que el archivo no este danado.',
            ]);
        }

        if ($realMime === 'image/jpeg') {
            $source = $this->applyExifOrientation($source, $binary);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = self::MAX_DIMENSION;

        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $width = max(1, (int) round($width * $ratio));
            $height = max(1, (int) round($height * $ratio));
        }

        // WEBP en GD exige un lienzo truecolor: algunos PNG/GIF se decodifican
        // en modo "paleta" (imageistruecolor() === false) y imagewebp() falla
        // con "Palette image not supported by webp" si se le pasa tal cual.
        // Por eso SIEMPRE se copia a un lienzo truecolor nuevo (aunque no haga
        // falta redimensionar), preservando la transparencia.
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);
        $source = $canvas;

        ob_start();
        imagewebp($source, null, self::WEBP_QUALITY);
        $webpData = ob_get_clean();
        imagedestroy($source);

        if ($webpData === false || $webpData === '') {
            throw ValidationException::withMessages([
                'image' => 'No se pudo optimizar la imagen a WEBP.',
            ]);
        }

        return [
            'data' => $webpData,
            'mime_type' => 'image/webp',
            'width' => $width,
            'height' => $height,
            'size_bytes' => strlen($webpData),
            'checksum' => hash('sha256', $webpData),
            'original_filename' => mb_substr($originalFilename, 0, 255),
        ];
    }

    /**
     * Lee el tag EXIF "Orientation" de una foto JPG (comun en celulares) y
     * rota/refleja la imagen para que quede visualmente correcta antes de
     * redimensionar/comprimir. Si no hay datos EXIF o el tag no aplica,
     * devuelve el recurso de imagen sin cambios.
     *
     * @param \GdImage $source
     * @return \GdImage
     */
    private function applyExifOrientation($source, string $binary)
    {
        if (! function_exists('exif_read_data')) {
            return $source;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $binary);
        rewind($stream);
        $exif = @exif_read_data($stream, null, false);
        fclose($stream);

        $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;

        switch ($orientation) {
            case 2:
                imageflip($source, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $source = imagerotate($source, 180, 0);
                break;
            case 4:
                imageflip($source, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $source = imagerotate($source, -90, 0);
                imageflip($source, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $source = imagerotate($source, -90, 0);
                break;
            case 7:
                $source = imagerotate($source, 90, 0);
                imageflip($source, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $source = imagerotate($source, 90, 0);
                break;
        }

        return $source;
    }
}
