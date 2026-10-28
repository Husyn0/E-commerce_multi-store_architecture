<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'product_snapshot' => $this->product_snapshot,
            'quantity' => (int) $this->quantity,
            'unit_price_cents' => (int) $this->unit_price_cents,
            'total_cents' => (int) $this->total_cents,
        ];
    }
}
