# Saved payment-method manual checks

The current API has no Storefront payment-method management routes, and checkout
explicitly prohibits `payment_method_id`. These probes document the missing
surface without assuming a route contract that has not been implemented.

## PM1. Probe payment-method collection

After Storefront customer login:

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account/payment-methods"
```

**Current expected result:** `404` route not found. Mark **BLOCKED / not
implemented**.

## PM2. Confirm checkout cannot use saved payment method

The current checkout route validates `payment_method_id` as prohibited:

```sh
curl -i -b "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -H 'Idempotency-Key: manual-payment-method-probe' \
  -d '{"payment_method_id":"test-payment-method","shipping_address":{"recipient_name":"Test Shopper","line1":"1 Test Street","city":"Test City","postal_code":"10000","country_code":"US"},"currency":"USD","customer_email":"shopper@example.test"}' \
  "$STORE_BASE/api/storefront/checkout"
```

**Current expected result:** `422`; no order should be created. The database
model/table existing is not evidence that saved-payment behavior is available.

## Design rules to verify after implementation

- At most one default payment method per `(tenant,user,provider)`, updated
  transactionally while locking the acting user.
- Demo and Stripe may independently each have a default.
- Provider payment method IDs must not be exposed as credentials.

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| PM1 payment-method API | BLOCKED | | Route absent |
| PM2 checkout payment_method_id | | | Expected 422 |
| Defaults/provider isolation | BLOCKED | | CRUD not implemented |
