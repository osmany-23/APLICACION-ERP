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
        'shipping',
        'tax',
        'total',
        'paid_amount',
        'balance_due',
        'currency_id',
        'exchange_rate',
        'payment_reference',
        'amount_tendered',
        'amount_tendered_currency_id',
        'amount_tendered_base',
        'amount_tendered_foreign',
        'change_amount',
        'ir_withholding_rate',
        'ir_withholding_amount',
        'accounts_receivable_id',
        'journal_entry_id',
        'notes',
        'created_by',
        'salesperson_id',
        'cash_session_id',
        'payment_confirmed_at',
        'payment_confirmed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_authorized_by',
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
        'shipping' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'balance_due' => 'decimal:4',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:8',
        'amount_tendered' => 'decimal:4',
        'amount_tendered_currency_id' => 'integer',
        'amount_tendered_base' => 'decimal:4',
        'amount_tendered_foreign' => 'decimal:4',
        'change_amount' => 'decimal:4',
        'ir_withholding_rate' => 'decimal:2',
        'ir_withholding_amount' => 'decimal:4',
        'accounts_receivable_id' => 'integer',
        'journal_entry_id' => 'integer',
        'created_by' => 'integer',
        'salesperson_id' => 'integer',
        'cash_session_id' => 'integer',
        'payment_confirmed_at' => 'datetime',
        'payment_confirmed_by' => 'integer',
        'cancelled_at' => 'datetime',
        'cancelled_by' => 'integer',
        'cancellation_authorized_by' => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cancellationAuthorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancellation_authorized_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(SalesDebitNote::class);
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
