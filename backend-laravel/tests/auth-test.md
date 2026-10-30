# Authentication manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). These
requests exercise Admin JWT + refresh-cookie auth and Storefront cookie-session
auth. Do not share cookie jars or bearer tokens.

## Admin login, refresh, protected API, logout

### A1. Login with valid Admin credentials

```sh
ADMIN_LOGIN_RESPONSE="$(curl -fsS -c "$ADMIN_JAR" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}" \
  "$ADMIN_BASE/api/admin/auth/login")" &&
ADMIN_ACCESS_TOKEN="$(printf '%s' "$ADMIN_LOGIN_RESPONSE" | jq -er '.access_token')" &&
export ADMIN_ACCESS_TOKEN &&
unset ADMIN_LOGIN_RESPONSE
```

**Expect:** `200`; JSON includes `access_token`, `token_type: Bearer`, expiry,
and Admin user. Response sets HttpOnly `admin_refresh` and readable
`admin_csrf` cookies. The command validates and exports `ADMIN_ACCESS_TOKEN`
directly from the response without printing the token.

```sh
export ADMIN_CSRF="$(awk '$6 == "admin_csrf" {print $7}' "$ADMIN_JAR")"
```

Access tokens expire after the configured TTL (600 seconds by default), so log
in again or refresh before continuing if needed.

### A2. Reject invalid credentials

```sh
curl -i -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.test","password":"definitely-wrong"}' \
  "$ADMIN_BASE/api/admin/auth/login"
```

**Expect:** `422` with a generic invalid-credentials message. It must not
reveal whether the email exists.

### A3. Require authentication on an Admin resource

```sh
curl -i "$ADMIN_BASE/api/admin/products"
```

**Expect:** `401` without a bearer token.

### A4. Access Admin API with the in-memory access token

```sh
: "${ADMIN_ACCESS_TOKEN:?Run A1 in this same shell before A4}"
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/products"
```

**Expect:** `200` and a paginated product response.

### A5. Refresh requires the double-submit CSRF pair

Missing CSRF header:

```sh
curl -i -b "$ADMIN_JAR" -c "$ADMIN_JAR" \
  -X POST "$ADMIN_BASE/api/admin/auth/refresh"
```

**Expect:** `419`.

Valid cookie/header pair:

```sh
ADMIN_REFRESH_RESPONSE="$(curl -fsS -b "$ADMIN_JAR" -c "$ADMIN_JAR" \
  -H "X-CSRF-TOKEN: $ADMIN_CSRF" \
  -X POST "$ADMIN_BASE/api/admin/auth/refresh")" &&
ADMIN_ACCESS_TOKEN="$(printf '%s' "$ADMIN_REFRESH_RESPONSE" | jq -er '.access_token')" &&
export ADMIN_ACCESS_TOKEN &&
unset ADMIN_REFRESH_RESPONSE &&
export ADMIN_CSRF="$(awk '$6 == "admin_csrf" {print $7}' "$ADMIN_JAR")"
```

**Expect:** `200`, a new access token, and rotated refresh/CSRF cookies. The
command validates and exports the new access token and updates `ADMIN_CSRF`
from the cookie jar without printing the token.

### A6. Logout revokes refresh session

```sh
export ADMIN_CSRF="$(awk '$6 == "admin_csrf" {print $7}' "$ADMIN_JAR")"
curl -i -b "$ADMIN_JAR" -c "$ADMIN_JAR" \
  -H "X-CSRF-TOKEN: $ADMIN_CSRF" \
  -X POST "$ADMIN_BASE/api/admin/auth/logout"
```

**Expect:** `200`, `status: ok`, and expired refresh/CSRF cookies. A later
refresh using that jar should return `401`.

## Storefront registration and login

### A7. Register a brand-new customer (complete local flow)

**First fix tenant resolution if any request says `Tenant not found`.** The
Storefront URL must match `STOREFRONT_BASE_DOMAIN` in `backend-laravel/.env`,
and its leftmost label must match an existing `tenants.subdomain`. For example,
the exported `.env` currently sets `STOREFRONT_BASE_DOMAIN=storefront.localhost`;
with a tenant whose subdomain is `shop`, use
`http://shop.storefront.localhost:8000`. Do not use `localhost:8000` for
tenant-scoped API requests. Check the active configuration and tenant row:

```sh
cd backend-laravel
php artisan config:clear
php artisan tinker --execute="dump(config('tenancy.storefront_base_domain')); dump(DB::table('tenants')->select('id','name','subdomain','status')->get());"
```

For a fresh database created from `database/schema.mysql.sql`, no tenant is
created automatically. Provision one through the protected CLI flow described
in `INITIALIZATION.md` (temporarily enable `TENANT_LIFECYCLE_CLI_ENABLED=true`
and set a non-empty `TENANT_CLI_OPERATOR_REF` in `.env` for this controlled
local operation). Example:

```sh
php artisan tenant:provision "Local Shop" shop USD en --admin-email=admin@example.test
```

