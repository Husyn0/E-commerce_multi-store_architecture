# Checkout and payment manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Create a
published, in-stock product and add it to a guest cart as shown in
[cart-test.md](./cart-test.md). Set `STORE_CSRF` from the same cookie jar.
Complete a human check for the `checkout` action using
[verification-test.md](./verification-test.md).

For demo payments, `APP_ENV` must be local/testing and `DEMO_WEBHOOK_SECRET`
must contain at least 32 characters. Never test against real customer
payments. The schema/backend use one tenant currency and currently support only
two-minor-unit currencies.

## X1. Submit guest checkout

```sh
export CHECKOUT_KEY="manual-checkout-$(date +%s)"
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -H "Idempotency-Key: $CHECKOUT_KEY" \
  -d '{"shipping_address":{"recipient_name":"Test Shopper","line1":"1 Test Street","city":"Test City","postal_code":"10000","country_code":"US"},"currency":"USD","customer_email":"shopper@example.test"}' \
  "$STORE_BASE/api/storefront/checkout"
```

**Expect:** `200`, an order plus payment intent/client secret; order has a
tenant-scoped ULID order number. Do not share the client secret. Save the
returned IDs for the Orders tests.

### X2. Require Idempotency-Key and reject malformed key

```sh
curl -i -b "$STORE_JAR" -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"shipping_address":{"recipient_name":"Test Shopper","line1":"1 Test Street","city":"Test City","postal_code":"10000","country_code":"US"},"currency":"USD","customer_email":"shopper@example.test"}' \
  "$STORE_BASE/api/storefront/checkout"
```

**Expect:** `400` when the required header is missing.

### X3. Retry identical request with same key

Repeat X1 unchanged with the same `CHECKOUT_KEY`.

**Expect:** `200` and the same order/intent outcome; no duplicate order and no
second stock reservation.

### X4. Same key with changed request must conflict

Repeat X1 with the same key but change `customer_email` or address.

**Expect:** `409` idempotency conflict.

### X5. Invalid currency and empty cart

Use a fresh guest cart/key and try `JPY` (or any currency not matching the
tenant's configured currency):

```sh
curl -i -b "$STORE_JAR" -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -H 'Idempotency-Key: manual-invalid-currency' \
  -d '{"shipping_address":{"recipient_name":"Test Shopper","line1":"1 Test Street","city":"Test City","postal_code":"10000","country_code":"US"},"currency":"JPY","customer_email":"shopper@example.test"}' \
  "$STORE_BASE/api/storefront/checkout"
```

**Expect:** `422`. With a valid currency but an empty cart, expect `422`.

### X6. Ensure client-controlled prices cannot change checkout totals

Use a fresh cart and a valid request from X1, but add an arbitrary
`unit_price_cents` to the checkout JSON body.

**Expect:** the response/order total still uses authoritative product/variant
prices, never the supplied value. The current endpoint may ignore unknown JSON
fields rather than rejecting them; treat any client-supplied price affecting
the total as **FAIL**.

### X7. Verify inventory reservation

Read the product inventory using the Admin API before and after a successful
checkout:

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** successful checkout decrements stock once; an identical idempotent
retry does not decrement it again.

## Known scope limit

`payment_method_id` is explicitly prohibited by the current checkout endpoint.
Saved-payment-method checkout is not implemented. Verify payment status by
webhook/order APIs; do not infer payment success from a browser redirect.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| X1 successful guest checkout | | | |
| X2-X4 idempotency behavior | | | |
| X5 invalid currency/empty cart | | | |
| X6 client price rejection | | | |
| X7 stock reservation/idempotent retry | | | |
| Saved payment method | BLOCKED | | Not implemented |
