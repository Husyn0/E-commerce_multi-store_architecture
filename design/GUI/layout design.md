# Interface design system and application boundaries

This document defines shared visual and interaction rules for three separate user experiences. It describes the intended design, not verified running applications.

## Products and boundaries

| Experience | Audience | Entry point | Primary responsibility |
|---|---|---|---|
| Platform Console | Platform operators | `console.example.com` | Provision and oversee tenants, plans, billing, events, and platform health |
| Tenant Admin | Store owners and staff | `admin.<store-domain>` | Operate one tenant's catalog, orders, customers, and settings |
| Storefront | Shoppers | `<store-domain>` | Discover products, purchase, and manage customer account |

```text
┌───────────────────┐   ┌───────────────────┐   ┌───────────────────┐
│ Platform Console  │   │ Tenant Admin      │   │ Storefront        │
│ operator identity │   │ tenant identity   │   │ tenant by host    │
└─────────┬─────────┘   └─────────┬─────────┘   └─────────┬─────────┘
          │ /api/platform/*        │ /api/admin/*          │ /api/storefront/*
          └────────────────────────┴───────────────────────┘
                                   │
                         ┌─────────▼─────────┐
                         │ Laravel API       │
                         │ auth + tenant scope│
                         └─────────┬─────────┘
                                   │
                         ┌─────────▼─────────┐
                         │ MySQL             │
                         │ tenant-owned data │
                         └───────────────────┘
```

The architecture notes describe the storefront as SSR in one place and as a Vite/Vue app in another. Confirm the rendering mode and deployment boundary before implementation. Likewise, host-based tenant resolution and tenant claims in a token are distinct inputs; the API must define which is authoritative for each route and reject mismatches.

## Shared design tokens

- **Brand:** deep violet primary action; neutral gray surfaces and borders. Storefront colors may be tenant-configured.
- **Typography:** a readable sans-serif UI family with a clear heading/body/metadata scale.
- **Status:** semantic success, warning, danger, and neutral states; never communicate status by color alone.
- **Spacing and shape:** consistent 4/8px spacing rhythm, restrained card radius, clear separation between page sections.
- **Accessibility:** WCAG AA contrast target, visible keyboard focus, semantic landmarks/headings, reduced-motion support, and accessible names for icon-only controls.

Shared components should cover buttons, form controls, alerts, badges, dialogs, tables, pagination, and loading/empty/error states. Do not share tenant-specific navigation or authorization decisions merely to reduce UI duplication.

## Navigation and responsive behavior

- Console and Admin use a sidebar + top bar at desktop widths. At narrow widths, navigation becomes a dismissible drawer with focus management.
- Storefront uses a compact customer-facing header, prominent search/cart entry points, and a footer with tenant-provided links.
- Keep the active tenant/store identity visible in Admin and show a contextual breadcrumb on deep pages.
- Tables retain readable labels and essential actions on mobile; use horizontal overflow or row cards, not clipped columns.
- Dialogs trap and restore focus; route changes move focus to the page heading.

## Data and states

Every data-backed view must define initial loading, populated, empty, filtered-no-results, permission-denied, stale-session, and recoverable error states as applicable. Show only values returned or calculated under documented server rules. A successful-looking UI state must never stand in for a failed or pending request.

Use the tenant setting `theme_config` for storefront appearance, validate it, and provide safe defaults. Keep platform operator UI and tenant UI visually related but clearly distinguishable so an operator can recognize when they are impersonating a tenant.

## Implementation boundary notes

- These files are design specifications; the workspace currently contains no app source, package manifests, or executable tests to verify.
- The database design is the source of truth for what is persisted. Features without backing data or an explicitly defined derived calculation should be labeled as future scope, not shown as implemented.
- Authentication, tenant isolation, role checks, and payment/refund outcomes must be enforced by the API; hiding a control in the UI is not authorization.