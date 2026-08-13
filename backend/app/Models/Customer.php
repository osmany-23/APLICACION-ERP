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
        'residency_type',
        'gender',
        'sales_type',
        'phone',
        'phone_country_id',
        'has_landline',
        'landline_phone',
        'email',
        'address',
        'country_id',
        'city',
        'contact_person',
        'contact_phone',
        'contact_email',
        'payment_term_id',
        'currency_id',
        'price_list_id',
        'credit_limit',
        'credit_days',
        'applies_late_fee',
        'late_fee_percentage',
        'late_fee_period_unit',
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
        'country_id' => 'integer',
        'phone_country_id' => 'integer',
        'has_landline' => 'boolean',
        'credit_limit' => 'decimal:4',
        'credit_days' => 'integer',
        'current_balance' => 'decimal:4',
        'applies_late_fee' => 'boolean',
        'late_fee_percentage' => 'decimal:2',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function phoneCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'phone_country_id');
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
