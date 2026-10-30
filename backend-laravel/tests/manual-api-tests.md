# Manual API test index

These are **manual curl checklists**, not automated tests. Run each command
yourself, inspect the HTTP status/body, and mark the checklist. No requests are
sent by this guide.

## Before starting

1. Start the Laravel API and use the exact domain settings in `backend-laravel/.env`.
   With the sample domains in `INITIALIZATION.md`, a tenant named `shop` uses:
   - Admin: `http://shop.admin.localhost:8000`
   - Storefront: `http://shop.storefront.localhost:8000` (matches the exported `.env` value `STOREFRONT_BASE_DOMAIN=storefront.localhost`)
2. Confirm the tenant exists and is active. Set the test tenant's `subdomain` to
   the host prefix used below. If the database was initialized from the schema
   only, provision a tenant using the protected CLI flow in `INITIALIZATION.md`;
   the schema does not create one automatically.
3. Keep secrets in `.env`; do not paste secret values into reports, screenshots,
   or shared terminals.
4. Configure independent `SESSION_TOKEN_PEPPER` and
   `VERIFICATION_CODE_PEPPER` values (at least 32 characters each); the sample
   `.env.example` intentionally leaves secrets blank. For local demo checkout,
   use `APP_ENV=local`, `payment_driver=demo`, and a `DEMO_WEBHOOK_SECRET` of at
   least 32 characters. For human-check demo, the response token is `demo-pass`
   and the app must be in `local` or `testing`.
5. New customer registration is documented step-by-step in `auth-test.md`.
   It requires a Storefront cart cookie, CSRF header, human-verification proof,
   and an email OTP when `require_email_verification=true`. The `log` mailer is
   not a working OTP delivery transport; configure a test inbox or disable email
   verification only for an isolated local test.
6. Admin login requires an existing active tenant Admin. For initial
   provisioning, use the protected CLI flow documented in `INITIALIZATION.md`.
7. Most commands require `jq` only for convenient JSON value extraction. If
   unavailable, copy IDs/tokens from the response body and set the variables
   manually.

Start a shell and set the test values (replace example values as needed):

```sh
export ADMIN_BASE='http://shop.admin.localhost:8000'
export STORE_BASE='http://shop.storefront.localhost:8000'
export ADMIN_EMAIL='admin@example.test'
export ADMIN_PASSWORD='@password123'
export CUSTOMER_EMAIL='customer@example.test'
export CUSTOMER_PASSWORD='replace-with-customer-password'
export ADMIN_JAR='/tmp/shop-admin.cookies'
export STORE_JAR='/tmp/shop-store.cookies'
```

For each request, compare the response with the expected result in that topic's
file. Record **PASS**, **FAIL**, or **BLOCKED** and include the actual HTTP
status and a redacted response excerpt.

## Topics

- [Authentication](./auth-test.md)
- [Customer account and addresses](./account-addresses-test.md)
- [Tenant and host routing](./tenant-host-test.md)
- [Verification](./verification-test.md)
- [Categories](./category-test.md)
- [Products and catalog](./product-test.md)
- [Cart](./cart-test.md)
- [Checkout and payment](./checkout-test.md)
- [Orders and refunds](./orders-refunds-test.md)
- [Settings and audit log](./settings-audit-test.md)
- [Dashboard](./dashboard-test.md)
- [Saved payment methods](./payment-methods-test.md)
- [Payment webhooks](./webhook-test.md)
- [CLI lifecycle and pruning](./cli-test.md)