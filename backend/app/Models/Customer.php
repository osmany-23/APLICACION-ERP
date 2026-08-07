<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'customers';

    protected $fillable = [
        'company_id',
        'customer_type_id',
        'code',
        'full_name',
        'business_name',
        'tax_id',
        'tax_id_type',
        'phone',
        'email',
        'address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'payment_term_id',
        'currency_id',
        'price_list_id',
        'credit_limit',
        'credit_days',
        'discount_rate',
        'birthday',
        'latitude',
        'longitude',
        'salesperson_id',
        'rating',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'customer_type_id' => 'integer',
        'payment_term_id' => 'integer',
        'currency_id' => 'integer',
        'price_list_id' => 'integer',
        'salesperson_id' => 'integer',
        'credit_limit' => 'decimal:4',
        'credit_days' => 'integer',
        'current_balance' => 'decimal:4',
        'discount_rate' => 'decimal:2',
        'birthday' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
        'rating' => 'integer',
        'status' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function accountsReceivable(): HasMany
    {
        return $this->hasMany(AccountsReceivable::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function availableCredit(): float
    {
        $limit = (float) $this->credit_limit;
        $balance = (float) $this->current_balance;

        return max(0.0, $limit - $balance);
    }
}
