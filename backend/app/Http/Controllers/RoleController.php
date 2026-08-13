<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRoles($request);
        $companyId = (int) $request->user()->company_id;

        $roles = Role::query()
            ->where('company_id', $companyId)
            ->withCount('users')
            ->with('permissions:id,module_name,action_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->payload($role));

        return response()->json(['data' => $roles]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorizeRoles($request);
        $companyId = (int) $request->user()->company_id;

        $role = $this->findRole($companyId, $id);

        return response()->json(['item' => $this->payload($role)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->authorizeRoles($request);

        $companyId = (int) $user->company_id;
        $validated = $this->validatedRole($request, $companyId);

        $role = Role::create([
            'company_id' => $companyId,
            'name' => trim($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
        ]);

        if (! empty($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'item' => $this->payload($role->fresh(['permissions', 'users'])),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeRoles($request);
        $companyId = (int) $request->user()->company_id;

        $role = $this->findRole($companyId, $id);
        $validated = $this->validatedRole($request, $companyId, $id);

        $role->update([
            'name' => trim($validated['name']),
            'description' => $this->nullableText($validated['description'] ?? null),
        ]);

        if (array_key_exists('permission_ids', $validated)) {
            $role->permissions()->sync($validated['permission_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'item' => $this->payload($role->fresh(['permissions'])),
        ]);
    }

    public function syncPermissions(Request $request, int $id): JsonResponse
    {
        $this->authorizeRoles($request);
        $companyId = (int) $request->user()->company_id;
        $role = $this->findRole($companyId, $id);

        $validated = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        return response()->json([
            'message' => 'Permisos actualizados correctamente.',
            'item' => $this->payload($role->fresh(['permissions'])),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->authorizeRoles($request);
        $companyId = (int) $request->user()->company_id;
        $role = $this->findRole($companyId, $id);

        $usersCount = DB::table('users')->where('role_id', $id)->count();

        if ($usersCount > 0) {
            return response()->json([
                'message' => "No se puede eliminar el rol porque tiene {$usersCount} usuario(s) asignado(s). Reasignalos a otro rol primero.",
            ], 409);
        }

        try {
            $role->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'No se puede eliminar porque tiene registros relacionados.'], 409);
        }

        return response()->json(['message' => 'Rol eliminado correctamente.', 'deleted' => true]);
    }

    private function payload(Role $role): array
    {
        $permissions = $role->permissions->map(fn (Permission $permission) => [
            'id' => $permission->id,
            'module_name' => $permission->module_name,
            'action_name' => $permission->action_name,
        ])->values();

        // users_count viene precargado via withCount('users') en index(); en
        // el resto de acciones (store/update/syncPermissions) se calcula al
        // vuelo porque el rol recien creado/editado no paso por esa consulta.
        $usersCount = $role->users_count ?? $role->users()->count();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'users_count' => (int) $usersCount,
            'permissions' => $permissions,
            'permission_ids' => $permissions->pluck('id')->values(),
        ];
    }

    private function validatedRole(Request $request, int $companyId, ?int $roleId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ], [
            'name.required' => 'Ingresa el nombre del rol.',
        ]);
    }

    private function findRole(int $companyId, int $id): Role
    {
        $role = Role::query()->where('company_id', $companyId)->where('id', $id)->first();

        abort_unless($role, 404, 'Rol no encontrado.');

        return $role;
    }

    private function authorizeRoles(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->company_id, 403, 'No hay empresa asociada al usuario autenticado.');

        if (! $user->role_id) {
            abort(403, 'No tienes un rol con permisos para roles.');
        }

        $allowed = DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->where('permissions.module_name', 'roles')
            ->whereIn('permissions.action_name', ['manage', 'administrar', 'view', 'ver'])
            ->exists();

        abort_unless($allowed, 403, 'No tienes permiso para administrar roles.');
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
