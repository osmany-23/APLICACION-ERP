<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'company_id',
        'name',
        'description',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Comprueba si el rol tiene el permiso module_name+action_name indicado.
     * Util para gatillar funcionalidad (ej. "usuarios"/"pin") sin repetir
     * la consulta join en cada controlador.
     */
    public function hasPermission(string $module, string $action): bool
    {
        return $this->permissions()
            ->where('module_name', $module)
            ->where('action_name', $action)
            ->exists();
    }
}
