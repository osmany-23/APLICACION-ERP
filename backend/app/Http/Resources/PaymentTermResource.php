<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentTermResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'company_id' => $this->company_id !== null ? (int) $this->company_id : null,
            'code' => $this->code !== null ? (string) $this->code : null,
            'name' => (string) $this->name,
            'description' => $this->description !== null ? (string) $this->description : null,
            'type' => (string) $this->type,
            'cash' => (bool) $this->cash,
            'credit' => (bool) $this->credit,
            'advance' => (bool) $this->advance,
            'days' => (int) $this->days,
            'discount_percent' => (float) $this->discount_percent,
            'discount_days' => $this->discount_days !== null ? (int) $this->discount_days : null,
            'late_fee_percent' => $this->late_fee_percent !== null ? (float) $this->late_fee_percent : null,
            'down_payment_percent' => $this->down_payment_percent !== null ? (float) $this->down_payment_percent : null,
            'allow_partial_payments' => (bool) $this->allow_partial_payments,
            'installments' => $this->installments !== null ? (int) $this->installments : null,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
