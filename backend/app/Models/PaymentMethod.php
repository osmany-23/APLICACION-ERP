<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'type',
        'cash',
        'card',
        'bank',
        'check',
        'digital_wallet',
        'credit',
        'other',
        'requires_reference',
        'requires_bank',
        'requires_authorization',
        'allow_change',
        'allow_partial_payment',
        'is_online',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'cash' => 'boolean',
        'card' => 'boolean',
        'bank' => 'boolean',
        'check' => 'boolean',
        'digital_wallet' => 'boolean',
        'credit' => 'boolean',
        'other' => 'boolean',
        'requires_reference' => 'boolean',
        'requires_bank' => 'boolean',
        'requires_authorization' => 'boolean',
        'allow_change' => 'boolean',
        'allow_partial_payment' => 'boolean',
        'is_online' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
