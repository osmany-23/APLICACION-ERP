<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashCount extends Model
{
    protected $table = 'cash_counts';

    protected $fillable = [
        'cash_session_id',
        'type',
        'counted_by',
        'expected_amount',
        'counted_amount',
        'difference',
        'breakdown',
        'note',
    ];

    protected $casts = [
        'cash_session_id' => 'integer',
        'counted_by' => 'integer',
        'expected_amount' => 'decimal:4',
        'counted_amount' => 'decimal:4',
        'difference' => 'decimal:4',
        'breakdown' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }
}
