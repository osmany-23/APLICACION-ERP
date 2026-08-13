<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'company_id',
        'name',
        'manager_employee_id',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'manager_employee_id' => 'integer',
        'status' => 'boolean',
    ];

    public function managerEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
