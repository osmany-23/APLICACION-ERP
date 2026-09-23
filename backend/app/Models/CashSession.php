<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $table = 'cash_sessions';

    protected $fillable = [
        'company_id',
        'branch_id',
        'cash_register_id',
        'terminal_id',
        'opened_by',
        'closed_by',
        'status',
        'currency_id',
        'exchange_rate',
        'opening_amount',
        'opening_breakdown',
        'opening_note',
        'opened_at',
        'opening_ip',
        'expected_cash',
        'counted_cash',
        'cash_difference',
        'closing_breakdown',
        'closing_note',
        'closed_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'cash_register_id' => 'integer',
        'terminal_id' => 'integer',
        'opened_by' => 'integer',
        'closed_by' => 'integer',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'opening_amount' => 'decimal:4',
        'opening_breakdown' => 'array',
        'opened_at' => 'datetime',
        'expected_cash' => 'decimal:4',
        'counted_cash' => 'decimal:4',
        'cash_difference' => 'decimal:4',
        'closing_breakdown' => 'array',
        'closed_at' => 'datetime',
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

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function counts(): HasMany
    {
        return $this->hasMany(CashCount::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'ABIERTA');
    }
}
