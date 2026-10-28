# Admin dashboard manual checks

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Sign in
as Admin and set `$ADMIN_ACCESS_TOKEN`.

## D1. Load tenant dashboard data

```sh
curl -i \
  -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/dashboard"
```

**Expect:** `200`, `Cache-Control: no-store`, and a JSON object with:

- `period.from`, `period.to`, `period.timezone` (`UTC`), and `period.currency`
- `sales.total_cents`, `sales.order_count`, and seven `sales.by_day` entries
- `pending_fulfillment_count`
- `recent_orders` (up to five tenant orders)

The reporting interval is the last seven UTC calendar days, ending at the
current time. `sales` includes persisted order totals only where
`payment_status=succeeded` and `status!=cancelled`. It does not subtract
refunds from paid sales; refund totals remain separately available on order
details as `refunded_total_cents`. Pending fulfillment counts paid,
non-cancelled orders in `pending` or `processing`.

## D2. Verify tenant isolation and sales filters

Use test data in this tenant with:

1. A succeeded, non-cancelled order paid within the last seven days.
2. A succeeded but cancelled order in the same interval.
3. An unpaid or failed order in the interval.
4. A succeeded, non-cancelled order older than the interval.
5. A succeeded order belonging to a different tenant.

**Expect:** only case 1 contributes to `sales.total_cents`,
`sales.order_count`, and its UTC `by_day` entry. Cases 2–5 do not affect this
tenant's sales. `pending_fulfillment_count` includes only this tenant's paid,
non-cancelled orders in the `pending` or `processing` state.

## D3. Verify frontend display

Open the Admin Overview page and compare its figures and seven-day chart with
D1. Confirm it labels the UTC range and tenant currency and does not infer a
refund amount from `payment_status`.

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| D1 dashboard response | | | |
| D2 paid/non-cancelled and tenant scope | | | |
| D3 Overview display | | | |
