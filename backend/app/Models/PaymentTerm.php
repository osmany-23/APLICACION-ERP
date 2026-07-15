<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{
    protected $table = 'payment_terms';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'type',
        'cash',
        'credit',
        'advance',
        'days',
        'discount_percent',
        'discount_days',
        'late_fee_percent',
        'down_payment_percent',
        'allow_partial_payments',
        'installments',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'cash' => 'boolean',
        'credit' => 'boolean',
        'advance' => 'boolean',
        'days' => 'integer',
        'discount_percent' => 'float',
        'discount_days' => 'integer',
        'late_fee_percent' => 'float',
        'down_payment_percent' => 'float',
        'allow_partial_payments' => 'boolean',
        'installments' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
