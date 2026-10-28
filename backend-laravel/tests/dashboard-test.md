# Admin dashboard manual checks

The current API route list contains no Admin dashboard endpoint. This file is
an explicit manual gap check; it does not invent an endpoint contract.

## D1. Probe the expected dashboard route

After signing in as Admin and setting `$ADMIN_ACCESS_TOKEN`:

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/dashboard"
```

**Current expected result:** `404` because no dashboard route is currently
registered. Mark **BLOCKED / not implemented**, not PASS.

## Design behavior when implemented

The dashboard should count only orders where `payment_status=succeeded` and
`status!=cancelled`; report the tenant currency and explicit timezone/date
range. Compare displayed totals with the order API and verified test data.

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| D1 dashboard route | BLOCKED | | No endpoint in current API |
| Dashboard totals/timezone | BLOCKED | | Requires endpoint and Admin UI |
