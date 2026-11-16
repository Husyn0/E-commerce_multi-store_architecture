# Settings and audit-log manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Set
`ADMIN_ACCESS_TOKEN` using the login sequence in [auth-test.md](./auth-test.md).

## S1. Read tenant settings

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/settings"
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/theme"
```

**Expect:** `200`; settings include driver values, boolean `driver_warning`,
and a masked Stripe account indicator only. Secrets and the full connected
account ID must not be returned.

## S2. Update only allowed settings

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Manual Test Shop","default_locale":"en","require_email_verification":true}' \
  "$ADMIN_BASE/api/admin/settings"
```

**Expect:** `200 {"updated":true}` when changed, and a `settings.updated`
audit entry. Repeating unchanged data should return `updated:false` without
adding another audit row.

### S3. Reject provider, domain, status, and currency edits

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"payment_driver":"stripe"}' \
  "$ADMIN_BASE/api/admin/settings"
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"default_currency":"EUR","status":"suspended","custom_domain":"shop.example.test"}' \
  "$ADMIN_BASE/api/admin/settings"
```

**Expect:** both `422`; these fields are not Admin-editable.

## S4. Update theme

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"theme_config":{"colors":{"primary":"#345678"},"fonts":{"body":"Arial"},"layout_variant":"classic"}}' \
  "$ADMIN_BASE/api/admin/theme"
```

**Expect:** `200 {"updated":true}`; GET theme returns the configuration. Send
an unexpected top-level field and expect `422`.

## S5. Audit-log pagination and filter

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/audit-logs?source=web&limit=10"
```

**Expect:** `200`, `data` and `next_cursor`; web actions should include
`source=web` and `host_type=admin`. Use the returned URL-safe cursor:

```sh
curl -i -G -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  --data-urlencode 'source=web' \
  --data-urlencode 'limit=10' \
  --data-urlencode 'cursor=paste-next_cursor-here' \
  "$ADMIN_BASE/api/admin/audit-logs"
```

Invalid cursor should return `422`. Confirm no-op updates do not add audit
rows; confirm CLI and webhook rows show `source=cli`/`webhook` and null
`host_type`.

## S6. Customer administration

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/customers?per_page=5"
```

Open a customer ID from the response, then:

```sh
export CUSTOMER_ID='paste-customer-id'
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/customers/$CUSTOMER_ID"
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"status":"suspended"}' \
  "$ADMIN_BASE/api/admin/customers/$CUSTOMER_ID/status"
```

**Expect:** customer reads are tenant-scoped; suspend returns `200` and
revokes that customer's active sessions.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| S1 settings/theme reads | | | |
| S2 allowed settings update | | | |
| S3 protected field rejection | | | |
| S4 theme update | | | |
| S5 audit filters/cursor/source | | | |
| S6 tenant-scoped customer/status | | | |
