# Authentication manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). These
requests exercise Admin JWT + refresh-cookie auth and Storefront cookie-session
auth. Do not share cookie jars or bearer tokens.

## Admin login, refresh, protected API, logout

### A1. Login with valid Admin credentials

```sh
curl -i -c "$ADMIN_JAR" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}" \
  "$ADMIN_BASE/api/admin/auth/login"
```

**Expect:** `200`; JSON includes `access_token`, `token_type: Bearer`, expiry,
and Admin user. Response sets HttpOnly `admin_refresh` and readable
`admin_csrf` cookies. Keep the access token private.

```sh
export ADMIN_ACCESS_TOKEN='paste-access_token-from-login-response'
export ADMIN_CSRF="$(awk '$6 == "admin_csrf" {print $7}' "$ADMIN_JAR")"
```

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
curl -i -b "$ADMIN_JAR" -c "$ADMIN_JAR" \
  -H "X-CSRF-TOKEN: $ADMIN_CSRF" \
  -X POST "$ADMIN_BASE/api/admin/auth/refresh"
```

**Expect:** `200`, a new access token, and rotated refresh/CSRF cookies. Update
`ADMIN_ACCESS_TOKEN` and `ADMIN_CSRF` from the response/cookie jar before
continuing.

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

Storefront CSRF tokens are obtained by initializing a cart; see
[cart-test.md](./cart-test.md). After doing so:

```sh
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
```

### A7. Login with valid customer credentials

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"email\":\"$CUSTOMER_EMAIL\",\"password\":\"$CUSTOMER_PASSWORD\"}" \
  "$STORE_BASE/api/storefront/auth/login"
```

**Expect:** `200`, customer data, and HttpOnly `storefront_session` plus
`storefront_csrf` cookies.

### A8. Reject invalid customer credentials

```sh
curl -i -b "$STORE_JAR" -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"email":"customer@example.test","password":"wrong-password"}' \
  "$STORE_BASE/api/storefront/auth/login"
```

**Expect:** `422` with a generic credentials error.

### A9. Read authenticated account

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account"
```

**Expect:** `200`, current customer and only that customer's addresses. Repeat
with a fresh jar and expect `401`.

### A10. Logout and verify session invalidation

```sh
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -X POST "$STORE_BASE/api/storefront/auth/logout"
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account"
```

**Expect:** logout `200`, then account `401`.

## Admin password reset

### A11. Request reset OTP

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

### A12. Reset password with OTP

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
| A7-A10 Storefront session | | | |
| A11-A12 Admin OTP reset | | | |
