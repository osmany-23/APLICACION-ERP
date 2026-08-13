<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CompanySettingsService;
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
    public function __construct(private readonly CompanySettingsService $settings)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[A-Za-z0-9._@-]+$/'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'username.required' => 'Ingresa tu usuario o correo.',
            'username.min' => 'El usuario debe tener al menos 3 caracteres.',
            'username.regex' => 'El usuario solo puede contener letras, numeros, punto, guion, guion bajo o @.',
            'password.required' => 'Ingresa tu contrasena.',
        ]);

        $user = User::query()
            ->where('username', $validated['username'])
            ->orWhere('email', $validated['username'])
            ->first();

        $security = $this->securitySettings($user);
        $passwordMinLength = (int) ($security['password_min_length'] ?? 8);

        if (strlen($validated['password']) < $passwordMinLength) {
            throw ValidationException::withMessages([
                'password' => ['La contrasena debe tener al menos '.$passwordMinLength.' caracteres.'],
            ]);
        }

        $throttleKey = $this->throttleKey($request, $validated['username']);
        $lockoutEnabled = (bool) ($security['lockout_enabled'] ?? true);
        $maxAttempts = (int) ($security['max_login_attempts'] ?? 5);
        $lockoutSeconds = max(60, (int) ($security['lockout_minutes'] ?? 15) * 60);

        if ($lockoutEnabled && RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return response()->json([
                'message' => 'Demasiados intentos. Intenta nuevamente en unos segundos.',
                'retry_after' => RateLimiter::availableIn($throttleKey),
            ], 429);
        }

        if (! $user || ! Hash::check($validated['password'], $user->password_hash)) {
            if ($lockoutEnabled) {
                RateLimiter::hit($throttleKey, $lockoutSeconds);
            }

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
            : now()->addMinutes((int) ($security['session_timeout_minutes'] ?? 720));

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

        if ($this->auditEnabled((int) $user->company_id)) {
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
        }

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

    /**
     * Autoservicio: cualquier usuario autenticado puede actualizar su propio
     * nombre/correo/telefono. A diferencia de UserController::update(), esto
     * NO exige el permiso "usuarios" (seria absurdo requerir permiso de
     * administrar usuarios solo para corregir tu propio numero de telefono)
     * y deliberadamente NO permite tocar username/role_id/status/company —
     * eso sigue siendo exclusivo de un administrador.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]{1,4}([\-\s][0-9]{1,4})*$/'],
        ], [
            'full_name.required' => 'Ingresa tu nombre completo.',
            'phone.regex' => 'El telefono solo puede tener numeros, espacios y guiones.',
        ]);

        $user->update([
            'full_name' => trim($validated['full_name']),
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    /**
     * Autoservicio: cambiar tu propia contrasena, exigiendo la actual como
     * confirmacion (no un simple "reset" de administrador). Reutiliza el
     * mismo largo minimo configurado en Seguridad que ya valida el login.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $security = $this->securitySettings($user);
        $passwordMinLength = (int) ($security['password_min_length'] ?? 8);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', "min:{$passwordMinLength}", 'confirmed'],
        ], [
            'current_password.required' => 'Ingresa tu contrasena actual.',
            'new_password.required' => 'Ingresa la nueva contrasena.',
            'new_password.min' => "La nueva contrasena debe tener al menos {$passwordMinLength} caracteres.",
            'new_password.confirmed' => 'La confirmacion no coincide con la nueva contrasena.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contrasena actual no es correcta.'],
            ]);
        }

        $user->forceFill(['password_hash' => Hash::make($validated['new_password'])])->save();

        return response()->json(['message' => 'Contrasena actualizada correctamente.']);
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

    private function securitySettings(?User $user): array
    {
        if ($user && $user->company_id) {
            return $this->settings->all((int) $user->company_id)['security'] ?? [];
        }

        $companyId = DB::table('companies')->orderBy('id')->value('id');

        if (! $companyId) {
            return [];
        }

        return $this->settings->all((int) $companyId)['security'] ?? [];
    }

    private function auditEnabled(int $companyId): bool
    {
        return (bool) $this->settings->get($companyId, 'system', 'audit_enabled', true);
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
            'created_at' => $user->created_at?->toIso8601String(),
            'company' => $company ? (array) $company : null,
            'branch' => $branch ? (array) $branch : null,
            'role' => $role
                ? array_merge((array) $role, ['permissions' => $permissions->all()])
                : null,
        ];
    }
}
