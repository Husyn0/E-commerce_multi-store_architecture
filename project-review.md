# Project-wide review

**Review date:** 2026-11-03  
**Scope:** Repository working tree: Admin SPA, Storefront, Laravel API,
MySQL schema, design documents, and project setup documentation. This report
supersedes the narrower `storefront-home-review.md` for overall project
coverage.

## Executive summary

The project has working application foundations across the tenant-scoped
Laravel API, Admin SPA, and Nuxt Storefront. Both frontends build, their
available tests pass, and Storefront type-checking passes. Core customer flows
include catalog browsing, registration and sign-in, account management, cart,
and checkout with cash-on-delivery or Stripe Payment Element.

The principal correctness issue found is a mismatch between the intended
historical-order snapshot behavior and the MySQL foreign keys used when deleting
products or variants. The main completion gaps are missing Storefront pages for
backend capabilities already exposed by the API, absent shared packages
described by the architecture, and stale/conflicting setup documentation.
Backend test results are incomplete in this environment because PHP's SQLite
PDO driver is unavailable.

## Findings

| # | Severity | Location | Finding | Impact / recommendation |
|---|---|---|---|---|
| 1 | MEDIUM | `database/schema.mysql.sql:415-418`; `backend-laravel/app/Http/Controllers/Admin/ProductController.php:119-133` | Product and variant references in `order_items` use `ON DELETE RESTRICT`. The design says those nullable references use `ON DELETE SET NULL` so `product_snapshot` remains authoritative. The delete endpoint checks active carts, then deletes catalog records without detaching historical order references. | Deleting a product or variant that appears in an order can fail at the database constraint and surface as a server error. Align the imported schema and transactional delete behavior with the snapshot contract, then verify historical order reads. |
| 2 | MEDIUM | `backend-laravel/app/Console/Commands/PruneTenantData.php:54-59`; `backend-laravel/app/Http/Middleware/AuthenticateStorefrontSession.php:56-61` | Session authentication revokes sessions idle beyond `SESSION_IDLE_TTL`, but the pruning command removes sessions only when expired or already revoked. It does not prune idle sessions as required by `design/db-design.txt`. | Idle sessions are rejected at use time but remain stored until absolute expiry or another revocation. Include the idle cutoff in pruning and verify its dry-run and deletion behavior. |
| 3 | MEDIUM | `backend-laravel/routes/api.php:186-189,234-247`; `apps/storefront/app/pages/` | The backend exposes guest-order lookup and saved-payment-method APIs, and the Storefront design calls for lookup and payment-method pages. The current Storefront page tree has neither a guest order lookup page nor an account payment-method page. | Customers cannot use these API capabilities through the Storefront UI. Add the designed pages and connect them to the tenant-scoped APIs, or explicitly defer/remove the features from the supported design. |
| 4 | MEDIUM | `design/Monorepo Layout.txt:5-9,21-28`; `packages/` | The architecture describes shared UI, styles, API-client, and types packages, but `packages/` contains no tracked files or package manifests. | Cross-app contracts and components are not shared or generated from OpenAPI as designed. Either implement the packages and API contract or revise the architecture and setup plan to match the intended scope. |
| 5 | LOW | `apps/storefront/README.md:36-41`; `INITIALIZATION.md:127-133`; `report.md` | Documentation conflicts with the current implementation. The Storefront README says checkout, registration, and password recovery are unavailable despite implemented pages/API flows. `INITIALIZATION.md` says guest order lookup is out of scope while the design and backend routes include it. `report.md` also contains older status claims and should be treated as historical. | New contributors and operators may follow incorrect setup or feature expectations. Reconcile these documents against the current code and clearly date or archive superseded status reports. |
| 6 | LOW | `apps/storefront/app/pages/index.vue:105` | Category numbering prepends `0` to every index, so the tenth category is displayed as `010`, the eleventh as `011`, and so on. | Format numbers with a minimum two-digit width rather than a fixed prefix. |

## Validation performed

- `npm --workspace apps/admin run build` — passed.
- `npm --workspace apps/admin test` — passed, 1 test.
- `npm --workspace apps/storefront run build` — passed.
- `npm --workspace apps/storefront run typecheck` — passed. Nuxt warns that
  installed Node.js `22.19.0` is below its supported range
  (`^22.21.0 || ^24.11.0 || >=26.0.0`).
- `npm --workspace apps/storefront test` — passed, 2 tests.
- `cd backend-laravel && php artisan test --compact` — 50 of 52 tests passed.
  The two failures are in `TenantHostResolutionTest` and fail when connecting
  to the in-memory SQLite database because `pdo_sqlite` is not installed.
  PHP has `pdo_mysql`, but no MySQL integration run was performed.
- `git diff --check` — passed.

The frontend test suites are small and do not exercise complete browser
workflows. No browser end-to-end, imported-MySQL, or live payment-provider
validation was performed; successful builds and type-checking do not establish
those behaviors.

## Review boundaries

No application code was changed for this review. Existing uncommitted changes
in the working tree were left intact. The backend's SQLite test failures are
environment-blocked and are not, by themselves, evidence of an application
defect.
