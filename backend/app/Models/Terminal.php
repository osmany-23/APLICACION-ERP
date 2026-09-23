<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    protected $table = 'terminals';

    protected $fillable = [
        'company_id',
        'branch_id',
        'cash_register_id',
        'code',
        'name',
        'device_name',
        'ip_address',
        'user_agent',
        'os_info',
        'browser_info',
        'status',
        'last_seen_at',
        'current_user_id',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'cash_register_id' => 'integer',
        'last_seen_at' => 'datetime',
        'current_user_id' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function currentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVA');
    }
}
