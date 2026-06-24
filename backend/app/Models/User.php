<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const UPDATED_AT = null;

    /**
     * The column name of the password field used during authentication.
     *
     * @var string
     */
    protected $authPasswordName = 'password_hash';

    /**
     * Remember tokens are not part of the imported ERP schema.
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login' => 'datetime',
            'created_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }
}
