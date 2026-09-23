<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota de Debito: incrementa lo que debe el cliente sobre una factura ya
 * emitida (cargo adicional/correccion), sin impacto de inventario (ver
 * DebitNoteService).
 */
class SalesDebitNote extends Model
{
    use SoftDeletes;

    protected $table = 'sales_debit_notes';

    protected $fillable = [
        'company_id',
        'branch_id',
        'customer_id',
        'sale_id',
        'debit_number',
        'debit_date',
        'status',
        'subtotal',
        'tax',
        'total',
        'journal_entry_id',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'customer_id' => 'integer',
        'sale_id' => 'integer',
        'debit_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax' => 'decimal:4',
        'total' => 'decimal:4',
        'journal_entry_id' => 'integer',
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
        return $this->hasMany(SalesDebitNoteItem::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
