<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';

    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_reference',
        'requires_bank',
        'is_active',
    ];

    protected $casts = [
        'requires_reference' => 'boolean',
        'requires_bank' => 'boolean',
        'is_active' => 'boolean',
    ];
}
