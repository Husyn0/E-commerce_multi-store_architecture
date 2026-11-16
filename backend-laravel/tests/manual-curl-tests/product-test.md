# Product and catalog manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Set the
Admin access token per [auth-test.md](./auth-test.md). Product creation assigns
the tenant's default currency; do not send currency or stock in product JSON.

## Admin product operations

### P1. Create a product

```sh
curl -sS -o "/tmp/product-$TEST_RUN_ID.json" -w 'HTTP %{http_code}\n' \
  -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d "{\"title\":\"Manual Test Product $TEST_RUN_ID\",\"slug\":\"manual-$TEST_RUN_ID\",\"description\":\"Product created for manual API checks.\",\"sku\":\"MANUAL-$TEST_RUN_ID\",\"price_cents\":2599,\"compare_at_price_cents\":2999,\"weight_grams\":500,\"is_active\":true,\"published_at\":\"2026-11-01T00:00:00Z\"}" \
  "$ADMIN_BASE/api/admin/products"
jq . "/tmp/product-$TEST_RUN_ID.json"
export PRODUCT_ID="$(jq -er '.data.id' "/tmp/product-$TEST_RUN_ID.json")"
export PRODUCT_SLUG="manual-$TEST_RUN_ID"
printf 'Created PRODUCT_ID=%s\nPRODUCT_SLUG=%s\n' "$PRODUCT_ID" "$PRODUCT_SLUG"
```

**Expect:** `201`; the command prints the response and exports the created product's `id` as `PRODUCT_ID`. Keep this shell open for subsequent steps.

### P2. Read product list and details

```sh
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/products"
curl -i -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** `200`, product fields and any image/variant collections.

### P3. Update product

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"price_cents":2699,"description":"Updated manual test description."}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** `200` and updated values.

### P4. Reject invalid price and protected fields

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"price_cents":-1}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"currency":"EUR"}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** both return `422`; tenant currency cannot be supplied by the
client. Also try `compare_at_price_cents` less than or equal to `price_cents`;
expect `422`.

### P5. Adjust inventory

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"stock":20}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID/inventory"
```

**Expect:** `200` with the new stock. Negative stock should return `422`.

### P6. Publish and unpublish

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"published_at":null}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"published_at":"2026-11-01T00:00:00Z"}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** `200`; unpublished product should disappear from Storefront
catalog.
### P7. Feature products for the storefront

Feature a product with an optional start and end time:

```sh
curl -i -X PATCH -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"is_featured":true,"featured_from":"2026-11-08T00:00:00Z","featured_until":"2027-11-15T00:00:00Z"}' \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** `200`; products with an end time before their start time should
return `422`. Either time may be `null` for an open-ended feature period.
Featured products appear on the storefront only while active, published, and
inside their feature period. Remove a product from the section by setting
`is_featured` to `false`.

```sh
curl -i "$STORE_BASE/api/storefront/catalog/featured-products?per_page=100&page=1"
```

**Expect:** `200` with pagination metadata and all currently featured products
across pages.


## Storefront catalog

```sh
curl -i "$STORE_BASE/api/storefront/catalog/products"
curl -i "$STORE_BASE/api/storefront/catalog/products?per_page=5&page=1"
curl -i "$STORE_BASE/api/storefront/catalog/products/$PRODUCT_SLUG"
```

**Expect:** `200` and only active, published products for the resolved tenant.
The detail request is `404` while unpublished.

### P8. Clean up this run's product (optional)

Delete only after finishing all product/cart checks, and only when the product
has no cart/order dependencies. Use the run-specific `PRODUCT_ID` from P1:

```sh
curl -i -X DELETE -H "Authorization: Bearer $ADMIN_ACCESS_TOKEN" \
  "$ADMIN_BASE/api/admin/products/$PRODUCT_ID"
```

**Expect:** `204` when no dependent rows; otherwise `409` and deactivate it.
After `204`, run `unset PRODUCT_ID PRODUCT_SLUG` before any later cleanup.



## Known scope limit

Product API does not expose variant create/update/delete endpoints or image
management endpoints. Only variant inventory adjustment is available. Record
variant CRUD/attribute uniqueness as **BLOCKED / not implemented**.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| P1-P3 create/read/update | | | |
| P4 validation/protected fields | | | |
| P5 inventory adjustment | | | |
| P6 publish/delete behavior | | | |
| Storefront catalog/detail | | | |
| Variant CRUD | BLOCKED | | Not implemented |
