<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Catalogo global de solo lectura (paises + prefijo telefonico E.164).
     * Alimenta el selector de "Pais" y el prefijo del telefono en Clientes.
     * No es CRUD porque es data de referencia compartida entre todas las
     * empresas (no tiene company_id), igual que 'currencies'.
     */
    public function index(Request $request): JsonResponse
    {
        $countries = Country::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'iso2', 'iso3', 'name', 'phone_code']);

        return response()->json(['data' => $countries]);
    }
}
