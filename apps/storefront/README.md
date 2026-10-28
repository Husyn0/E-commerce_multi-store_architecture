# Storefront

Nuxt storefront for tenant-scoped public catalogs, shopping carts, and customer
accounts.

## Run locally

Start Laravel, then from the repository root run:

```sh
STOREFRONT_API_ORIGIN=http://127.0.0.1:8000 npm run dev:storefront
```

Open the Nuxt dev server through a tenant Storefront hostname that resolves to
localhost, for example `http://shop.storefront.localhost:3000`. Laravel's
`STOREFRONT_BASE_DOMAIN` must be configured for that hostname. The backend
resolves tenant and host type; the frontend does not send a tenant identifier.
Configure Laravel's `TRUSTED_PROXIES` for the Nuxt server so Laravel accepts
the forwarded Storefront host. For a local Nuxt server connecting from
`127.0.0.1`, use `TRUSTED_PROXIES=127.0.0.1`.

`STOREFRONT_API_ORIGIN` is a server-only setting used by the SSR API proxy.
The proxy preserves the request host and session cookies, has a two-second
upstream timeout, and does not retry. It forwards only the required API
headers. Catalog pages use the proxy for server-side rendering; customer,
cart, and account data are not shared-cached.

## Current functionality

- Server-rendered, published product catalog and active categories
- Product search, category pages, product details, variant selection, and
  server-priced cart operations
- Customer sign-in, profile, saved addresses, and tenant-scoped order history
- Responsive storefront layout and empty/loading/error states

Checkout is deliberately unavailable in this initial implementation. The API
can create a payment intent, but the storefront still needs the provider's
secure payment element and tenant-safe public payment configuration before it
can safely collect payment or create orders. Registration, customer password
recovery, and guest order lookup also await their complete verification
interfaces and backend contracts.

Run `npm --workspace apps/storefront run typecheck` and
`npm --workspace apps/storefront run build` to validate the app.
