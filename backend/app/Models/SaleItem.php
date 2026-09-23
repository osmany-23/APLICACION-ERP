<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    protected $table = 'sale_items';

    protected $fillable = [
        'sale_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount',
        'tax',
        'subtotal',
        'total',
        'has_warranty',
        'warranty_days',
        'warranty_period_unit',
        'warranty_type',
        'warranty_expires_at',
    ];

    protected $casts = [
        'sale_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
        'has_warranty' => 'boolean',
        'warranty_days' => 'integer',
        'warranty_expires_at' => 'date',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(SaleItemTax::class);
    }
}
