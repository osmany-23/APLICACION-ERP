<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    protected $fillable = [
        'uuid',
        'name',
        'legal_name',
        'short_name',
        'slogan',
        'description',
        'tax_id',
        'nrc',
        'commercial_registry',
        'business_activity',
        'tax_regime',
        'taxpayer_type',
        'phone',
        'mobile',
        'whatsapp',
        'email',
        'website',
        'fiscal_address',
        'commercial_address',
        'country',
        'department',
        'city',
        'full_address',
        'postal_code',
        'latitude',
        'longitude',
        'logo',
        'logo_dark',
        'favicon',
        'currency_id',
        'timezone',
        'locale',
        'date_format',
        'time_format',
        'status',
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'status' => 'integer',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
