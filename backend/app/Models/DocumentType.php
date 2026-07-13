<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentType extends Model
{
    protected $table = 'document_types';

    protected $fillable = [
        'code',
        'name',
        'prefix',
        'next_number',
        'is_active',
    ];

    protected $casts = [
        'next_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function generateDocumentNumber(self $documentType): string
    {
        return DB::transaction(function () use ($documentType): string {
            $row = DB::table($this->getTable())
                ->where('id', $documentType->getKey())
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new \RuntimeException('Tipo de documento no encontrado.');
            }

            $nextNumber = (int) ($row->next_number ?? 1);
            $prefix = trim((string) ($row->prefix ?? ''));
            $base = strtoupper($prefix !== '' ? $prefix : (string) ($row->code ?? ''));
            $documentNumber = sprintf('%s-%s', $base, str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT));

            DB::table($this->getTable())
                ->where('id', $documentType->getKey())
                ->update([
                    'next_number' => $nextNumber + 1,
                    'updated_at' => now(),
                ]);

            return $documentNumber;
        });
    }
}
