# Project initialization

The monorepo scaffolds and MySQL schema are in place. The Laravel API includes
tenant-host resolution and status gates, Admin authentication and management
APIs, Storefront catalog/cart/guest checkout, payment webhooks, Admin refund
initiation, OTP/account flows, server-side human-verification endpoints, and
env-gated tenant lifecycle/data-pruning commands. The frontend applications
are not complete.

## Prerequisites

- PHP and Composer versions supported by the selected Laravel release.
- Node.js LTS and npm.
- MySQL 8.0.16 or later. This minimum is required for enforced `CHECK`
  constraints and stored generated columns.
- Git.

## 1. Initialize the monorepo

Run these commands from the repository root. Do not run a scaffold command if
its target directory already contains files.

```sh
mkdir -p apps packages database
npm init -y
```

Do not configure npm workspaces until the app/package folders each have a
`package.json`; npm requires every configured workspace to exist. Create shared
package directory placeholders:

```sh
mkdir -p packages/ui packages/styles packages/api-client packages/types
```

## 2. Laravel API setup and current implementation

```sh
cd backend-laravel
composer install
```

The Laravel project is already scaffolded in `backend-laravel/`; do not recreate
it. Configure `.env` for the database created from
[`database/schema.mysql.sql`](./database/schema.mysql.sql). Set trusted-proxy
configuration for the actual deployment; do not trust arbitrary forwarded
headers. The sample config uses file-based sessions/cache and synchronous jobs
because this schema does not include Laravel's default sessions/cache/jobs
tables.

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://api.localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_multistore
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_local_database_password

ADMIN_BASE_DOMAIN=admin.localhost
STOREFRONT_BASE_DOMAIN=store.localhost
CUSTOM_DOMAIN_ALLOWLIST=
TRUSTED_PROXIES=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

