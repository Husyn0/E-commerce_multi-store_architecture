# Customer account and address manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). Log in
as a Storefront customer using [auth-test.md](./auth-test.md). Keep the
customer's Storefront cookies in `$STORE_JAR` and get the current CSRF value:

```sh
export STORE_CSRF="$(awk '$6 == "storefront_csrf" {print $7}' "$STORE_JAR")"
```

## A1. Read and update customer profile

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account"
curl -i -b "$STORE_JAR" -X PATCH \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"first_name":"Manual","last_name":"Shopper"}' \
  "$STORE_BASE/api/storefront/account"
```

**Expect:** first response is `200`; profile update is `200` and persists the
new names. An unauthenticated request must return `401`.

## A2. Add address with default flags

```sh
curl -i -b "$STORE_JAR" \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"label":"Home","recipient_name":"Manual Shopper","line1":"1 Test Street","city":"Test City","postal_code":"10000","country_code":"US","is_default_shipping":true,"is_default_billing":true}' \
  "$STORE_BASE/api/storefront/account/addresses"
```

**Expect:** `201`; save the returned `id`:

```sh
export ADDRESS_ID='paste-address-id'
```

### A3. Make another address default and verify only one per kind

Create a second address with `is_default_shipping=true` and
`is_default_billing=true`. Then read the account:

```sh
curl -i -b "$STORE_JAR" "$STORE_BASE/api/storefront/account"
```

**Expect:** `200`; only the second address is default for each kind. Confirm
that shipping and billing defaults can differ by switching only one flag.

### A4. Update and delete address

```sh
curl -i -b "$STORE_JAR" -X PATCH \
  -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d '{"city":"Updated City"}' \
  "$STORE_BASE/api/storefront/account/addresses/$ADDRESS_ID"
curl -i -b "$STORE_JAR" -X DELETE \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  "$STORE_BASE/api/storefront/account/addresses/$ADDRESS_ID"
```

**Expect:** `200` for update and `200 {"deleted":true}` for delete. An address
owned by another user/tenant must not be readable or mutable.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| A1 profile read/update | | | |
| A2-A3 address defaults | | | |
| A4 address update/delete | | | |
