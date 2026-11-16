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
curl -sS -o "/tmp/address-$TEST_RUN_ID.json" -w 'HTTP %{http_code}\n' \
  -b "$STORE_JAR" -c "$STORE_JAR" -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"label\":\"Home\",\"recipient_name\":\"Manual Shopper\",\"line1\":\"1 Test Street\",\"city\":\"Test City\",\"postal_code\":\"10000\",\"country_code\":\"US\",\"is_default_shipping\":true,\"is_default_billing\":true}" \
  "$STORE_BASE/api/storefront/account/addresses"
jq . "/tmp/address-$TEST_RUN_ID.json"
export ADDRESS_ID="$(jq -er '.id' "/tmp/address-$TEST_RUN_ID.json")"
printf 'Created ADDRESS_ID=%s\n' "$ADDRESS_ID"
```

**Expect:** `201`; the command prints the response and exports the created address ID as `ADDRESS_ID`. Keep this shell open for follow-up and cleanup.

### A3. Make another address default and verify only one per kind

Create a second address and capture its ID separately so A4 still targets the
first object. Then read the account:

```sh
curl -sS -o "/tmp/address-second-$TEST_RUN_ID.json" -w 'HTTP %{http_code}\n' \
  -b "$STORE_JAR" -c "$STORE_JAR" -H 'Content-Type: application/json' \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  -d "{\"label\":\"Work\",\"recipient_name\":\"Manual Shopper\",\"line1\":\"2 Test Street\",\"city\":\"Test City\",\"postal_code\":\"10000\",\"country_code\":\"US\",\"is_default_shipping\":true,\"is_default_billing\":true}" \
  "$STORE_BASE/api/storefront/account/addresses"
jq . "/tmp/address-second-$TEST_RUN_ID.json"
export SECOND_ADDRESS_ID="$(jq -er '.id' "/tmp/address-second-$TEST_RUN_ID.json")"
printf 'Created SECOND_ADDRESS_ID=%s\n' "$SECOND_ADDRESS_ID"
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
owned by another user/tenant must not be readable or mutable. Delete the
second address created in A3 too, using its run-specific ID:

```sh
curl -i -b "$STORE_JAR" -X DELETE \
  -H "X-CSRF-TOKEN: $STORE_CSRF" \
  "$STORE_BASE/api/storefront/account/addresses/$SECOND_ADDRESS_ID"
```

After each successful delete, clear that variable (`unset ADDRESS_ID` or
`unset SECOND_ADDRESS_ID`) so the same shell cannot accidentally reuse it.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| A1 profile read/update | | | |
| A2-A3 address defaults | | | |
| A4 address update/delete | | | |