SESSION_TOKEN_PEPPER=
VERIFICATION_CODE_PEPPER=
DEMO_WEBHOOK_SECRET=
TURNSTILE_SECRET=
HCAPTCHA_SECRET=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
DEFAULT_PAYMENT_DRIVER=demo
DEFAULT_VERIFICATION_DRIVER=demo
DEFAULT_HUMAN_CHECK_DRIVER=demo
DEFAULT_EMAIL_PROVIDER=log
DEFAULT_SMS_PROVIDER=log
WEBHOOK_PAYLOAD_RETENTION_DAYS=30
SESSION_IDLE_TTL=1209600
TENANT_LIFECYCLE_CLI_ENABLED=false
TENANT_CLI_OPERATOR_REF=
```

The secret entries above are intentionally blank in the sample. Generate
independent random values for the active flows and store them only in
environment/secret-management configuration; never commit `.env`. The cart,
Admin refresh-session, and demo webhook flows fail closed if their required
secrets are missing. Checkout also returns 503 until the selected tenant
payment driver and its webhook secret are configured. `SESSION_IDLE_TTL` is
expressed in seconds.
Use an empty allowlist in local development unless custom-domain routing is
specifically being tested.

Implemented API routes:

- `GET /health/ready` checks database readiness and does not require a tenant.
- `GET /api/admin/tenant` resolves the admin host and returns tenant bootstrap
  fields, including status, so a suspended Admin screen can poll for recovery.
- Admin auth: `POST /api/admin/auth/login`, `/refresh`, and `/logout`.
- Admin catalog and operations: product/category management, inventory,
  customers, orders, settings/theme, audit-log reads, and
  `POST /api/admin/orders/{orderId}/refunds`.
- Storefront reads: tenant bootstrap and published catalog/category endpoints.
- Guest commerce: `GET /api/storefront/cart`, cart item add/update/delete, and
  `POST /api/storefront/checkout`. Initialize a cart first to receive the
  HttpOnly cart cookie and CSRF cookie; mutating cookie requests require the
  matching `X-CSRF-TOKEN` header. Checkout requires `Idempotency-Key`.
- Storefront customer flows: email verification-code issuance, registration,
  login/logout, profile, tenant-scoped addresses with transactional defaults,
  and authenticated order history.
- Admin password reset: OTP request and reset endpoints on the Admin host.
- `POST /api/webhooks/payment` verifies Stripe or demo signatures, deduplicates
  events, and reconciles tenant payment/refund events.

Stripe checkout creates a PaymentIntent on the tenant's configured connected
account using `Stripe-Account`; no platform/application fee is charged. Admin
refunds are sent to that same tenant account. This is shopper-to-store payment
processing, not developer revenue handling. With blank payment secrets or a
missing tenant Stripe account, payment operations correctly fail closed.

Cart sign-in merge adopts a guest cart when no signed-in cart exists; otherwise
it adds quantities for matching lines and returns a validation error if current
stock would be exceeded. The merge is audited as `cart.merged`. Guest order
lookup is explicitly out of scope for v1; orders are available to signed-in
customers only. Human-verification endpoints support Turnstile/hCaptcha and a
local/testing-only demo token (`demo-pass`); configure provider secrets and a
matching frontend widget before enabling those tenant drivers. Phone OTP is
not wired yet. Demo
payments and demo webhooks are disabled in production. Do not treat the backend
as production-complete until these flows, provider configuration, frontend
apps, and end-to-end MySQL/webhook tests are finished.

Tenant provisioning and lifecycle commands are disabled by default. To enable
them for a controlled CLI session, set `TENANT_LIFECYCLE_CLI_ENABLED=true` and
set `TENANT_CLI_OPERATOR_REF` to the operator's non-secret identifier. The
commands write audit rows with `actor_id=NULL`, `source=cli`, and
`metadata.operator_ref`; suspension revokes every tenant user session in the
same transaction. Provisioning generates an unusable random Admin password
and never displays it; the Admin must request an OTP through the Admin password
reset endpoint. `tenant:reset-admin-password` is a TTY-only fallback for broken
email delivery. It creates an audited, rate-limited 10-minute OTP and displays
it only in the interactive terminal; it does not write the OTP to application
logs. Share the code with the Admin through a secure channel.
`tenant:prune-data` defaults to all tenants; pass `--tenant=TENANT_UUID` to
narrow its scope. The daily scheduled prune removes expired carts, codes,
human verifications, sessions and checkout idempotency records, and only
nulls webhook payloads older than `WEBHOOK_PAYLOAD_RETENTION_DAYS`; it never
deletes audit rows or webhook event rows. The lifecycle CLI gate and operator
reference must remain enabled for scheduled pruning to run.

```sh
cd backend-laravel
php artisan tenant:provision "Shop name" shopname USD en --admin-email=admin@example.test
php artisan tenant:suspend TENANT_UUID
php artisan tenant:recover TENANT_UUID
php artisan tenant:reset-admin-password TENANT_UUID admin@example.test
php artisan tenant:prune-data --dry-run
```

Tenant URLs follow `<tenant-subdomain>.<ADMIN_BASE_DOMAIN>` and
`<tenant-subdomain>.<STOREFRONT_BASE_DOMAIN>`. A custom domain resolves only
when it is in `CUSTOM_DOMAIN_ALLOWLIST` and exactly matches
`tenants.custom_domain`. Unknown hosts return 404. Suspended Admin hosts are
blocked with 403; suspended Storefront hosts return uncached 503. Configure
`TRUSTED_PROXIES` with explicit proxy addresses/CIDRs only.

Keep changes aligned with `design/back-design.txt` and `design/db-design.txt`.
The SQL schema is the database bootstrap. Do not run Laravel's default
migrations against the imported database; the Composer setup scripts no longer
run migrations automatically. Run `cd backend-laravel && php artisan test
--compact` for the current automated test suite.

## 3. Initialize the Admin SPA

From the repository root:

```sh
npm create vite@latest apps/admin -- --template vue
cd apps/admin
npm install
npm run dev
```

Implement the routes and views in `design/Tenant Admin.txt`. The SPA must not
derive a tenant from `window.location` or send a tenant header. Obtain tenant
display context from `GET /api/admin/tenant`. Keep access JWTs in memory; use
the refresh cookie and CSRF contract from the backend design.

## 4. Initialize the Storefront SSR app

From the repository root:

```sh
npx nuxi@latest init apps/storefront
cd apps/storefront
npm install
npm run dev
```

Implement the routes in `design/Storefront.txt`. SSR tenant context must come
from the server-validated request host. Do not resolve the tenant from a
client-supplied tenant ID. The SSR proxy must use the documented timeout,
cache isolation, and no-cache rules.

## 5. Shared packages and API contract

- Implement `packages/ui` as framework-agnostic Vue components using props and
  events. Keep Nuxt composables/adapters in `apps/storefront`.
- Version `packages/types` by API major. Generate contracts from the published
  OpenAPI document, or keep hand-maintained types aligned with it.
- Keep tenant resolution on the server. `packages/api-client` must not infer the
  tenant from the browser host or add a tenant header.
- Add an OpenAPI contract before implementing clients/controllers so the two
  frontends share the same request/response definitions.
- After all workspace packages have `package.json` files, configure the root
  `package.json` like this:

```json
{
  "name": "ecommerce-multistore",
  "private": true,
  "workspaces": [
    "apps/admin",
    "apps/storefront",
    "packages/*"
  ],
  "scripts": {
    "dev:admin": "npm --workspace apps/admin run dev",
    "dev:storefront": "npm --workspace apps/storefront run dev",
    "build": "npm run build --workspaces --if-present"
  }
}
```

## 6. Apply the MySQL schema in phpMyAdmin

1. Confirm the server is MySQL 8.0.16 or later and that you have permission to
   create a database. MariaDB and older MySQL versions are not the target for
   this script.
2. Back up any existing database. This script uses `CREATE TABLE IF NOT EXISTS`
   for convenience but is not a migration or a safe upgrade script.
3. Open phpMyAdmin, select the **Import** tab, choose
   [`database/schema.mysql.sql`](./database/schema.mysql.sql), and run the
   import. Alternatively, create/open a SQL tab and paste the complete script.
4. If `CREATE DATABASE` privileges are unavailable, create a database named
   `ecommerce_multistore` in phpMyAdmin first, then remove the script's initial
   `CREATE DATABASE` and `USE` statements before importing.
5. Confirm the tables exist and configure Laravel's `DB_*` values to match.

The script creates no tenant, admin user, API credential, or reusable secret.
Provision the first tenant/admin later through the protected CLI flow described
in the design; do not insert production credentials manually into the database.
The composite tenant foreign keys intentionally restrict hard deletes. Before
hard-deleting a product or variant referenced by historical order items,
transactionally set the relevant `order_items.product_id` or `variant_id` to
`NULL` while preserving `product_snapshot`; variants referenced by cart items
cannot be deleted until those cart lines are removed.

## 7. Local development order

1. Start MySQL and import the schema.
2. Configure Laravel `.env`, then start the API with
   `php artisan serve --host=127.0.0.1 --port=8000`.
3. Start Admin and Storefront in separate terminals using the root npm scripts.
4. Configure local hostnames/DNS and trusted proxies consistently before testing
   host-based tenant resolution. Unknown hosts must remain rejected.
5. Use demo payment/verification drivers only in local development. Never enable
   log-based delivery for real OTPs or production accounts.
