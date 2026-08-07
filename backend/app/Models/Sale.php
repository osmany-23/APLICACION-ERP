<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'document_type_id',
        'payment_term_id',
        'payment_method_id',
        'sale_number',
        'sale_date',
        'status',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'balance_due',
        'currency_id',
        'exchange_rate',
        'accounts_receivable_id',
        'journal_entry_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'warehouse_id' => 'integer',
        'customer_id' => 'integer',
        'document_type_id' => 'integer',
        'payment_term_id' => 'integer',
        'payment_method_id' => 'integer',
        'sale_date' => 'date',
        'subtotal' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'balance_due' => 'decimal:4',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'accounts_receivable_id' => 'integer',
        'journal_entry_id' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function accountsReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountsReceivable::class);
    }

    public function scopeCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
