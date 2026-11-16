# Tenant lifecycle and pruning CLI manual checks

These are manual operator commands, not curl requests. Run only on a disposable
or explicitly approved tenant. Tenant lifecycle commands are disabled unless
`TENANT_LIFECYCLE_CLI_ENABLED=true` and `TENANT_CLI_OPERATOR_REF` is set. Do not
put real secrets in command history.

## L1. Confirm fail-closed gate

With the CLI gate unset/false:

```sh
php artisan tenant:provision "Manual Test Tenant" manual-test USD en --admin-email=admin@example.test
```

**Expect:** command fails as disabled and creates no tenant.

## L2. Provision test tenant

Set the operator environment variables in the process environment, then run:

```sh
php artisan tenant:provision "Manual Test Tenant" manual-test USD en --admin-email=admin@example.test
```

**Expect:** tenant UUID and success message; no password/token is printed.
Verify the Admin account is required to use email OTP reset or the TTY fallback.
Check its audit row has `actor_id=NULL`, `source=cli`, and
`metadata.operator_ref`.

## L3. Suspend and recover

```sh
php artisan tenant:suspend TENANT_UUID
php artisan tenant:suspend TENANT_UUID
php artisan tenant:recover TENANT_UUID
php artisan tenant:recover TENANT_UUID
```

**Expect:** first suspend changes status and revokes all active sessions in
one transaction; repeated suspend/recover are no-op messages; recovery sets
active but does not restore revoked sessions. Verify audit rows.

## L4. Reset Admin password via TTY fallback

Run from an interactive terminal:

```sh
php artisan tenant:reset-admin-password TENANT_UUID admin@example.test
```

**Expect:** one six-digit OTP shown once in the TTY, expires in 10 minutes,
with an audit row. Verify logs do not contain the OTP. Non-TTY execution must
fail clearly without issuing/displaying an OTP.

## L5. Prune dry-run and scoped behavior

```sh
php artisan tenant:prune-data --dry-run
php artisan tenant:prune-data --tenant=TENANT_UUID --dry-run
```

**Expect:** first reports global candidate counts, second reports only that
tenant. Dry run changes no data. Real prune removes expired carts/codes/
verifications/sessions/idempotency records, redacts only old webhook payloads,
and never deletes audit or webhook-event rows.

## Database checks (read-only)

Run via approved MySQL tooling and replace the UUID:

```sql
SELECT status FROM tenants WHERE id = 'TENANT_UUID';
SELECT actor_id, action, source, host_type, metadata, created_at
FROM audit_logs
WHERE tenant_id = 'TENANT_UUID' AND source = 'cli'
ORDER BY created_at DESC
LIMIT 20;
SELECT COUNT(*) AS active_sessions
FROM user_sessions
WHERE tenant_id = 'TENANT_UUID' AND revoked_at IS NULL;
```

**Important:** pruning completed checkout idempotency records may fail while
the `order_id` foreign key uses `ON DELETE RESTRICT`; verify against the
installed MySQL schema and record any FK error. This known schema/implementation
gap is described in `report.md`.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual outcome | Notes |
|---|---|---|---|
| L1 disabled gate | | | |
| L2 provision/no secret output | | | |
| L3 suspend/recover/session revocation | | | |
| L4 TTY OTP | | | |
| L5 global/scoped prune | | | |
