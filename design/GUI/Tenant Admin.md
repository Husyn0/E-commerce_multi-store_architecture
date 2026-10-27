# Tenant Admin

The authenticated workspace for each store owner and authorized staff. Keep tenant context visible throughout; all records, search results, and actions are scoped to the current tenant.

## Visual direction

- **Feel:** focused operations workspace: neutral surfaces, deep violet primary actions, restrained status colors, and readable tabular data.
- **Navigation:** persistent sidebar on desktop, collapsible drawer on mobile, compact top bar with store identity, notifications, and account menu.
- **Roles:** show only actions permitted to the signed-in role; the server remains the source of authorization.
- **Feedback:** provide loading, empty, validation, saved, and error states. Confirm irreversible or money-moving actions.

## Application shell

```text
┌────────────────────┬──────────────────────────────────────────────────────────┐
│ [mark] NORTHSTAR   │ Search products and orders…    Help  Alerts  Owner ▾    │
├────────────────────┼──────────────────────────────────────────────────────────┤
│ OVERVIEW           │ Good morning, Alex                        [View store]  │
│  Dashboard         │                                                          │
│ CATALOG            │ Today's snapshot                                        │
│  Products          │ Orders 18       Gross sales $2,840       Low stock 4     │
│  Categories        │                                                          │
│  Inventory         │ Recent orders                       Low-stock products  │
│ SALES              │ #1048  Paid   $124   10:02          Linen throw     3   │
│  Orders            │ #1047  Shipped $86   09:44          Oak tray        2   │
│  Refunds           │                                                          │
│  Customers         │                                                          │
│ STORE              │                                                          │
│  Appearance        │                                                          │
│  Pages             │                                                          │
│  Settings          │                                                          │
└────────────────────┴──────────────────────────────────────────────────────────┘
```

Dashboard figures should state their time period and currency. If a metric is unavailable, show an explicit unavailable state rather than a guessed value.

## Main work areas

### Products and inventory

- Product list: search, category/status filters, sortable columns, pagination, stock, price, and publication state.
- Product editor: title, description, category, price/currency, images, SKU, status, and variants with attributes, SKU, price, and stock.
- Inventory: low-stock and out-of-stock views with a deliberate adjustment flow and a record of the change.
- Category manager: clear parent/child hierarchy with safe reordering and activation controls.

Products and variants both contain stock in the current schema. Make inventory ownership explicit in the API and interface; do not let an operator unknowingly edit two competing stock values.

### Orders and refunds

Order list supports filters for status, date, and order number. Detail view prioritizes the order state, immutable item/address snapshots, payment state, totals, and allowed next actions:

```text
← Orders
Order #ORD-2026-0001                       Paid · Oct 25, 2026
Customer: Jane Doe · jane@example.test                 Total: $124.00
─────────────────────────────────────────────────────────────────────
ITEMS                                  ACTIONS
Linen throw / Natural · qty 1 · $68    [Mark processing]
Oak tray · qty 1 · $36                 [Mark shipped]
                                        [Issue refund]
TIMELINE                               PAYMENT
Created 10:00 · Paid 10:01             Provider: Stripe · Succeeded
                                        Shipping address
                                        123 Main St, …
```

Actions must follow server-defined state transitions. A refund shows amount, remaining refundable balance, reason, and confirmation; display pending/failed/succeeded distinctly. Do not equate order status with payment status.

### Customers

Customer list and detail show only tenant-owned customer data. Detail can summarize orders, verification state, and saved addresses. Mask sensitive values where full values are not needed and avoid exposing payment-provider secrets.

### Settings and appearance

- **General:** store identity, locale, currency, and domain status.
- **Payments:** configured provider and connection state; never expose credentials.
- **Shipping:** show configured options only; shipping-rule persistence is not present in the current schema.
- **Verification:** email, SMS, and human-check provider status.
- **Staff:** staff access and role management, with a clear invitation/activation state.
- **Audit log:** actor, action, entity, and timestamp, with filters and permission checks.
- **Appearance:** logo, colors, fonts, radius, and layout choices from tenant theme settings. Preview in a storefront frame, with desktop/mobile preview and unsaved-change warning.
- **Pages:** manage supported static content; avoid promising a page editor beyond what the backend persists.

## Key interaction rules

- Keep store name and current environment visible; destructive actions name the affected object.
- Preserve a draft on recoverable validation/server errors and explain which fields need attention.
- Tables need keyboard access, stable pagination, accessible sort labels, and useful empty states.
- Ensure all order, product, customer, and settings views work on narrow screens without hiding essential actions.
- Permission-denied and expired-session states should be clear and offer a safe recovery path.
