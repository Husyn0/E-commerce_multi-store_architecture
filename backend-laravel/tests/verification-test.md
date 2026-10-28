# Verification manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). The
Storefront cookie jar and CSRF token are required. Initialize the cart first:

```sh
curl -i -c "$STORE_JAR" -b "$STORE_JAR" "$STORE_BASE/api/storefront/cart"
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
```

`demo-pass` works only when the tenant uses `human_check_driver=demo` and
`APP_ENV=local` or `testing`. For Turnstile/hCaptcha, submit a real provider
widget response token. Do not send provider secrets to this endpoint.

## V1 human verification

### V1. Issue a demo verification for registration

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"action":"register","response_token":"demo-pass"}' \
  "$STORE_BASE/api/storefront/verification/human"
```

**Expect:** `200 {"verified":true}` and `Cache-Control: no-store`.

### V1b. Issue a separate demo verification for checkout

The human-verification row stores one action per Storefront session token. Do
not reuse the registration proof for this check; issue the checkout proof
immediately before testing checkout:

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"action":"checkout","response_token":"demo-pass"}' \
  "$STORE_BASE/api/storefront/verification/human"
```

**Expect:** `200 {"verified":true}`. Proceed to checkout using the same
Storefront cart cookie/session.

### V2. Reject invalid demo token

```sh
curl -i -b "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"action":"register","response_token":"wrong"}' \
  "$STORE_BASE/api/storefront/verification/human"
```

**Expect:** `422`.

### V3. Reject unsupported action

```sh
curl -i -b "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"action":"login","response_token":"demo-pass"}' \
  "$STORE_BASE/api/storefront/verification/human"
```

**Expect:** `422`; only `register` and `checkout` are supported.

### V4. Reject missing verification before registration

Use a fresh, separate cookie jar that has not passed the registration human
check:

```sh
export VERIFY_FRESH_JAR='/tmp/shop-verification-fresh.cookies'
curl -i -c "$VERIFY_FRESH_JAR" "$STORE_BASE/api/storefront/cart"
export VERIFY_FRESH_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$VERIFY_FRESH_JAR")"
curl -i -b "$VERIFY_FRESH_JAR" -c "$VERIFY_FRESH_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $VERIFY_FRESH_CSRF" \
  -d '{"email":"new-customer@example.test","password":"Customer-Password-2026","password_confirmation":"Customer-Password-2026"}' \
  "$STORE_BASE/api/storefront/auth/register"
```

**Expect:** `422` until a valid registration human check is recorded. If email
verification is required, a valid `email_verification_code` is also required.

## Email OTP issuance

### V5. Request email verification code

```sh
curl -i -b "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"email":"new-customer@example.test"}' \
  "$STORE_BASE/api/storefront/verification/email"
```

**Expect:** `202` with a generic delivery message. A successful response does
not include the OTP. Check the recipient mailbox.

### V6. Verify registration using human check and email OTP

First call V1 and V5, then replace the OTP below with the delivered six-digit
code:

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"email":"new-customer@example.test","password":"Customer-Password-2026","password_confirmation":"Customer-Password-2026","email_verification_code":"123456"}' \
  "$STORE_BASE/api/storefront/auth/register"
```

**Expect:** `201`, customer data, and a Storefront session cookie.

## Current contract limitation to record

The current checkout route enforces human verification but does not accept or
consume email/phone OTP fields. Phone OTP is not implemented; when
`require_phone_verification=true`, registration returns `503`. Record this as
**BLOCKED / design gap**, not as a failed manual setup step.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| V1-V4 human check | | | |
| V5 email code issuance | | | |
| V6 registration with OTP | | | |
| Phone OTP / checkout OTP contract | BLOCKED | | Not implemented |
