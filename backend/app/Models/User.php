<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'company_id',
        'branch_id',
        'uuid',
        'username',
        'password_hash',
        'full_name',
        'email',
        'phone',
        'role_id',
        'status',
        'last_login',
    ];

    // pin_hash se asigna deliberadamente fuera de mass-assignment (via
    // forceFill en UserController::generatePin) para que nunca llegue por
    // accidente desde un payload de update() general, igual que la
    // contrasena.
    protected $hidden = [
        'password_hash',
        'pin_hash',
    ];

    protected $casts = [
        'status' => 'integer',
        'last_login' => 'datetime',
        'pin_generated_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Devuelve la contraseña para que Laravel pueda autenticar al usuario.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}