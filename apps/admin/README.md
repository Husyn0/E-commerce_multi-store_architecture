# Tenant Admin

The Admin is a Vue 3 SPA. It does not derive tenant identity from the browser
host; it asks the API for `/api/admin/tenant`, which must resolve the request as
an Admin host.

## Local development

1. Configure the tenant's Admin host in `backend-laravel/.env`. For example,
   `shop.admin.localhost` with `ADMIN_BASE_DOMAIN=admin.localhost`.
2. Start the Laravel API in one terminal:

   ```sh
   cd backend-laravel
   php artisan serve --host=127.0.0.1 --port=8000
   ```

3. Start the Admin Vite server from the repository root in another terminal:

   ```sh
   npm run dev:admin
   ```

   Vite proxies `/api` to `http://127.0.0.1:8000`. Set
   `ADMIN_API_ORIGIN` if Laravel listens elsewhere. Open the Vite URL using
   the tenant's Admin host (normally `http://shop.admin.localhost:5173`) so the
   API can resolve the tenant host correctly.

The browser session keeps the short-lived Admin access token in memory and
uses the `admin_refresh` HttpOnly cookie. Do not persist the access token in
local storage. Provider settings and tenant status remain read-only in the UI;
driver changes are operator-managed.

For manual API tests with `curl`, keep the Laravel server running unless a
different local web server or container is already serving the backend.
