# Backend/design progress review

**Review date:** 2026-10-28  
**Scope:** Compare `design/back-design.txt`, `design/db-design.txt`, and
`database/schema.mysql.sql` with the current Laravel backend, frontend
scaffolds, and `INITIALIZATION.md`. This review made no implementation or
design changes; this report is the only file written for this request.

## Current-session handoff — 2026-10-28

This update supersedes the older statements below wherever they say that the
Storefront is still only a Nuxt welcome screen, or that password reset,
guest-order lookup, saved-payment-method APIs, and product image/variant CRUD
are absent. Those backend/API additions and initial Storefront pages/proxy are
now present. The rest of the review is retained as historical analysis and
still needs to be checked against the current tree before treating it as
current fact.

### Work completed in the latest implementation pass

- Added customer OTP password-reset endpoints; successful reset revokes the
  customer's active Storefront sessions.
- Added authenticated email-verification request/confirm endpoints.
- Added tenant-scoped guest order lookup request/confirm endpoints using
  `guest_order_lookup` email OTP.
- Added Stripe saved-payment-method endpoints: payment configuration,
  SetupIntent creation and verification, list, make-default, and removal.
  SetupIntent completion validates tenant/user metadata and the payment
  method's Stripe customer association. Raw card data is not handled.
- Added Admin product image create/update/delete and variant
  create/update/delete endpoints. Variant attribute combinations are compared
  canonically; active-cart references block deletion.
- Updated product deletion to lock the product and check product/variant cart
  references in the same transaction. Historical order references continue to
  depend on the imported database FK behavior; do not assume the documented
  `SET NULL` behavior until that schema gap is resolved.
- Registered the new endpoints in `backend-laravel/routes/api.php`; configured
  `STRIPE_PUBLISHABLE_KEY`.
- Added initial Storefront pages/components and a Nuxt server-side API proxy.
  The proxy only accepts `storefront/` routes, uses the incoming `Host` (not a
  client-supplied `x-forwarded-host`) to preserve tenant resolution, forwards
  only Storefront cookies, and uses a 2-second timeout with no retries.
- Fixed expired checkout idempotency cleanup to clear the nullable order
  reference before deleting the row, both when a key is reused and during
  pruning. This preserves the restrictive FK while allowing expiry cleanup.
- Changed failed webhook processing to return HTTP 500 after recording the
  failed attempt, allowing the provider's redelivery policy to retry it. Failed
  events still stop being retried after the existing five-attempt limit.
- Traced the Storefront 404 configuration: the root page and tenant API route
  exist. The local backend domain is `storefront.localhost`, so the local
  tenant URL must include that suffix (for example,
  `http://shop.storefront.localhost:3000`) and a tenant with the `shop`
  subdomain must exist. No browser check was run.

### Validation performed (no tests)

No test files were created and no tests were run, as requested.

- `backend-laravel/vendor/bin/pint --dirty --format agent` passed for the
  current PHP changes.
- `php -l` passed for the changed CheckoutController, PruneTenantData command,
  and PaymentWebhookController; `git diff --check` passed. Behavior remains
  unverified because no tests or browser checks were run.
- PHP syntax lint passed for the changed PHP controllers.
- Pint check passed for the changed controllers after formatting the modified
  `AccountController`.
- `php artisan route:list --path=storefront --except-vendor` succeeded and
  reported 33 Storefront routes.
- Storefront `npm run typecheck` passed. It emitted a warning that Node.js
  22.19.0 is below the Nuxt-supported range (`^22.21.0 || ^24.11.0 ||
  >=26.0.0`).
- `git diff --check` passed.
- PHP Language Server diagnostics include many magic Eloquent model-property
  warnings in new controllers; these are static-analysis warnings and not PHP
  syntax errors. Review any newly surfaced diagnostics while continuing work.

### Current implementation gaps / caution

- The new API endpoints do not yet have all matching Storefront UI flows:
  customer registration, OTP password reset/email verification, guest-order
  lookup, and saved-card management still need user-facing pages/components.
- The Storefront checkout UI remains unavailable/incomplete; implement the
  secure Stripe Payment Element flow before enabling card checkout. Do not send
  raw card data through Laravel.
- Do not infer completion from routes or type-check alone. The prior blocking
  items in this report—including order-item snapshot/FK behavior, configured
  phone/email verification at checkout, idle-session pruning, per-tenant
  provider CLI operations/readiness, MySQL verification, Admin UI completeness,
  and shared API packages—remain open unless explicitly re-verified and
  resolved. The idempotency expiry and webhook failure-response changes above
  have not been behaviorally verified.
