<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentMethodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'company_id' => $this->company_id !== null ? (int) $this->company_id : null,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'description' => $this->description !== null ? (string) $this->description : null,
            'type' => (string) $this->type,
            'cash' => (bool) $this->cash,
            'card' => (bool) $this->card,
            'bank' => (bool) $this->bank,
            'check' => (bool) $this->check,
            'digital_wallet' => (bool) $this->digital_wallet,
            'credit' => (bool) $this->credit,
            'other' => (bool) $this->other,
            'flags' => [
                'cash' => (bool) $this->cash,
                'card' => (bool) $this->card,
                'bank' => (bool) $this->bank,
                'check' => (bool) $this->check,
                'digital_wallet' => (bool) $this->digital_wallet,
                'credit' => (bool) $this->credit,
                'other' => (bool) $this->other,
            ],
            'requires_reference' => (bool) $this->requires_reference,
            'requires_bank' => (bool) $this->requires_bank,
            'requires_authorization' => (bool) $this->requires_authorization,
            'allow_change' => (bool) $this->allow_change,
            'allow_partial_payment' => (bool) $this->allow_partial_payment,
            'is_online' => (bool) $this->is_online,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
