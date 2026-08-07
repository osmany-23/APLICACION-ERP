<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class CompanySetting extends Model
{
    use HasFactory;

    protected $table = 'company_settings';

    protected $fillable = [
        'company_id',
        'group',
        'key',
        'value',
        'type',
        'is_encrypted',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_encrypted' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getValueAttribute(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if ((bool) ($this->attributes['is_encrypted'] ?? false)) {
            return Crypt::decryptString($value);
        }

        return match ($this->attributes['type'] ?? 'string') {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true) ?: [],
            default => $value,
        };
    }

    public function setValueAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;
            return;
        }

        $serialized = ($this->attributes['type'] ?? null) === 'json'
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) $value;

        $this->attributes['value'] = (bool) ($this->attributes['is_encrypted'] ?? false)
            ? Crypt::encryptString($serialized)
            : $serialized;
    }

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
