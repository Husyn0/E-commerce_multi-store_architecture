# Project initialization

This repository currently contains design documents only. The commands below
scaffold the three application ends; they do not implement the API, UI, tenancy,
authentication, payment, or other behavior described in `design/`.

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

## 2. Initialize the Laravel API

```sh
composer create-project laravel/laravel backend-laravel
cd backend-laravel
php artisan key:generate
```

Configure the backend `.env` for the database created from
[`database/schema.mysql.sql`](./database/schema.mysql.sql). Set trusted-proxy
configuration for the actual deployment; do not trust arbitrary forwarded
headers.

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

SESSION_TOKEN_PEPPER=replace_with_a_random_32_byte_or_longer_secret
VERIFICATION_CODE_PEPPER=replace_with_a_different_random_secret
DEMO_WEBHOOK_SECRET=replace_with_a_random_secret
WEBHOOK_PAYLOAD_RETENTION_DAYS=30
SESSION_IDLE_TTL=1209600
```

Use independent randomly generated secrets for each pepper/secret, store them
only in environment/secret-management configuration, and never commit `.env`.
`SESSION_IDLE_TTL` is expressed in seconds.
Use an empty allowlist in local development unless custom-domain routing is
specifically being tested.

Create and implement the Laravel API according to `design/back-design.txt` and
`design/db-design.txt`. The SQL schema is the database bootstrap; do not run
Laravel migrations that recreate the same tables unless they are adapted to
this schema.

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
2. Configure the Laravel `.env`, implement tenant/host middleware, then start
   Laravel with `php artisan serve --host=127.0.0.1 --port=8000`.
3. Start Admin and Storefront in separate terminals using the root npm scripts.
4. Configure local hostnames/DNS and trusted proxies consistently before testing
   host-based tenant resolution. Unknown hosts must remain rejected.
5. Use demo payment/verification drivers only in local development. Never enable
   log-based delivery for real OTPs or production accounts.
