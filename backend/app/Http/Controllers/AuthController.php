<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[A-Za-z0-9._@-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'username.required' => 'Ingresa tu usuario o correo.',
            'username.min' => 'El usuario debe tener al menos 3 caracteres.',
            'username.regex' => 'El usuario solo puede contener letras, numeros, punto, guion, guion bajo o @.',
            'password.required' => 'Ingresa tu contrasena.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
        ]);

        $throttleKey = $this->throttleKey($request, $validated['username']);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'message' => 'Demasiados intentos. Intenta nuevamente en unos segundos.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        $user = User::query()
            ->where('username', $validated['username'])
            ->orWhere('email', $validated['username'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password_hash)) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'username' => ['Las credenciales no coinciden con nuestros registros.'],
            ]);
        }

        if ((int) $user->status !== 1) {
            return response()->json([
                'message' => 'Tu usuario esta inactivo. Contacta al administrador.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $plainToken = Str::random(80);
        $expiresAt = $validated['remember'] ?? false
            ? now()->addDays(30)
            : now()->addHours(12);

        DB::table('user_api_tokens')->insert([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => json_encode(['*']),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'expires_at' => $expiresAt,
            'created_at' => now(),
        ]);

        $user->forceFill(['last_login' => now()])->save();

        DB::table('audit_logs')->insert([
            'user_id' => $user->id,
            'table_name' => 'users',
            'action_type' => 'LOGIN',
            'record_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode(['username' => $user->username]),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Sesion iniciada correctamente.',
            'token_type' => 'Bearer',
            'token' => $plainToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $tokenId = $request->attributes->get('api_token_id');

        if ($tokenId) {
            DB::table('user_api_tokens')
                ->where('id', $tokenId)
                ->update(['revoked_at' => now()]);
        }

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }

    private function throttleKey(Request $request, string $username): string
    {
        return Str::lower($username).'|'.$request->ip();
    }

    private function userPayload(User $user): array
    {
        $company = DB::table('companies')
            ->select('id', 'name', 'legal_name', 'email', 'phone', 'status')
            ->where('id', $user->company_id)
            ->first();

        $branch = $user->branch_id
            ? DB::table('branches')
                ->select('id', 'name', 'phone', 'address', 'status')
                ->where('id', $user->branch_id)
                ->first()
            : null;

        $role = $user->role_id
            ? DB::table('roles')
                ->select('id', 'name', 'description')
                ->where('id', $user->role_id)
                ->first()
            : null;

        $permissions = $user->role_id
            ? DB::table('permissions')
                ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role_id', $user->role_id)
                ->orderBy('permissions.module_name')
                ->orderBy('permissions.action_name')
                ->get(['permissions.module_name', 'permissions.action_name'])
                ->map(fn ($permission) => [
                    'module' => $permission->module_name,
                    'action' => $permission->action_name,
                ])
                ->values()
            : collect();

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => (int) $user->status,
            'last_login' => $user->last_login instanceof Carbon
                ? $user->last_login->toIso8601String()
                : $user->last_login,
            'company' => $company ? (array) $company : null,
            'branch' => $branch ? (array) $branch : null,
            'role' => $role
                ? array_merge((array) $role, ['permissions' => $permissions->all()])
                : null,
        ];
    }
}
