<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    protected $table = 'cash_registers';

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'description',
        'default_currency_id',
        'status',
        'authorization_threshold',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'default_currency_id' => 'integer',
        'authorization_threshold' => 'decimal:4',
        'created_by' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * Usuarios autorizados a operar esta caja. No confundir con el
     * "responsable" de una apertura (cash_sessions.opened_by): un usuario
     * puede estar autorizado sin haber abierto nunca la caja el mismo.
     */
    public function authorizedUsers(): BelongsToMany
    {
        // La tabla pivote solo tiene created_at (no updated_at), asi que no
        // usamos withTimestamps(): el created_at se fija a mano al hacer
        // attach() desde el servicio/controlador.
        return $this->belongsToMany(User::class, 'cash_register_users');
    }

    public function openSession(): ?CashSession
    {
        return $this->sessions()->where('status', 'ABIERTA')->latest('opened_at')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVA');
    }
}
