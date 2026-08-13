<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $table = 'employees';

    protected $fillable = [
        'company_id',
        'code',
        'branch_id',
        'department_id',
        'position_id',
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'salary',
        'hire_date',
        'termination_date',
        'status',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'branch_id' => 'integer',
        'department_id' => 'integer',
        'position_id' => 'integer',
        'user_id' => 'integer',
        'salary' => 'decimal:4',
        'hire_date' => 'date',
        'termination_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }
}
