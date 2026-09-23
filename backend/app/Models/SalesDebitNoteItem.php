<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDebitNoteItem extends Model
{
    protected $table = 'sales_debit_note_items';

    protected $fillable = [
        'sales_debit_note_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'tax',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'sales_debit_note_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'tax' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function salesDebitNote(): BelongsTo
    {
        return $this->belongsTo(SalesDebitNote::class);
    }

    // Ver nota en SalesReturnItem: no hay modelo Eloquent para "products".
}
