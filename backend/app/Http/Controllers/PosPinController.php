<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PosPinController extends Controller
{
    /**
     * Resuelve un PIN de 4 digitos al usuario vendedor al que pertenece,
     * dentro de la misma empresa que la sesion actual. NO inicia sesion ni
     * cambia permisos: solo identifica quien esta vendiendo para asociarlo a
     * sales.salesperson_id (ver SalesService::createSale). La sesion del
     * navegador sigue siendo la que autoriza la operacion.
     */
    public function resolve(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor && $actor->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        $validated = $request->validate([
            'pin' => ['required', 'string', 'digits:4'],
        ], [
            'pin.digits' => 'El PIN debe tener 4 digitos.',
        ]);

        $companyId = (int) $actor->company_id;
        $throttleKey = 'pos-pin|'.$companyId.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            return response()->json([
                'message' => 'Demasiados intentos de PIN. Espera un momento e intenta de nuevo.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $candidates = User::query()
            ->where('company_id', $companyId)
            ->where('status', 1)
            ->whereNotNull('pin_hash')
            ->get(['id', 'full_name', 'pin_hash', 'role_id']);

        $match = $candidates->first(fn (User $candidate) => Hash::check($validated['pin'], $candidate->pin_hash));

        if (! $match) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json(['message' => 'PIN invalido.'], 422);
        }

        RateLimiter::clear($throttleKey);

        return response()->json([
            'data' => [
                'id' => $match->id,
                'full_name' => $match->full_name,
            ],
        ]);
    }
}
