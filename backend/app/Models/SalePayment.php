<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    protected $table = 'sale_payments';

    protected $fillable = [
        'company_id',
        'sale_id',
        'payment_method_id',
        'amount',
        'cash_session_id',
        'cash_movement_id',
        'reference',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'sale_id' => 'integer',
        'payment_method_id' => 'integer',
        'amount' => 'decimal:4',
        'cash_session_id' => 'integer',
        'cash_movement_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
