<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\OrderResource;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends TenantApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = $this->customer($request);

        return response()->json([
            'user' => $this->userData($user),
            'addresses' => Address::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Address $address): array => $this->addressData($address)),
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request): JsonResponse
    {
        $this->customer($request);
        $this->rejectUnexpectedKeys($request, ['first_name', 'last_name']);
        $data = $request->validate([
            'first_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        $user->fill($data);
        $user->save();

        return response()->json(['user' => $this->userData($user)])->header('Cache-Control', 'no-store');
    }

    public function orders(Request $request)
    {
        $user = $this->customer($request);
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $orders = Order::query()
            ->forTenant($this->tenantId())
            ->where('user_id', $user->id)
            ->withSum(['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')], 'amount_cents')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return OrderResource::collection($orders)->additional(['meta' => ['tenant_scoped' => true]]);
    }

    public function order(Request $request, string $orderId): OrderResource
    {
        $user = $this->customer($request);
        $order = Order::query()
            ->forTenant($this->tenantId())
            ->where('user_id', $user->id)
            ->with(['items', 'refunds'])
            ->withSum(['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')], 'amount_cents')
            ->whereKey($orderId)
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $data = $request->validate($this->addressRules());
        $address = DB::transaction(function () use ($data, $user): Address {
            $lockedUser = User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $address = new Address;
            $address->tenant_id = $this->tenantId();
            $address->user_id = $lockedUser->id;
            $this->fillAddress($address, $data);
            $this->applyDefaults($address, $lockedUser->id);
            $address->save();

            return $address;
        });

        return response()->json($this->addressData($address), 201)->header('Cache-Control', 'no-store');
    }

    public function updateAddress(Request $request, string $addressId): JsonResponse
    {
        $user = $this->customer($request);
        $data = $request->validate($this->addressRules(partial: true));
        $address = DB::transaction(function () use ($addressId, $data, $user): Address {
            $lockedUser = User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $address = Address::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $lockedUser->id)
                ->whereKey($addressId)
                ->lockForUpdate()
                ->firstOrFail();
            $this->fillAddress($address, $data);
            $this->applyDefaults($address, $lockedUser->id);
            $address->save();

            return $address;
        });

        return response()->json($this->addressData($address))->header('Cache-Control', 'no-store');
    }

    public function destroyAddress(Request $request, string $addressId): JsonResponse
    {
        $user = $this->customer($request);
        DB::transaction(function () use ($addressId, $user): void {
            User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            Address::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $user->id)
                ->whereKey($addressId)
                ->delete();
        });

        return response()->json(['deleted' => true]);
    }

    private function customer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === 'customer' && $user->tenant_id === $this->tenantId(), 401);

        return $user;
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email_verified' => $user->email_verified_at !== null,
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified_at !== null,
        ];
    }

    private function addressRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'recipient_name' => [$required, 'string', 'max:160'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'line1' => [$required, 'string', 'max:255'],
            'line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => [$required, 'string', 'max:120'],
            'state' => ['sometimes', 'nullable', 'string', 'max:120'],
            'postal_code' => [$required, 'string', 'max:32'],
            'country_code' => [$required, 'string', 'regex:/^[A-Za-z]{2}$/'],
            'is_default_shipping' => ['sometimes', 'boolean'],
            'is_default_billing' => ['sometimes', 'boolean'],
        ];
    }

    private function fillAddress(Address $address, array $data): void
    {
        $address->fill($data);
        if (isset($data['country_code'])) {
            $address->country_code = strtoupper($data['country_code']);
        }
    }

    private function applyDefaults(Address $address, string $userId): void
    {
        foreach (['is_default_shipping', 'is_default_billing'] as $field) {
            if ($address->{$field}) {
                Address::query()
                    ->forTenant($this->tenantId())
                    ->where('user_id', $userId)
                    ->where($field, true)
                    ->where('id', '<>', $address->id ?? '')
                    ->update([$field => false]);
            }
        }
    }

    private function addressData(Address $address): array
    {
        return [
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
            'is_default_shipping' => (bool) $address->is_default_shipping,
            'is_default_billing' => (bool) $address->is_default_billing,
        ];
    }

    private function rejectUnexpectedKeys(Request $request, array $allowed): void
    {
        abort_if(array_diff(array_keys($request->all()), $allowed) !== [], 422, 'The request contains fields that cannot be edited.');
    }
}
