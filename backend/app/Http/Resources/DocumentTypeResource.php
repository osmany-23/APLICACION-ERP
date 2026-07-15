<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'prefix' => $this->prefix !== null ? (string) $this->prefix : null,
            'next_number' => (int) $this->next_number,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