- Before this continuation, the worktree had pre-existing modifications to
  `report.md` and `run.md`. Preserve those edits; the PHP changes listed above
  are from this continuation.

### Suggested continuation for the next session

1. Read this handoff and the design contracts in `design/Storefront.txt`,
   `design/back-design.txt`, and `design/db-design.txt`; treat the existing
   blocking-gap list below as a starting point, not verified current truth.
2. Inspect `git status --short`, then review the current backend routes,
   controllers, models, schema, and Storefront pages before changing code.
   Keep changes scoped and preserve dirty work.
3. Continue backend correctness work: resolve checkout verification
   requirements and the schema/order snapshot deletion mismatch. The
   idempotency expiry/prune FK conflict and webhook failed-processing response
   were addressed in the current continuation but remain unverified. Confirm
   the actual imported schema, not just design text.
4. Continue implementing the missing Storefront flows and secure checkout UI
   against the existing API. Keep tenant host context, CSRF/cookie forwarding,
   and no raw-card-data rules intact. Then fill the missing Admin screens and
   operational/readiness gaps.
5. The user explicitly asked not to create or run tests in the implementation
   request. Respect that unless the user changes the instruction. Use
   non-test validation (PHP lint, Pint, route listing, frontend type-check/build
   if requested, and `git diff --check`) and clearly label behavior that remains
   unverified.
6. Update this `report.md` after material progress: date the handoff, record
   precise files/features changed and commands/results, distinguish verified
   facts from open questions, and remove or mark superseded claims rather than
   silently leaving conflicting status entries.

## Executive summary

At the time of the original review, the backend had a substantial
tenant-aware foundation and the main shopper checkout/payment path was
implemented, but it was **not design-complete or production-ready**. The
imported MySQL schema has still not been verified end-to-end against the
backend.

The highest-risk issue is that completed checkout idempotency records cannot be
deleted while their `order_id` foreign key points to an order. Both checkout's
expired-key path and the scheduled pruning job attempt such a deletion, while
the schema specifies `ON DELETE RESTRICT`. This can break checkout retries and
the scheduled prune after the 24-hour idempotency window.

## Progress snapshot

| Area | Status | Evidence / remaining work |
|---|---|---|
| Tenant boundaries and host routing | **Implemented in backend** | Host resolver uses configured Admin/Storefront domains and env custom-domain allowlist; route groups enforce host type and tenant status before authentication. Trusted proxies are configurable. |
| No-platform model | **Implemented in current schema/backend** | Schema has tenant/store concepts and no platform-fee/account fields; scan during the prior validation found no `platform_fee`, `application_fee`, or `platform_account_id` matches in database/backend. |
| Schema-backed models | **Implemented** | Models exist for tenants, users/sessions, catalog, carts, orders, refunds, OTP, audit, and webhook records. |
| Admin API | **Partially implemented** | Auth, catalog/category, inventory, customer, order/refund, settings/theme, and audit-log APIs exist. Admin frontend is still the Vite/Vue starter, so the required screens, status polling, reset views, and driver warning UI are absent. |
| Storefront API | **Partially implemented** | Catalog, cart, checkout, email OTP issuance, registration/login, account, addresses, and signed-in order history exist. Storefront app is still Nuxt’s welcome page; SSR proxy, pages, verification widget, and branded suspended screen are absent. |
| Tenant payments | **Partially implemented** | Demo and Stripe PaymentIntent/refund/cancel adapters use the tenant connected account; no application/platform fee flow was found. Stripe behavior has mocked tests, not a live Stripe or imported-MySQL integration test. |
| Webhooks | **Partially implemented** | Shared signed route, account/tenant resolution, event deduplication, event filtering, suspended receipt flag, reconciliation, and payload retention fields exist. Retry/failed-response behavior needs correction (see blocker below). |
| Verification and reset | **Partially implemented** | Email OTP and server-side Turnstile/hCaptcha/demo human-check paths exist. Phone OTP and Storefront password reset are missing; Admin reset UI is absent. |
| Tenant lifecycle CLI | **Implemented with gaps** | Provision/suspend/recover, TTY reset OTP, and prune commands exist and are env-gated. Prune has FK and idle-session issues; tenant provider settings have no corresponding CLI management command. |
| Data retention | **Partially implemented** | Expired carts/codes/verifications/sessions/idempotency records are selected and webhook payloads are redacted. Idempotency record deletion is incompatible with its FK; idle-session pruning is not implemented. |
| Shared frontend packages/API contract | **Not implemented** | `packages/` has no implementation files or package manifests; no shared API types/client or OpenAPI contract was found. |
| MySQL acceptance | **Not verified** | The test suite is configured for SQLite, and no end-to-end execution against the imported MySQL schema was recorded. |

