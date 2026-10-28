<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'first_name' => $this->actor->first_name,
                'last_name' => $this->actor->last_name,
                'email' => $this->actor->email,
            ]),
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'source' => $this->source,
            'host_type' => $this->host_type,
            'metadata' => $this->metadata,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
