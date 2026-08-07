<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfigurationBackup extends Model
{
    use HasFactory;

    protected $table = 'configuration_backups';

    protected $fillable = [
        'company_id',
        'file_name',
        'file_path',
        'file_size',
        'status',
        'notes',
        'created_by',
        'restored_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'file_size' => 'integer',
        'created_by' => 'integer',
        'restored_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
