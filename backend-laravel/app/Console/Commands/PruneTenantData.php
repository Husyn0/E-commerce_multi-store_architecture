<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantLifecycleService;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PruneTenantData extends Command
{
    protected $signature = 'tenant:prune-data
        {--tenant= : Limit pruning to one tenant UUID}
        {--dry-run : Report affected row counts without changing data}';

    protected $description = 'Prune expired tenant data and redact old webhook payloads.';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $lifecycle->assertEnabled();
        $tenantId = $this->option('tenant');
        if ($tenantId !== null && (! is_string($tenantId) || ! Tenant::query()->whereKey($tenantId)->exists())) {
            $this->error('The requested tenant does not exist.');

            return self::INVALID;
        }

        $retentionDays = (int) config('services.webhook_payload_retention_days');
        if ($retentionDays < 1) {
            throw new RuntimeException('WEBHOOK_PAYLOAD_RETENTION_DAYS must be at least one.');
        }

        $scope = $tenantId === null ? 'all tenants' : "tenant {$tenantId}";
        $this->info(($this->option('dry-run') ? 'Dry run for ' : 'Pruning ').$scope.'.');
        $now = now();
        $cutoff = $now->copy()->subDays($retentionDays);

        $counts = [
            'expired carts deleted' => $this->prune('carts', fn (Builder $query) => $query->where('expires_at', '<=', $now)),
            'expired or used verification codes deleted' => $this->prune(
                'verification_codes',
                fn (Builder $query) => $query->where(function (Builder $query) use ($now): void {
                    $query->where('expires_at', '<=', $now)->orWhereNotNull('used_at');
                }),
            ),
            'expired human verifications deleted' => $this->prune(
                'human_verifications',
                fn (Builder $query) => $query->where('expires_at', '<=', $now),
            ),
            'expired or revoked user sessions deleted' => $this->prune(
                'user_sessions',
                fn (Builder $query) => $query->where(function (Builder $query) use ($now): void {
                    $query->where('expires_at', '<=', $now)->orWhereNotNull('revoked_at');
                }),
            ),
            'expired checkout idempotency records deleted' => $this->prune(
                'checkout_idempotency_records',
                fn (Builder $query) => $query->where('expires_at', '<=', $now),
            ),
            'old webhook payloads redacted' => $this->redactWebhookPayloads($cutoff),
        ];

        foreach ($counts as $label => $count) {
            $this->line("{$count} {$label}.");
        }

        return self::SUCCESS;
    }

    private function prune(string $table, Closure $filter): int
    {
        $query = $this->scopedQuery($table);
        $filter($query);

        if ($this->option('dry-run')) {
            return $query->count();
        }

        return $this->deleteInBatches($table, $query);
    }

    private function redactWebhookPayloads(Carbon $cutoff): int
    {
        $query = $this->scopedQuery('payment_webhook_events')
            ->whereNotNull('payload')
            ->where('created_at', '<=', $cutoff);

        if ($this->option('dry-run')) {
            return $query->count();
        }

        $redacted = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use (&$redacted): void {
            $redacted += DB::table('payment_webhook_events')
                ->whereIn('id', $rows->pluck('id'))
                ->whereNotNull('payload')
                ->update(['payload' => null]);
        });

        return $redacted;
    }

    private function scopedQuery(string $table): Builder
    {
        $query = DB::table($table);
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    private function deleteInBatches(string $table, Builder $query): int
    {
        $deleted = 0;
        $query->orderBy('id')->chunkById(500, function ($rows) use (&$deleted, $table): void {
            $deleted += DB::table($table)->whereIn('id', $rows->pluck('id'))->delete();
        });

        return $deleted;
    }
}
