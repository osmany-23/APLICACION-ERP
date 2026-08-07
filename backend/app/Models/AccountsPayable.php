<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountsPayable extends Model
{
    protected $table = 'accounts_payable';

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_id',
        'accounting_account_id',
        'document_number',
        'doc_date',
        'due_date',
        'currency_id',
        'exchange_rate',
        'total_amount',
        'paid_amount',
        'balance',
        'status',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'supplier_id' => 'integer',
        'purchase_id' => 'integer',
        'accounting_account_id' => 'integer',
        'doc_date' => 'date',
        'due_date' => 'date',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'balance' => 'decimal:4',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class, 'applicable_id')
            ->where('applicable_type', 'accounts_payable');
    }

    /**
     * Aplica un abono a este documento: actualiza el saldo y el estado.
     * A diferencia de AccountsReceivable, no existe un saldo corriente
     * almacenado en `suppliers` (RelationController lo calcula on-the-fly
     * sumando accounts_payable.balance), asi que no hace falta sincronizar
     * ninguna otra tabla.
     */
    public function applyPayment(float $amount): void
    {
        $this->paid_amount = round((float) $this->paid_amount + $amount, 4);
        $this->balance = round((float) $this->total_amount - (float) $this->paid_amount, 4);

        if ($this->balance <= 0.0001) {
            $this->status = 'PAID';
            $this->balance = max($this->balance, 0);
        } elseif ((float) $this->paid_amount > 0) {
            $this->status = 'PARTIAL';
        } else {
            $this->status = 'PENDING';
        }

        $this->save();
    }
}
