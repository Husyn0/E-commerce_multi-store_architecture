# Orders and refunds manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Create
an order with [checkout-test.md](./checkout-test.md), then set these values
from its response:

```sh
export ORDER_ID='paste-order-id'
export ORDER_NUMBER='paste-order-number'
```

Admin calls require a valid `$ADMIN_ACCESS_TOKEN`; Storefront account-order
calls require a valid customer session in `$STORE_JAR`.

## O1. Admin order list and filters

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/orders"
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/orders?per_page=5&payment_status=requires_payment"
```

**Expect:** `200`, paginated orders, and `refunded_total_cents`. Filtering
should only affect the current tenant.

## O2. Admin order details

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/orders/$ORDER_ID"
```

**Expect:** `200`, order, line-item snapshot, refund list, and computed
`refunded_total_cents`.

## O3. Fulfillment status transition

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"status":"processing"}' \
  "$ADMIN_BASE/api/admin/orders/$ORDER_ID/status"
```

**Expect:** `200` for a valid transition. Try an invalid transition such as
`delivered` directly from `pending`; expect `409`.

## O4. Customer order history is scoped

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account/orders"
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account/orders/$ORDER_ID"
```

**Expect:** `200` only when authenticated as the customer who owns the order.
Unauthenticated requests return `401`; another customer's order must not be
returned. Customer order resources also include `refunded_total_cents`.

## O5. Issue refund with idempotency

Only attempt a refund after the order is confirmed `payment_status=succeeded`
by a valid payment provider/webhook event. Demo checkout can require a manually
signed demo webhook; see [webhook-test.md](./webhook-test.md).

```sh
export REFUND_KEY="manual-refund-$(date +%s)"
curl -i -X POST -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -H "Idempotency-Key: $REFUND_KEY" \
  -d '{"amount_cents":500,"reason":"Manual API test"}' \
  "$ADMIN_BASE/api/admin/orders/$ORDER_ID/refunds"
```

**Expect:** `202` for a new refund (provider may report pending), with no
provider credentials in the response. Repeat the same request and key; expect
the existing refund rather than a duplicate.

## O6. Reject over-refund and wrong order state

Try a refund larger than the remaining captured amount, or attempt to refund an
order that is not paid:

```sh
curl -i -X POST -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: manual-refund-too-large' \
  -d '{"amount_cents":999999999}' \
  "$ADMIN_BASE/api/admin/orders/$ORDER_ID/refunds"
```

**Expect:** `422` for an amount over the refundable balance, or `409` when the
order is not successfully paid.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| O1-O2 Admin list/details | | | |
| O3 status transition | | | |
| O4 customer ownership | | | |
| O5 refund/idempotency | | | |
| O6 over-refund/state rejection | | | |
