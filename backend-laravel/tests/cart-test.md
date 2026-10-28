# Cart manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Cart
commands are cookie-based and require a persistent cookie jar. Start with a
published product and a Storefront host.

## G1. Initialize guest cart and CSRF cookies

```sh
curl -i -c "$STORE_JAR" -b "$STORE_JAR" "$STORE_BASE/api/storefront/cart"
```

**Expect:** `200`, an empty cart JSON body, and `storefront_cart` (HttpOnly) +
`storefront_csrf` cookies.

```sh
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
```

### G2. Add a published product

```sh
curl -i -b "$STORE_JAR" -c "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"product_id\":\"$PRODUCT_ID\",\"quantity\":2}" \
  "$STORE_BASE/api/storefront/cart/items"
```

**Expect:** `200`; item has server-set `unit_price_cents`, quantity 2, and
computed line total.

### G3. Verify client cannot set price

```sh
curl -i -b "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"product_id\":\"$PRODUCT_ID\",\"quantity\":1,\"unit_price_cents\":1}" \
  "$STORE_BASE/api/storefront/cart/items"
```

**Expect:** `422`.

### G4. Update cart line quantity

Set the `item_id` from G2's response:

```sh
export CART_ITEM_ID='paste-cart-item-id'
curl -i -b "$STORE_JAR" -X PATCH \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"quantity":3}' \
  "$STORE_BASE/api/storefront/cart/items/$CART_ITEM_ID"
```

**Expect:** `200`; quantity and subtotal recalculate.

### G5. Reject quantity over stock and missing CSRF

```sh
curl -i -b "$STORE_JAR" -X PATCH \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"quantity":999}' \
  "$STORE_BASE/api/storefront/cart/items/$CART_ITEM_ID"
curl -i -b "$STORE_JAR" -H 'Content-Type: application/json' \
  -d '{"quantity":1}' \
  "$STORE_BASE/api/storefront/cart/items/$CART_ITEM_ID"
```

**Expect:** first request `422` if stock is below 999; second `419` CSRF
failure.

### G6. Read cart then delete line

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/cart"
curl -i -b "$STORE_JAR" -X DELETE \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  "$STORE_BASE/api/storefront/cart/items/$CART_ITEM_ID"
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/cart"
```

**Expect:** first read shows the line; delete returns `200` with empty cart;
final read remains empty.

## G7. Guest cart is isolated by tenant and cookie

Initialize a second cookie jar and read its cart:

```sh
export OTHER_STORE_JAR='/tmp/shop-other-session.cookies'
curl -i -c "$OTHER_STORE_JAR" "$STORE_BASE/api/storefront/cart"
```

**Expect:** a distinct cart ID and no items from `STORE_JAR`. Repeat against a
different tenant Storefront host to check tenant isolation.

## G8. Sign-in merge

With an existing guest cart, log in using the same cookie jar as described in
[auth-test.md](./auth-test.md), then read `/api/storefront/cart`.

**Expect:** guest cart lines are adopted or quantities for matching
product+variant lines summed; stock conflicts must fail atomically. The merge
should produce a `cart.merged` audit row. Compare cart contents before/after.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| G1-G2 init/add/server price | | | |
| G3-G5 validation/CSRF/stock | | | |
| G6 read/delete | | | |
| G7 cookie/tenant isolation | | | |
| G8 sign-in merge | | | |
