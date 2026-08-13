<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Traits\StatusUpdateable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use StatusUpdateable;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeUsers($request);
        $companyId = (int) $request->user()->company_id;

        $search = trim((string) $request->query('search', ''));

        $query = User::query()
            ->with(['role:id,name', 'employee:id,user_id,code,first_name,last_name,position_id'])
            ->where('company_id', $companyId)
            ->orderBy('full_name');

        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->get()->map(fn (User $user) => $this->payload($user));

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeUsers($request);
        $companyId = (int) $request->user()->company_id;

        $user = $this->findUser($companyId, $id);

        return response()->json(['item' => $this->payload($user)]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $this->authenticatedUser($request);
        $this->authorizeUsers($request);

        $companyId = (int) $actor->company_id;
        $validated = $this->validatedUser($request, $companyId);

        $user = DB::transaction(function () use ($validated, $companyId) {
            return User::create([
                'company_id' => $companyId,
                'branch_id' => $validated['branch_id'] ?? null,
                'uuid' => (string) Str::uuid(),
                'username' => trim($validated['username']),
                'password_hash' => Hash::make($validated['password']),
                'full_name' => trim($validated['full_name']),
                'email' => $this->nullableText($validated['email'] ?? null),
                'phone' => $this->nullableText($validated['phone'] ?? null),
                'role_id' => $validated['role_id'] ?? null,
                'status' => $this->statusValue($validated['status'] ?? 1),
            ]);
        });

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'item' => $this->payload($user->fresh(['role', 'employee'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $this->authenticatedUser($request);
        $this->authorizeUsers($request);

        $companyId = (int) $actor->company_id;
        $user = $this->findUser($companyId, $id);
        $validated = $this->validatedUser($request, $companyId, $id);

        // Si se le esta quitando el rol de administrador de usuarios, hay que
        // asegurarse de que quede al menos otro activo — si no, la empresa se
        // queda sin nadie que pueda revertir el cambio.
        $newRoleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;
        $oldRoleId = $user->role_id !== null ? (int) $user->role_id : null;
        if ($oldRoleId !== $newRoleId && $this->managesUsers($oldRoleId) && ! $this->managesUsers($newRoleId)) {
            $this->guardLastUserManager($companyId, $user->id);
        }

        $user->update([
            'branch_id' => $validated['branch_id'] ?? null,
            'username' => trim($validated['username']),
            'full_name' => trim($validated['full_name']),
            'email' => $this->nullableText($validated['email'] ?? null),
            'phone' => $this->nullableText($validated['phone'] ?? null),
            'role_id' => $newRoleId,
        ]);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'item' => $this->payload($user->fresh(['role', 'employee'])),
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $actor = $this->authenticatedUser($request);
        $this->authorizeUsers($request);
        $companyId = (int) $actor->company_id;

        $user = $this->findUser($companyId, $id);
        $validated = $this->validateStatusUpdate($request);
        $activating = (bool) $validated['status'];

        if (! $activating) {
            if ((int) $actor->id === $user->id) {
                return response()->json([
                    'message' => 'No puedes desactivar tu propia cuenta.',
                ], 422);
            }

            if ($this->managesUsers($user->role_id)) {
                $guard = $this->guardLastUserManager($companyId, $user->id, dryRun: true);
                if ($guard !== null) {
                    return $guard;
                }
            }
        }

        return $this->changeStatus($user, $activating ? 1 : 0, 'status');
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $this->authorizeUsers($request);
        $companyId = (int) $request->user()->company_id;
        $user = $this->findUser($companyId, $id);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user->forceFill(['password_hash' => Hash::make($validated['password'])])->save();

        return response()->json(['message' => 'Contrasena restablecida correctamente.']);
    }

    /**
     * Genera un PIN de 4 digitos para ventas rapidas. Solo disponible si el
     * rol del usuario tiene el permiso usuarios/pin (ver SecuritySeeder, rol
     * "Ventas"). El PIN se devuelve en texto plano UNA sola vez; solo se
     * guarda su hash.
     */
    public function generatePin(Request $request, int $id): JsonResponse
    {
        $this->authorizeUsers($request);
        $companyId = (int) $request->user()->company_id;
        $user = $this->findUser($companyId, $id);

        if (! $this->roleHasPermission($user->role_id, 'usuarios', 'pin')) {
            return response()->json([
                'message' => 'El rol de este usuario no tiene habilitada la generacion de PIN. Asignale un rol con el permiso "usuarios / pin" (ej. Ventas) desde Roles.',
            ], 422);
        }

        $plainPin = $this->generateUniquePin($companyId, $user->id);

        $user->forceFill([
            'pin_hash' => Hash::make($plainPin),
            'pin_generated_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'PIN generado correctamente. Se muestra una sola vez: guardalo en un lugar seguro.',
            'pin' => $plainPin,
            'pin_generated_at' => $user->pin_generated_at->toIso8601String(),
        ]);
    }

    public function revokePin(Request $request, int $id): JsonResponse
    {
        $this->authorizeUsers($request);
        $companyId = (int) $request->user()->company_id;
        $user = $this->findUser($companyId, $id);

        $user->forceFill(['pin_hash' => null, 'pin_generated_at' => null])->save();

        return response()->json(['message' => 'PIN revocado correctamente.']);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'branch_id' => $user->branch_id,
            'role_id' => $user->role_id,
            'role' => $user->relationLoaded('role') && $user->role
                ? ['id' => $user->role->id, 'name' => $user->role->name]
                : null,
            'employee' => $user->relationLoaded('employee') && $user->employee
                ? [
                    'id' => $user->employee->id,
                    'code' => $user->employee->code,
                    'full_name' => $user->employee->full_name,
                ]
                : null,
            'status' => (int) $user->status,
            'status_label' => (int) $user->status === 1 ? 'Activo' : 'Inactivo',
            'has_pin' => ! empty($user->pin_hash),
            'pin_generated_at' => $user->pin_generated_at?->toIso8601String(),
            'can_generate_pin' => $this->roleHasPermission($user->role_id, 'usuarios', 'pin'),
            'last_login' => $user->last_login?->toIso8601String(),
        ];
    }

    private function validatedUser(Request $request, int $companyId, ?int $userId = null): array
    {
        $rules = [
            'username' => [
                'required', 'string', 'min:3', 'max:80', 'regex:/^[A-Za-z0-9._@-]+$/',
                Rule::unique('users', 'username')->where('company_id', $companyId)->ignore($userId),
            ],
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')->where('company_id', $companyId)],
            'status' => ['sometimes', Rule::in([1, 0, '1', '0', true, false])],
        ];

        if ($userId === null) {
            $rules['password'] = ['required', 'string', 'min:8', 'max:255'];
        }

        return $request->validate($rules, [
            'username.required' => 'Ingresa el nombre de usuario.',
            'username.regex' => 'Usa solo letras, numeros, punto, guion, guion bajo o @.',
            'username.unique' => 'Ya existe un usuario con ese nombre en esta empresa.',
            'full_name.required' => 'Ingresa el nombre completo.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
            'role_id.exists' => 'El rol seleccionado no existe en esta empresa.',
            'branch_id.exists' => 'La sucursal seleccionada no existe en esta empresa.',
        ]);
    }

    private function generateUniquePin(int $companyId, int $userId): string
    {
        $activeHashes = User::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $userId)
            ->whereNotNull('pin_hash')
            ->pluck('pin_hash');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = (string) random_int(1000, 9999);

            $collides = $activeHashes->contains(fn ($hash) => Hash::check($candidate, $hash));

            if (! $collides) {
                return $candidate;
            }
        }

        throw new \RuntimeException('No se pudo generar un PIN unico. Intenta nuevamente.');
    }

    private function roleHasPermission(?int $roleId, string $module, string $action): bool
    {
        if (! $roleId) {
            return false;
        }

        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.module_name', $module)
            ->where('permissions.action_name', $action)
            ->exists();
    }

    private function managesUsers(?int $roleId): bool
    {
        if (! $roleId) {
            return false;
        }

        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $roleId)
            ->where('permissions.module_name', 'usuarios')
            ->whereIn('permissions.action_name', ['manage', 'administrar'])
            ->exists();
    }

    /**
     * Evita dejar la empresa sin ningun usuario activo capaz de administrar
     * usuarios (ej. el ultimo Administrador). Si dryRun es true, devuelve la
     * respuesta de error en vez de abortar, para poder componerla desde
     * updateStatus() sin lanzar excepcion.
     */
    private function guardLastUserManager(int $companyId, int $excludingUserId, bool $dryRun = false): ?JsonResponse
    {
        $othersCanManage = User::query()
            ->where('company_id', $companyId)
            ->where('id', '!=', $excludingUserId)
            ->where('status', 1)
            ->get(['id', 'role_id'])
            ->contains(fn (User $candidate) => $this->managesUsers($candidate->role_id));

        if ($othersCanManage) {
            return null;
        }

        $message = 'No puedes dejar la empresa sin ningun usuario activo que pueda administrar usuarios. Asigna el rol de administrador a otra persona primero.';

        if ($dryRun) {
            return response()->json(['message' => $message], 422);
        }

        abort(422, $message);
    }

    private function statusValue(mixed $status): int
    {
        return in_array($status, [1, '1', true], true) ? 1 : 0;
    }

    private function findUser(int $companyId, int $id): User
    {
        $user = User::query()->with(['role:id,name', 'employee:id,user_id,code,first_name,last_name'])->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($user, 404, 'Usuario no encontrado.');

        return $user;
    }

    private function authorizeUsers(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para usuarios.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'usuarios')
            ->whereIn('permissions.action_name', ['manage', 'administrar', 'view', 'ver'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar usuarios.');
    }

    private function authenticatedUser(Request $request): object
    {
        $user = $request->user();

        abort_unless($user && $user->id && $user->company_id, 403, 'No hay usuario autenticado o empresa asociada.');

        return $user;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
