<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return $this->unauthorized();
        }

        $token = DB::table('user_api_tokens')
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $token || ($token->expires_at && Carbon::parse($token->expires_at)->isPast())) {
            return $this->unauthorized();
        }

        $user = User::query()->find($token->user_id);

        if (! $user || (int) $user->status !== 1) {
            return $this->unauthorized();
        }

        // Antes esto escribia en CADA peticion (hasta las lecturas GET),
        // una escritura de mas por cada llamada a la API. "Ultima
        // actividad" no necesita precision al segundo, asi que se
        // throttlea a como mucho una vez por minuto por token.
        $lastUsedAt = $token->last_used_at ? Carbon::parse($token->last_used_at) : null;

        if (! $lastUsedAt || $lastUsedAt->diffInSeconds(now()) >= 60) {
            DB::table('user_api_tokens')
                ->where('id', $token->id)
                ->update(['last_used_at' => now()]);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token_id', $token->id);
        $this->applyCompanyTimezone((int) $user->company_id);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json([
            'message' => 'No autenticado.',
        ], 401);
    }

    private function applyCompanyTimezone(int $companyId): void
    {
        // El timezone de una empresa practicamente nunca cambia; cachearlo
        // unos minutos evita una consulta a "companies" en cada peticion
        // autenticada del sistema (esta funcion corre en TODAS). Si se
        // edita el timezone en Configuracion, el cambio tarda como mucho
        // este TTL en reflejarse aqui, un costo aceptable a cambio de
        // ahorrarse la consulta en el 99% de las peticiones.
        $timezone = Cache::remember(
            "company_timezone_{$companyId}",
            300,
            fn () => DB::table('companies')->where('id', $companyId)->value('timezone'),
        );

        if (! $timezone) {
            return;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set((string) $timezone);
    }
}
