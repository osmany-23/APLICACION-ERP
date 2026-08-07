<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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

        DB::table('user_api_tokens')
            ->where('id', $token->id)
            ->update(['last_used_at' => now()]);

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
        $timezone = DB::table('companies')
            ->where('id', $companyId)
            ->value('timezone');

        if (! $timezone) {
            return;
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set((string) $timezone);
    }
}
