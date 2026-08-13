<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $table = 'positions';

    protected $fillable = [
        'company_id',
        'department_id',
        'name',
        'description',
        'base_salary',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'department_id' => 'integer',
        'base_salary' => 'decimal:4',
        'status' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
