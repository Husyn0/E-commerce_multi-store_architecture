# Project status

**Updated:** 2026-11-15

**Scope:** Current repository snapshot and the latest manual-test guide review.

## Current state

- **Backend:** Tenant-scoped Laravel APIs are present for authentication, catalog, cart and checkout, customer accounts, orders/refunds, Admin management, payment methods, verification, webhooks, and tenant lifecycle commands.
- **Applications:** The Admin app has sections for overview, products, categories, orders, customers, settings, appearance, payments, verification, and audit logs. The Storefront has catalog, product, cart, address checkout, account, order history, registration, login, and password recovery pages.
- **Manual API guides:** Updated 11 files in `backend-laravel/tests/`. The shared setup creates run-specific values; create flows capture IDs for follow-up, and cleanup targets those IDs. The Admin login flow exports its access token for reuse.

## Remaining work

- The Storefront page tree does not show a payment confirmation flow, guest order lookup page, or saved-payment-method management page. Related backend routes exist; UI completion and end-to-end behavior still need review.
- Runtime behavior against the configured MySQL schema has not been verified in this review.
- The manual API flows are ready to run, but no API requests or application tests have been run as part of this update.

## Validation for this update

- `bash -n` passed for fenced shell snippets in `backend-laravel/tests/*.md`.
- `git diff --check` passed.

Begin with `backend-laravel/tests/manual-api-tests.md`. Keep the same shell open for a run so the exported token, session cookies, and newly created object IDs stay available for follow-up and cleanup.
