<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItemTax extends Model
{
    protected $table = 'purchase_item_taxes';

    protected $fillable = [
        'purchase_item_id',
        'tax_id',
        'rate',
        'taxable_base',
        'tax_amount',
    ];

    protected $casts = [
        'purchase_item_id' => 'integer',
        'tax_id' => 'integer',
        'rate' => 'decimal:4',
        'taxable_base' => 'decimal:4',
        'tax_amount' => 'decimal:4',
    ];

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
