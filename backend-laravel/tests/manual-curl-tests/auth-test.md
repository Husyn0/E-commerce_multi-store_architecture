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
unset ADMIN_LOGIN_RESPONSE &&
printf 'Admin login succeeded; ADMIN_ACCESS_TOKEN is exported.\n'
```

**Expect:** `200`; JSON includes `access_token`, `token_type: Bearer`, expiry,
and Admin user. Response sets HttpOnly `admin_refresh` and readable
`admin_csrf` cookies. The command validates and exports `ADMIN_ACCESS_TOKEN`
directly from the response without printing the token.

```sh
export ADMIN_CSRF="$(awk '$6 == "admin_csrf" {print $7}' "$ADMIN_JAR")"
```

Access tokens expire after the configured TTL (5400 seconds / 90 minutes by default), so log
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
## 📋 Section A7: "Register a brand-new customer (complete local flow)"

This section walks through a __complete storefront customer registration flow__. It's designed for local development/testing and requires proper tenant configuration first.

### ⚠️ Key Prerequisites (Critical for Safety)

Before running any A7 commands, you __must__ fix tenant resolution. The documentation explicitly states:

> __First fix tenant resolution if any request says `Tenant not found`.__ The Storefront URL must match `STOREFRONT_BASE_DOMAIN` in `backend-laravel/.env`, and its leftmost label must match an existing `tenants.subdomain`.

### 🔧 Setup Steps (Safe - read-only until you run the final register)

1. __Check your configuration__ (safe, just shows current state):

```sh
cd backend-laravel
php artisan config:clear
php artisan tinker --execute="
  dump(config('tenancy.storefront_base_domain'));
  dump(DB::table('tenants')->select('id','name','subdomain','status')->get());
"
```

- This __only reads__ configuration and database - no data modification
- Verify `STOREFRONT_BASE_DOMAIN` matches your setup
- Verify a tenant exists with a matching subdomain

2. __If no tenant exists__, you'll need to provision one (documented in the file):

```sh
php artisan tenant:provision "Local Shop" shop USD en --admin-email=admin@example.test
```

- This creates a tenant with subdomain `shop`
- You can use a __throwaway admin email__ - it doesn't affect your real accounts

### 🛡️ Safe Registration Flow (Step-by-step, minimal risk)

The registration uses a __dedicated cookie jar__ (`/tmp/shop-register-*.cookies`) and __unique test emails__ pattern: `manual-<ID>@example.test`

__Step 1: Initialize cookie jar (safe)__

```sh
export STORE_JAR="/tmp/shop-register-$TEST_RUN_ID.cookies"
curl -i -c "$STORE_JAR" -b "$STORE_JAR" "$STORE_BASE/api/storefront/cart"
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
```

- Only reads cart endpoint, sets up cookie jar
- __Uses `$TEST_RUN_ID`__ - automatically unique per run

__Step 2: Human check (safe, local only)__

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"action":"register","response_token":"demo-pass"}' \
  "$STORE_BASE/api/storefront/verification/human"
```

- __Only works when `APP_ENV=local`__ (or testing)
- Uses `human_check_driver=demo` setting
- No real data affected yet

__Step 3: Request email OTP (safe - configurable risk)__

```sh
export CUSTOMER_EMAIL="manual-$TEST_RUN_ID@example.test"
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"email\":\"$CUSTOMER_EMAIL\"}" \
  "$STORE_BASE/api/storefront/verification/email"
```

- __Expects `202` response__ - just requests a code
- Default uses `log` mailer - __won't deliver to real email__
- Option: set tenant's `require_email_verification` to `false` in DB for isolated testing

__Step 4: Complete registration (the actual data-creating step)__

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"email\":\"$CUSTOMER_EMAIL\",\"password\":\"Customer-Password-2026\",\"password_confirmation\":\"Customer-Password-2026\",\"first_name\":\"Test\",\"last_name\":\"Customer\",\"email_verification_code\":\"123456\"}" \
  "$STORE_BASE/api/storefront/auth/register"
```

- __Creates a customer account__ inside the tenant resolved from the host
- Password must be ≥ 8 chars, confirmation must match
- When `require_email_verification=false`, you can omit `email_verification_code`
- __Does NOT create a store/tenant__ - creates customer __inside existing tenant__

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
