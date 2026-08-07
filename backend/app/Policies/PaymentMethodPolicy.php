<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PaymentMethodPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return (int) $user->status !== 1 ? false : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, ['index', 'ver', 'manage', 'administrar']);
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, ['store', 'crear', 'manage', 'administrar']);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, ['update', 'editar', 'status', 'manage', 'administrar']);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->hasPermission($user, ['destroy', 'eliminar', 'manage', 'administrar']);
    }

    private function hasPermission(User $user, array $actions): bool
    {
        if (! $user->role_id) {
            return false;
        }

        $role = DB::table('roles')->where('id', $user->role_id)->first();

        if ($role && strcasecmp((string) $role->name, 'Administrador') === 0) {
            return true;
        }

        return DB::table('role_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $user->role_id)
            ->whereIn('permissions.module_name', ['payment_methods', 'settings', 'global_catalogs'])
            ->whereIn('permissions.action_name', $actions)
            ->exists();
    }
}
