<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use SoftDeletes;

    protected $table = 'purchases';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'payment_term_id',
        'payment_method_id',
        'purchase_number',
        'purchase_date',
        'status',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'balance_due',
        'currency_id',
        'exchange_rate',
        'accounts_payable_id',
        'journal_entry_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'warehouse_id' => 'integer',
        'supplier_id' => 'integer',
        'payment_term_id' => 'integer',
        'payment_method_id' => 'integer',
        'purchase_date' => 'date',
        'subtotal' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'balance_due' => 'decimal:4',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'accounts_payable_id' => 'integer',
        'journal_entry_id' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class);
    }

    public function scopeCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
