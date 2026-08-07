<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItemTax extends Model
{
    protected $table = 'sale_item_taxes';

    protected $fillable = [
        'sale_item_id',
        'tax_id',
        'rate',
        'taxable_base',
        'tax_amount',
    ];

    protected $casts = [
        'sale_item_id' => 'integer',
        'tax_id' => 'integer',
        'rate' => 'decimal:4',
        'taxable_base' => 'decimal:4',
        'tax_amount' => 'decimal:4',
    ];

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
