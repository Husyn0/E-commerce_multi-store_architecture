<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'payment_provider' => $this->payment_provider,
            'subtotal_cents' => (int) $this->subtotal_cents,
            'tax_cents' => (int) $this->tax_cents,
            'shipping_cents' => (int) $this->shipping_cents,
            'discount_cents' => (int) $this->discount_cents,
            'total_cents' => (int) $this->total_cents,
            'refunded_total_cents' => (int) ($this->getAttribute('refunded_total_cents')
                ?? $this->refundedTotalCents()),
            'currency' => $this->currency,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'shipping_address' => $this->shipping_address_snapshot,
            'billing_address' => $this->billing_address_snapshot,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'paid_at' => $this->paid_at,
        ];
    }
}