## Blocking gaps

### 1. Expired checkout idempotency records cannot currently be deleted

**Current continuation status:** the expired-key path and pruning command now
clear `order_id` before deleting the row. PHP syntax and formatting checks
passed, but the behavior has not been tested.

`checkout_idempotency_records.order_id` has `ON DELETE RESTRICT`. Once checkout
has created an order, the record references that order. The expiry branch in
`CheckoutController` deletes the expired record directly, and
`PruneTenantData` also deletes expired records directly. MySQL will reject both
operations for records with non-null `order_id`.

**Required resolution:** preserve the design’s authoritative expiry and
`ON DELETE RESTRICT` rules, but safely detach or otherwise retain the order
reference before deleting the expired idempotency row (inside a transaction),
or explicitly revise the schema/design contract. Add a MySQL test for both
expired-key reuse and scheduled pruning.

### 2. Schema order-item delete behavior disagrees with the design

The design specifies `order_items.product_id` and `variant_id` as nullable
references with `ON DELETE SET NULL`, keeping `product_snapshot` authoritative.
The installed schema instead uses `ON DELETE RESTRICT` for both. The Admin
product delete endpoint also refuses deletion when order items reference the
product/variant. Historical orders therefore cannot follow the documented
catalog-deletion behavior.

**Required resolution:** align the schema foreign keys and product deletion
flow with the snapshot-retention contract, and verify historical order API
responses after catalog rows are deleted.

### 3. Webhook processing failures are acknowledged as successes

**Current continuation status:** a recorded processing failure now returns
HTTP 500 so the provider may redeliver it. Existing failed-event handling
limits processing to five attempts. This has not been tested.

When a webhook state update throws a `QueryException`, the controller marks
the row failed but returns the non-null event; the final response reports
`status: processed` (HTTP 200). Processing is synchronous and does not enqueue a
retry. This does not meet the design’s failed-event retry/redelivery contract
and can cause the payment provider to stop retrying an event that failed
locally.

**Required resolution:** return a failure response for failed processing or
implement the designed bounded retry/re-enqueue path; ensure event status,
attempt count, response code, and retry behavior agree. Add tests for first
failure, retry, exhaustion, and duplicate delivery.

### 4. Phone verification and password-reset coverage are incomplete

- `require_phone_verification=true` makes Storefront registration return 503;
  no phone OTP issuance/consumption is wired.
- The checkout request does not accept or consume configured email/phone OTP
  fields. `EnforceConfiguredVerification` checks a human-verification row, not
  the configured email/phone verification codes.
- Password reset is implemented for Admin only. Storefront customer password
  reset/API and the designed `ForgotPassword.vue` / `ResetPassword.vue` screens
  are absent.
- Human verification accepts `response_token`, while the design describes
  `human_verification_token`; the adapter contract should be standardized.

**Required resolution:** implement the supported verification combinations and
reset flows, or explicitly narrow the design and tenant configuration so the
API does not advertise an unsupported requirement.

### 5. Production tenant-driver configuration is not operable through CLI

Admin settings correctly expose driver/provider values read-only, and the
provision command uses global defaults. However, there is no CLI command to
configure a tenant’s `payment_driver`, `stripe_account_id`, email/SMS provider,
or verification drivers. A tenant provisioned with the default demo settings
cannot be switched to its Stripe connected account through the implemented
operator workflow.

**Required resolution:** add audited, env-gated CLI commands for the
design-approved tenant settings, validate provider/secret/account readiness,
and document safe operations. Do not add Admin edit controls for these fields.

### 6. Backend/frontend completion and MySQL verification remain outstanding

`apps/admin/src/App.vue` still renders `HelloWorld`; `apps/storefront/app/app.vue`
still renders `NuxtWelcome`. There are no feature pages, shared
`packages/ui`, `packages/types`, `packages/api-client`, SSR proxy/cache rules,
or OpenAPI contract. The 52-route API therefore has no complete user-facing
application flow.

The available test suite is primarily unit/contract testing with SQLite. The
backend depends on MySQL-specific constraints, generated columns, composite
foreign keys, and locking semantics that have not been integration-tested
against the imported schema.

## Important remaining gaps

1. **Session pruning:** the Storefront middleware enforces `SESSION_IDLE_TTL`,
   but `PruneTenantData` deletes only expired or revoked sessions; it does not
   prune sessions whose `last_used_at` is idle beyond the configured TTL.
