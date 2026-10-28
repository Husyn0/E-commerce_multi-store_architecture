# Tenant and host-routing manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). The host
must map to an existing tenant subdomain and match the configured environment
domains.

## Tenant resolution and host-type checks

### T1. Admin host resolves the tenant

```sh
curl -i "$ADMIN_BASE/api/admin/tenant"
```

**Expect:** `200`, tenant ID/name/status/default currency/default locale, and
`host_type: admin`.

### T2. Storefront host resolves the same tenant as Storefront

```sh
curl -i "$STORE_BASE/api/storefront/tenant"
```

**Expect:** `200` for the same tenant ID and `host_type: storefront`.

### T3. Admin API rejects a Storefront host

```sh
curl -i "$STORE_BASE/api/admin/tenant"
```

**Expect:** `403` host-type rejection.

### T4. Storefront API rejects an Admin host

```sh
curl -i "$ADMIN_BASE/api/storefront/tenant"
```

**Expect:** `403` host-type rejection.

### T5. Unknown host fails closed

```sh
curl -i 'http://unknown.invalid:8000/api/storefront/tenant'
```

**Expect:** `404`; no default tenant should be selected.

### T6. Health readiness bypasses tenant-host resolution

```sh
curl -i 'http://127.0.0.1:8000/health/ready'
```

**Current implementation expectation:** `200 {"status":"ok"}` when the database
is reachable, or `503` when database connectivity fails. This endpoint
currently reports database readiness only; the design additionally requires
driver readiness reporting.

## Custom domain (only if configured)

Set `CUSTOM_DOMAIN_ALLOWLIST` in the environment and make the value exactly
match the tenant's normalized `custom_domain` before running these requests.
Changing only the DB column is not sufficient.

```sh
curl -i 'http://shop-custom.example.test:8000/api/storefront/tenant'
curl -i 'http://not-allowlisted.example.test:8000/api/storefront/tenant'
```

**Expect:** allowlisted-and-matching host resolves as `storefront`; the
non-allowlisted host returns `404`.

## Suspended tenant (coordinate with the operator)

Use `tenant:suspend TENANT_UUID` only in a disposable/test tenant, then run:

```sh
curl -i "$ADMIN_BASE/api/admin/auth/login"
curl -i "$ADMIN_BASE/api/admin/tenant"
curl -i "$STORE_BASE/api/storefront/catalog/products"
curl -i 'http://127.0.0.1:8000/health/ready'
```

**Expect:** Admin routes return `403` before authentication; Storefront routes
return uncached `503`; health remains available. Payment webhooks should remain
available to process reconciliation. Recover the tenant with
`tenant:recover TENANT_UUID`; revoked sessions are not restored automatically.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| T1-T2 correct host resolution | | | |
| T3-T4 host-type rejection | | | |
| T5 unknown host | | | |
| T6 health endpoint | | | |
| Custom domain (optional) | | | |
| Suspended/recovered lifecycle (optional) | | | |
