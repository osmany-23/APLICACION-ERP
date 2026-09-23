<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Sirve las imagenes almacenadas como BLOB en la tabla "images".
 *
 * Esta ruta es publica (fuera del middleware erp.auth) a proposito: un
 * <img src="..."> del navegador no puede mandar el header Authorization
 * Bearer que usa el resto de la API, y esta app no maneja cookies de
 * sesion. El identificador es un UUID v4 (122 bits de aleatoriedad, no
 * correlativo ni adivinable a partir del id numerico), el mismo patron que
 * usan proveedores como S3/Cloudinary para servir imagenes de catalogo: no
 * son datos confidenciales, son fotos de productos/marcas/logos que de
 * todas formas se muestran en pantallas de venta.
 */
class ImageController extends Controller
{
    public function show(Request $request, string $uuid): Response
    {
        $image = DB::table('images')->where('uuid', $uuid)->first();

        abort_unless($image, 404);

        $etag = '"'.$image->checksum.'"';

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->withHeaders([
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }

        return response($image->data, 200)->withHeaders([
            'Content-Type' => $image->mime_type,
            'Content-Length' => (string) $image->size_bytes,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Content-Disposition' => 'inline',
        ]);
    }
}
