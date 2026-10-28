<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class AuditController extends TenantApiController
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'action' => ['sometimes', 'string', 'max:120'],
            'entity_type' => ['sometimes', 'string', 'max:100'],
            'actor_id' => ['sometimes', 'uuid'],
            'source' => ['sometimes', 'in:web,cli,webhook'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::query()
            ->forTenant($this->tenantId())
            ->with('actor')
            ->when(isset($filters['action']), fn ($builder) => $builder->where('action', $filters['action']))
            ->when(isset($filters['entity_type']), fn ($builder) => $builder->where('entity_type', $filters['entity_type']))
            ->when(isset($filters['actor_id']), fn ($builder) => $builder->where('actor_id', $filters['actor_id']))
            ->when(isset($filters['source']), fn ($builder) => $builder->where('source', $filters['source']))
            ->when(isset($filters['from']), fn ($builder) => $builder->where('created_at', '>=', CarbonImmutable::parse($filters['from'])->utc()))
            ->when(isset($filters['to']), fn ($builder) => $builder->where('created_at', '<=', CarbonImmutable::parse($filters['to'])->utc()));

        if (isset($filters['cursor'])) {
            [$createdAt, $id] = $this->decodeCursor($filters['cursor']);
            $query->where(function ($builder) use ($createdAt, $id): void {
                $builder->where('created_at', '<', $createdAt)
                    ->orWhere(function ($tieBreaker) use ($createdAt, $id): void {
                        $tieBreaker->where('created_at', '=', $createdAt)
                            ->where('id', '<', $id);
                    });
            });
        }

        $limit = $filters['limit'] ?? 25;
        $rows = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit)->values();
        $lastItem = $items->last();

        return response()->json([
            'data' => AuditLogResource::collection($items),
            'next_cursor' => $hasMore && $lastItem !== null
                ? $this->encodeCursor(CarbonImmutable::parse($lastItem->created_at)->utc()->toISOString(), $lastItem->id)
                : null,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decodeCursor(string $cursor): array
    {
        try {
            $value = Crypt::decryptString($cursor);
        } catch (DecryptException) {
            throw ValidationException::withMessages([
                'cursor' => 'The cursor is invalid.',
            ]);
        }

        $parts = explode('|', $value, 2);
        if (count($parts) !== 2
            || ! is_numeric(strtotime($parts[0]))
            || ! preg_match('/^[0-9a-f-]{36}$/i', $parts[1])) {
            throw ValidationException::withMessages([
                'cursor' => 'The cursor is invalid.',
            ]);
        }

        return $parts;
    }

    private function encodeCursor(string $createdAt, string $id): string
    {
        return Crypt::encryptString($createdAt.'|'.$id);
    }
}
