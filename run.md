# Running the application locally

This repository contains the Laravel API, the Vue Admin SPA, and the Nuxt
Storefront. Run each server in its own terminal and leave the terminals open.

## Before starting

- Install PHP dependencies in `backend-laravel` with `composer install`.
- Install JavaScript workspace dependencies from the repository root with
  `npm install`.
- Configure `backend-laravel/.env` using `backend-laravel/.env.example`.
- Ensure MySQL is running and the schema has been imported into the configured
  database. Use the database name, user, and password set in `.env`.
- Set the tenant host/domain values and required app secrets in `.env`. Never
  commit or share the real `.env`.
- Ensure the tenant hostnames resolve to localhost. Example local hosts:
  `shop.admin.localhost` and `shop.storefront.localhost`.

## Reset the local Admin password

If you need to reset the provisioned local Admin account, first start the
backend, then run the lifecycle command in a TTY terminal. Do not put OTPs or
passwords in this file:

  ```sh
  cd backend-laravel
  TENANT_LIFECYCLE_CLI_ENABLED=true TENANT_CLI_OPERATOR_REF=local-admin-bootstrap \
    php artisan tenant:reset-admin-password 6b6641e4-180a-42dc-9ec8-488d802f7bf2 admin@example.test
  ```

Copy the one-time code shown in that terminal into the Admin app's **I have a
reset code** page, choose a password, then sign in with the provisioned Admin
email. The code expires after 10 minutes. Keep it private.

The reset command must run in an interactive terminal because it deliberately
refuses to print OTPs to non-TTY output. The lifecycle gate is enabled only for
that command invocation; it is not enabled persistently in `.env`.

The local `.env` now has generated `SESSION_TOKEN_PEPPER` and
`VERIFICATION_CODE_PEPPER` values, plus `CACHE_STORE=file`, `SESSION_DRIVER=file`,
and `QUEUE_CONNECTION=sync`, which work with the imported schema without
Laravel cache/session/job tables. If the backend server was already running,
restart it so it loads these settings.

## 1. Start the Laravel backend

In Terminal 1, from the repository root:

```sh
cd backend-laravel
php artisan serve --host=127.0.0.1 --port=8000
```

Keep this terminal running. The backend is available at `http://127.0.0.1:8000`.
The Vite Admin server proxies API requests to this address by default.

## 2. Start the Admin frontend

In Terminal 2, from the repository root:

```sh
npm run dev:admin
```

Open the Vite URL using the provisioned tenant Admin hostname:

```text
http://shop.admin.localhost:5173
```

The Admin SPA asks the backend to resolve the tenant; it does not infer a
tenant from the host itself. Set `ADMIN_API_ORIGIN` if Laravel is running at a
different origin.

## 3. Start the Storefront frontend

In Terminal 3, from the repository root:

```sh
npm run dev:storefront
```

Set `STOREFRONT_API_ORIGIN` for the Nuxt server-side proxy if Laravel is at a
different origin. The local default is `http://127.0.0.1:8000`. Open the Nuxt
URL using a configured tenant Storefront hostname, for example:

```text
http://shop.storefront.localhost:3000
```

Use the port printed by Nuxt if it selects a different one. The Storefront
server-side API proxy must be configured to reach the Laravel backend.

## Run the services together

Start the backend and both frontends in three separate terminals, in the order
above. The backend must be running for tenant lookup, Admin API requests, and
Storefront API requests to work.

To run manual `curl` checks, leave Terminal 1 (Laravel) running and use the
tenant-specific Admin or Storefront host required by the endpoint. Running a
frontend is not required for direct `curl` calls.

## Stop the services

Press `Ctrl+C` in each terminal.
