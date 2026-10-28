# Payment webhook manual tests

Use the common setup in [manual-api-tests.md](./manual-api-tests.md). The
shared route is `/api/webhooks/payment`; it is not tenant-host scoped and
requires provider signature verification.

## W1. Reject invalid JSON

```sh
curl -i -X POST -H 'Content-Type: application/json' \
  --data-binary 'not-json' \
  'http://127.0.0.1:8000/api/webhooks/payment'
```

**Expect:** `400`.

## W2. Reject invalid demo signature

```sh
curl -i -X POST -H 'Content-Type: application/json' \
  -H "X-Demo-Signature: $(printf '%064d' 0)" \
  --data-binary '{"id":"shop:manual-invalid-signature","type":"payment_intent.succeeded","tenant_id":"TENANT_UUID"}' \
  'http://127.0.0.1:8000/api/webhooks/payment'
```

**Expect:** `400` invalid signature. This requires demo secret configuration
to reach signature validation; a missing/short secret should return `503`.

## W3. Reject platform/subscription event type

Sign the exact raw body below with HMAC-SHA-256 and
`DEMO_WEBHOOK_SECRET`. Do not send unsigned event JSON.

```sh
export WEBHOOK_BODY='{"id":"TENANT_UUID:manual-platform-event","type":"customer.subscription.created","tenant_id":"TENANT_UUID"}'
export WEBHOOK_SIGNATURE="$(printf '%s' "$WEBHOOK_BODY" | openssl dgst -sha256 -hmac "$DEMO_WEBHOOK_SECRET" -hex | sed 's/^.*= //')"
curl -i -X POST -H 'Content-Type: application/json' \
  -H "X-Demo-Signature: $WEBHOOK_SIGNATURE" \
  --data-binary "$WEBHOOK_BODY" \
  'http://127.0.0.1:8000/api/webhooks/payment'
```

Replace both `TENANT_UUID` occurrences with the tenant UUID. **Expect:** `400`
unsupported payment event. No webhook row should be inserted.

## W4. Signed demo PaymentIntent event (disposable test order only)

Use an existing unpaid demo order. Set the order's ID and use its current
`payment_intent_id`. The event ID must start with the tenant UUID and the
tenant ID is included inside the HMAC-signed body.

```sh
export TENANT_UUID='paste-tenant-uuid'
export ORDER_ID='paste-unpaid-order-id'
export PAYMENT_INTENT_ID='paste-demo-payment-intent-id'
export WEBHOOK_BODY="{\"id\":\"$TENANT_UUID:manual-payment-succeeded-001\",\"type\":\"payment_intent.succeeded\",\"tenant_id\":\"$TENANT_UUID\",\"data\":{\"object\":{\"id\":\"$PAYMENT_INTENT_ID\",\"metadata\":{\"order_id\":\"$ORDER_ID\"}}}}"
export WEBHOOK_SIGNATURE="$(printf '%s' "$WEBHOOK_BODY" | openssl dgst -sha256 -hmac "$DEMO_WEBHOOK_SECRET" -hex | sed 's/^.*= //')"
curl -i -X POST -H 'Content-Type: application/json' \
  -H "X-Demo-Signature: $WEBHOOK_SIGNATURE" \
  --data-binary "$WEBHOOK_BODY" \
  'http://127.0.0.1:8000/api/webhooks/payment'
```

**Expect:** `200 {"status":"processed"}` and the order payment state changes to
`succeeded`. Resend the exact same body/signature; expect a duplicate response
without a second state change/audit row.

## W5. Unknown tenant/account

Send a correctly signed event with an unknown `tenant_id`; expect `404` and no
tenant event row. For Stripe, an event without a connected `account` should
also be rejected. Use Stripe CLI or the provider's test-event tooling for a
valid Stripe signature; do not construct or expose a live webhook secret in
shared logs.

## W6. Production and suspended-tenant behavior

- In production, a demo-signed request should return `503`; demo webhooks are
  disabled.
- For a suspended tenant, a valid signed event should still be processed and
  its event row should record `suspended_at_receipt=true`.

## Known behavior to scrutinize

If local event processing fails after signature verification, verify that the
HTTP response and stored event state tell the provider to retry. The current
implementation has a known risk of marking a failed event yet returning a
processed-shaped `200`; record any occurrence as **FAIL** and attach the
redacted event ID/status only.

## Result record

| Case | Result (PASS/FAIL/BLOCKED) | Actual status | Notes |
|---|---|---:|---|
| W1 invalid JSON | | | |
| W2 invalid signature | | | |
| W3 unsupported event | | | |
| W4 signed event/dedup | | | |
| W5 unknown tenant/account | | | |
| W6 production/suspended receipt | | | |
