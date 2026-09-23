<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashMovementReason extends Model
{
    protected $table = 'cash_movement_reasons';

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'requires_note',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'requires_note' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'reason_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Motivos visibles para una empresa: los globales (company_id null,
     * sembrados por el sistema) mas los propios de esa empresa.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where(function ($sub) use ($companyId) {
            $sub->whereNull('company_id')->orWhere('company_id', $companyId);
        });
    }
}
