<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $table = 'sales_return_items';

    protected $fillable = [
        'sales_return_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'discount',
        'tax',
        'subtotal',
        'total',
    ];

    protected $casts = [
        'sales_return_id' => 'integer',
        'product_id' => 'integer',
        'variant_id' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    // No hay modelo Eloquent para "products" en este proyecto (se maneja
    // via DB::table('products'), ver SaleController::detailPayload() para
    // el mismo patron) — el nombre del producto se resuelve ahi mismo, no
    // con una relacion Eloquent.
}
