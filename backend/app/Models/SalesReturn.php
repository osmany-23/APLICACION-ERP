<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota de Credito: se apoya en las tablas `sales_returns`/
 * `sales_return_items` que ya existian en el schema desde el inicio pero
 * nunca se usaron (ver CreditNoteService).
 */
class SalesReturn extends Model
{
    use SoftDeletes;

    protected $table = 'sales_returns';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'sale_id',
        'warehouse_id',
        'return_number',
        'return_date',
        'status',
        'subtotal',
        'tax',
        'total',
        'journal_entry_id',
        'cash_movement_id',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'customer_id' => 'integer',
        'sale_id' => 'integer',
        'warehouse_id' => 'integer',
        'return_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
        'journal_entry_id' => 'integer',
        'cash_movement_id' => 'integer',
        'created_by' => 'integer',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
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
