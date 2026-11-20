# Postman API automation

Two collections keep operator and shopper tests separate:

- `admin.postman_collection.json` — tenant resolution, Admin login/session, dashboard, catalog CRUD, read-only Admin endpoints, logout.
- `storefront.postman_collection.json` — public tenant/catalog, guest cart, then an existing customer account/address flow.

## Import and configure

1. Import both collection JSON files and the matching local environment JSON files into Postman.
2. Select **Local Admin** for the Admin collection. Set `adminEmail` and `adminPassword` to an active Admin for the tenant.
3. Select **Local Storefront** for the Storefront collection. Set `productId` to a published, in-stock product UUID with no required variants. Set `customerEmail` and `customerPassword` to an existing active customer when running the authenticated folder.
4. Confirm the local hostnames resolve to the Laravel server and match the tenant subdomain/base-domain configuration. Defaults use `shop.admin.localhost` and `shop.storefront.localhost` on port 8000.
5. Before each run, clear cookies for the Admin and Storefront hosts in Postman so the guest-cart checks start as guests. Run requests in collection order with the Postman Runner. Cookies are then kept by Postman's cookie jar for each host.

The Admin collection logs in and stores its access token as a collection variable. Each collection also extracts its CSRF cookie value for mutation headers. Do not put production credentials or tokens in exported environment files.

## Isolation and cleanup

CRUD requests generate a unique run suffix and capture the created record ID before follow-up. Their final requests delete only that captured product, category, cart line, or customer address. If a run stops before cleanup, inspect the collection variables (`createdProductId`, `createdCategoryId`, `createdCartItemId`, or `createdAddressId`) and clean up only that ID. Do not substitute IDs from existing tenant data.

The Storefront customer folder assumes an existing customer; registration and OTP flows are not included because they require tenant verification settings and a real delivery channel. Checkout/payment confirmation is also not included: it requires human verification and provider-specific setup.

These files have not been executed against a running API. Review response assertions against the configured environment before using them outside local development.
