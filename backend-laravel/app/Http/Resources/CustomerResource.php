<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($address) => [
                'id' => $address->id,
                'label' => $address->label,
                'recipient_name' => $address->recipient_name,
                'phone' => $address->phone,
                'line1' => $address->line1,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'postal_code' => $address->postal_code,
                'country_code' => $address->country_code,
                'is_default_shipping' => $address->is_default_shipping,
                'is_default_billing' => $address->is_default_billing,
            ])->values()),
            'orders' => $this->whenLoaded('orders', fn () => $this->orders->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_provider' => $order->payment_provider,
                'total_cents' => (int) $order->total_cents,
                'refunded_total_cents' => (int) ($order->refunded_total_cents ?? 0),
                'currency' => $order->currency,
                'created_at' => $order->created_at,
            ])->values()),
        ];
    }
}
