<?php

namespace App\Models;

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

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'status' => 'integer',
        'last_login' => 'datetime',
    ];

    /**
     * Devuelve la contraseña para que Laravel pueda autenticar al usuario.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}