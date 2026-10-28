<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function web(
        TenantContext $context,
        User $actor,
        string $action,
        string $entityType,
        string $entityId,
        Request $request,
        ?array $metadata = null,
    ): void {
        $audit = new AuditLog;
        $audit->tenant_id = $context->tenant->id;
        $audit->actor_id = $actor->id;
        $audit->action = $action;
        $audit->entity_type = $entityType;
        $audit->entity_id = $entityId;
        $audit->source = 'web';
        $audit->host_type = $context->hostType;
        $audit->metadata = $metadata;
        $audit->ip_address = $request->ip();
        $audit->save();
    }
}