2. **Saved payment methods and variant management:** the schema/models exist,
   but there is no payment-method API or payment-method CRUD; checkout explicitly
   prohibits `payment_method_id`. Product APIs do not create/update/delete
   variants or enforce canonical attribute-combination uniqueness. Variant
   inventory adjustment alone is implemented.
3. **Guest order lookup:** the current design requires a tenant-scoped,
   rate-limited lookup using `guest_order_lookup` OTPs. The backend has no
   lookup endpoint and `INITIALIZATION.md` says guest lookup is out of scope.
   Resolve this design/documentation conflict and implement the chosen contract.
4. **Dashboard and readiness:** the Admin dashboard endpoint and Overview UI
   are now implemented with tenant-scoped seven-day UTC sales totals that
   exclude cancelled and non-succeeded orders. The current `/health/ready`
   still checks only database connectivity; design also calls for global and
   per-tenant driver readiness without exposing credentials.
5. **Driver warning/status UX:** settings API returns `driver_warning` and
   `/api/admin/tenant` exposes status, but the Admin warning banner and
   suspended/recovered status screen are not implemented.
6. **Custom-domain administration:** routing honors the environment allowlist,
   but no CLI workflow writes/normalizes `tenants.custom_domain`. Admin UI
   management is intentionally prohibited; document the operator/database
   procedure or add a controlled CLI operation.
7. **Audit completeness:** most Admin mutations and webhook state changes call
   the audit logger. The `tenant.read` action is in the design vocabulary, but
   `GET /api/admin/tenant` does not write that audit action. Verify the complete
   action vocabulary against each endpoint.
8. **Order snapshots/FKs:** until the FK mismatch above is resolved, the backend
   cannot rely on the documented “snapshot remains authoritative after catalog
   deletion” behavior.
9. **Human-verification lifecycle:** verification is stored for five minutes
   and is not consumed by registration/checkout; the unique session-token row
   also stores only one action at a time. Confirm intended replay and
   simultaneous-action semantics and test them.
10. **Provisioning reset instructions:** the provision command prints that the
    Admin should use the password-reset endpoint. The design instead says the
    operator must run `tenant:reset-admin-password`; with the default log mail
    provider, the ordinary email-reset path cannot deliver an OTP.
11. **Webhook operational coverage:** provider signatures, supported event
    handling, suspended receipt flag, and payload redaction exist, but webhook
    event reconciliation and retry lifecycle have not been exercised against
    MySQL or provider test events.
12. **No build/release gate for the monorepo:** frontend workspaces are not
    connected to a root workspace/build/test contract; no end-to-end browser
    test currently validates Admin or Storefront flows.

## Progress already present

- Tenant-scoped model layer and host resolution with explicit Admin/Storefront
  host context; unknown hosts fail closed.
- Admin JWT access tokens with refresh sessions in `user_sessions`, and
  Storefront cookie sessions with hashed tokens and CSRF checks.
- Tenant-scoped catalog, cart, guest checkout, order snapshots, 24-hour
  principal-bound idempotency records, server-side price/stock recalculation,
  and Stripe/demo payment gateway abstractions.
- Shared payment webhook route, signature validation, tenant account resolution,
  deduplication, payment/refund reconciliation, and suspended-at-receipt field.
- Admin product/category/inventory/customer/order/refund/settings/theme/audit
  APIs; Storefront catalog/cart/auth/account/address/order-history APIs.
- Email OTP, server human-verification integration, env-gated lifecycle
  commands, terminal-only OTP fallback, and scheduled data pruning.
- No `.env` secrets were generated as part of the reviewed work. The sample
  secret fields remain blank.

## Validation evidence and limits

The latest recorded backend validation before this review was **50 tests and
144 assertions passing**, Pint formatting passing, Composer validation
passing, `git diff --check` passing, and **52 API routes registered**. A scan
found no platform fee/account fields in the database/backend paths inspected.
These checks do **not** prove transaction behavior on MySQL, Stripe behavior
against a real test account, email/SMS delivery, or complete browser flows.

## Recommended completion order

1. Fix and MySQL-test idempotency expiry/pruning; align order-item FK delete
   behavior with snapshot semantics.
2. Fix webhook failed-event response/retry semantics and add MySQL/provider
   contract tests.
3. Close verification/reset gaps and add CLI tenant-driver configuration,
   including per-tenant readiness reporting.
4. Implement variant CRUD/attribute uniqueness, saved payment methods if kept
   in V1, and the designed guest order lookup; reconcile documentation.
5. Implement Admin and Storefront applications, SSR proxy/cache behavior,
   dashboards, status/warning/reset screens, shared packages, and API contract.
6. Add complete MySQL and browser end-to-end gates before calling the
   monorepo/backend complete.