Use the actual `subdomain` and configured base domain consistently. After
provisioning, keep `STOREFRONT_BASE_DOMAIN` aligned with the URL you use (the
exported `.env` value is `storefront.localhost`), run `php artisan config:clear`,
and confirm `http://shop.storefront.localhost:8000/api/storefront/tenant`
returns `200`. Do not disable tenant resolution or add a default-tenant fallback.

Initialize a fresh cookie jar and obtain the CSRF token. Keep using this same
jar for the registration human-check, email OTP request, and registration:

```sh
export STORE_JAR='/tmp/shop-new-customer.cookies'
rm -f "$STORE_JAR"
curl -i -c "$STORE_JAR" -b "$STORE_JAR" \
  "$STORE_BASE/api/storefront/cart"
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
```

**Expect:** cart endpoint `200`; both `storefront_cart` and
`storefront_csrf` cookies are present. If the response is `404 Tenant not
found`, stop here and fix the host/subdomain configuration rather than retrying
registration.

Pass the local-only human check. This works only when `APP_ENV=local` (or
`testing`) and the tenant setting is `human_check_driver=demo`:

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"action":"register","response_token":"demo-pass"}' \
  "$STORE_BASE/api/storefront/verification/human"
```

**Expect:** `200 {"verified":true}`. If the tenant requires email verification
(the schema defaults `require_email_verification` to true), request a code:

```sh
export CUSTOMER_EMAIL='new-customer@example.test'
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"email\":\"$CUSTOMER_EMAIL\"}" \
  "$STORE_BASE/api/storefront/verification/email"
```

**Expect:** `202`, with no OTP in the response. Retrieve the six-digit code
from the configured email delivery channel. The default `log` mailer is not a
working delivery channel for this flow; configure a real local SMTP/test inbox
or, for an isolated test only, set the tenant's `require_email_verification`
to false in the database. Do not guess an OTP or expect it in the HTTP response.

Register using the same cookie jar and a unique email. If email verification
is required, replace `123456` with the actual delivered code:

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"email\":\"$CUSTOMER_EMAIL\",\"password\":\"Customer-Password-2026\",\"password_confirmation\":\"Customer-Password-2026\",\"first_name\":\"Test\",\"last_name\":\"Customer\",\"email_verification_code\":\"123456\"}" \
  "$STORE_BASE/api/storefront/auth/register"
```

**Expect:** `201`, customer role/data, and an HttpOnly `storefront_session`
cookie. The password must be at least eight characters and the confirmation
must match. When `require_email_verification=false`, omit
`email_verification_code` from the JSON. Registration should not create a
store/tenant; it creates a customer **inside the tenant resolved from the host**.

### A8. Reject duplicate registration

Repeat the registration request with the same email after obtaining a fresh
registration human-check proof for the same session, or expect the duplicate
request to be blocked by the one-use/expired proof before it reaches the
uniqueness check. The intended account-conflict response is `409` when the
request passes middleware and validation.

### A9. Login with valid customer credentials

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"email\":\"$CUSTOMER_EMAIL\",\"password\":\"$CUSTOMER_PASSWORD\"}" \
  "$STORE_BASE/api/storefront/auth/login"
```

**Expect:** `200`, customer data, and HttpOnly `storefront_session` plus
`storefront_csrf` cookies.

### A10. Reject invalid customer credentials

```sh
curl -i -b "$STORE_JAR" -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"email":"customer@example.test","password":"wrong-password"}' \
  "$STORE_BASE/api/storefront/auth/login"
```

**Expect:** `422` with a generic credentials error.

### A11. Read authenticated account

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account"
```

**Expect:** `200`, current customer and only that customer's addresses. Repeat
with a fresh jar and expect `401`.

### A12. Logout and verify session invalidation

```sh
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -X POST "$STORE_BASE/api/storefront/auth/logout"
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account"
```

**Expect:** logout `200`, then account `401`.

## Admin password reset

### A13. Request reset OTP

```sh
curl -i -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\"}" \
  "$ADMIN_BASE/api/admin/auth/password-reset/request"
```

**Expect:** `202` with the uniform “If an eligible Admin account exists”
message. Obtain the six-digit OTP from the configured delivery channel. If
delivery is unavailable, an authorized operator can use the TTY-only
`tenant:reset-admin-password` CLI fallback; the code is never returned by this
HTTP request.

### A14. Reset password with OTP

```sh
curl -i -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"code\":\"123456\",\"password\":\"New-Strong-Password-2026\",\"password_confirmation\":\"New-Strong-Password-2026\"}" \
  "$ADMIN_BASE/api/admin/auth/password-reset"
```

Replace `123456` with the delivered OTP.

**Expect:** `200`, `password_reset: true`; old refresh sessions are revoked.
Test login with the new password and verify the old password no longer works.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| A1-A6 Admin JWT/refresh/logout | | | |
| A7 Storefront registration | | | |
| A8-A12 Storefront login/session | | | |
| A13-A14 Admin OTP reset | | | |