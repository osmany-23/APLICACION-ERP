<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';

    protected $fillable = [
        'company_id',
        'uuid',
        'code',
        'name',
        'phone',
        'mobile',
        'whatsapp',
        'email',
        'address',
        'country',
        'department',
        'city',
        'postal_code',
        'full_address',
        'contact_person',
        'latitude',
        'longitude',
        'is_headquarters',
        'headquarters_unique_key',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_headquarters' => 'boolean',
        'status' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeHeadquarters($query)
    {
        return $query->where('is_headquarters', true);
    }
}
