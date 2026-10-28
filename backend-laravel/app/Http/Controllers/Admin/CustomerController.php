<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends TenantApiController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'status' => ['sometimes', 'in:active,suspended,deleted'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $customers = User::query()
            ->where('tenant_id', $this->tenantId())
            ->where('role', 'customer')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['q']), function ($query) use ($filters): void {
                $term = '%'.addcslashes($filters['q'], '\\%_').'%';
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery->where('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);

        return CustomerResource::collection($customers);
    }

    public function show(string $customerId): CustomerResource
    {
        $customer = User::query()
            ->where('tenant_id', $this->tenantId())
            ->where('role', 'customer')
            ->findOrFail($customerId);

        return new CustomerResource($customer);
    }

    public function updateStatus(Request $request, string $customerId, AuditLogger $auditLogger): CustomerResource
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);

        $customer = DB::transaction(function () use ($auditLogger, $customerId, $data, $request): User {
            $customer = User::query()
                ->where('tenant_id', $this->tenantId())
                ->where('role', 'customer')
                ->lockForUpdate()
                ->findOrFail($customerId);

            if ($customer->status === $data['status']) {
                return $customer;
            }

            $customer->status = $data['status'];
            $customer->save();
            if ($customer->status === 'suspended') {
                $customer->sessions()
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                $customer->status === 'suspended' ? 'customer.suspended' : 'customer.updated',
                'user',
                $customer->id,
                $request,
                ['status' => $customer->status],
            );

            return $customer;
        });

        return new CustomerResource($customer->refresh());
    }
}
