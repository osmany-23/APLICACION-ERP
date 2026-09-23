<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    protected $table = 'cash_movements';

    protected $fillable = [
        'cash_session_id',
        'cash_register_id',
        'company_id',
        'branch_id',
        'terminal_id',
        'user_id',
        'type',
        'reason_id',
        'reason_text',
        'amount',
        'currency_id',
        'exchange_rate',
        'reference',
        'observation',
        'status',
        'requires_authorization',
        'authorized_by',
        'authorized_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'cash_session_id' => 'integer',
        'cash_register_id' => 'integer',
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'terminal_id' => 'integer',
        'user_id' => 'integer',
        'reason_id' => 'integer',
        'amount' => 'decimal:4',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'requires_authorization' => 'boolean',
        'authorized_by' => 'integer',
        'authorized_at' => 'datetime',
        'cancelled_by' => 'integer',
        'cancelled_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(CashMovementReason::class, 'reason_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVO');
    }
}
