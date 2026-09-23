<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Image extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'imageable_type',
        'imageable_id',
        'data',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'original_filename',
        'checksum',
        'is_primary',
        'sort_order',
        'uploaded_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
    ];

    // El binario nunca debe viajar por accidente dentro de un JSON normal
    // (listados, etc.); solo se lee explicitamente en el controlador que
    // transmite la imagen (ImageController::show).
    protected $hidden = ['data'];

    protected static function booted(): void
    {
        static::creating(function (Image $image) {
            if (! $image->uuid) {
                $image->uuid = (string) Str::uuid();
            }
        });
    }

    public function url(): string
    {
        return url('/api/images/'.$this->uuid);
    }

    public function toPublicArray(): array
    {
        return [
            'id' => (int) $this->id,
            'uuid' => $this->uuid,
            'url' => $this->url(),
            'width' => $this->width,
            'height' => $this->height,
            'size_bytes' => $this->size_bytes,
            'is_primary' => (bool) $this->is_primary,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
