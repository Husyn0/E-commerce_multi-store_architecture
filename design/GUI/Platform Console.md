# Platform Console

The internal control plane for platform operators. It manages tenants and platform health; it is not a tenant's day-to-day store admin.

## Visual direction

- **Feel:** precise, calm, operational. Use a cool neutral canvas, white surfaces, deep violet for primary actions, and semantic green/amber/red for status.
- **Information density:** compact tables for operations, generous spacing on overview cards, and clear page titles with a single primary action.
- **Shell:** persistent left navigation and a compact top bar. On smaller screens the navigation becomes a drawer; tables retain key columns and allow horizontal scrolling rather than collapsing into unreadable text.
- **Status:** always pair color with a label or icon. Confirm destructive or impersonation actions and show the result.

## Application shell

```text
┌────────────────────┬──────────────────────────────────────────────────────────┐
│ violet / PLATFORM  │ Search tenants…                 Help   Operator ▾        │
├────────────────────┼──────────────────────────────────────────────────────────┤
│ OVERVIEW           │ Platform overview                         [Add tenant]  │
│  Dashboard         │                                                          │
│ OPERATIONS         │  Active tenants    Trial tenants    MRR       Attention  │
│  Tenants           │  47                8               $3,240    2 events    │
│  Plans             │                                                          │
│  Billing           │  Tenant growth                    Platform activity     │
│  Webhooks          │  [12-month chart]                 [recent events]       │
│  Health            │                                                          │
│                    │  Recently created tenants                               │
│                    │  Store       Plan       Status       Created      →     │
│                    │  northstar   Pro        Active       2 min ago    →     │
│                    │  fieldnotes  Free       Trial        1 hour ago   →     │
│                    │                                                          │
│  Operator profile  │                                                          │
└────────────────────┴──────────────────────────────────────────────────────────┘
```

The top-level metrics are summaries, not substitutes for the tenant and billing records. Clicking a metric opens the corresponding filtered list.

## Key screens

### Dashboard

- Metric cards: active tenants, trial tenants, monthly recurring revenue, and webhook events needing attention.
- Tenant growth chart with a selectable 30-day / 12-month range and accessible text summary.
- Recent tenant list and a short platform activity feed.
- Every metric needs a defined calculation and time window before it is treated as authoritative. MRR and billing history require subscription/invoice data that is not in the current schema.

### Tenants

Search and filter by name/domain, plan, and lifecycle status. Rows show tenant, domain, plan, status, creation date, and an action menu. Support loading, empty, no-results, and request-error states.

Tenant detail uses tabs for **Overview**, **Settings**, **Domains**, **Billing**, and **Activity**:

```text
← Tenants
northstar.example.com   Active · Pro                         [Suspend ▾]
Created Oct 1, 2026                                      [Impersonate]
────────────────────────────────────────────────────────────────────────
Overview   Settings   Domains   Billing   Activity

Products 143     Orders 2,341     Customers 892     Storage 1.2 / 10 GB
Payment provider: Stripe · Connected                  Last event: 2m ago
Recent changes and tenant health
```

Suspension and impersonation are privileged actions. The operator must confirm the target tenant; impersonation should explain its scope and expiry, then provide an obvious exit path. Do not present an action as available unless the backend enforces and audits it.

### Provision tenant

Use a short, validated flow: tenant name and subdomain → initial owner account → plan and defaults → review and provision. Show field-level errors, preserve entered values after recoverable errors, and render a completion summary. Never imply that a tenant was created before the server confirms success.

### Plans, billing, webhooks, and health

- **Plans:** plan list with feature/limit comparison and a clearly separated edit action.
- **Billing:** invoices and payment state with tenant filters; show “Not configured” until subscription/invoice data exists.
- **Webhooks:** event type, tenant, provider event ID, received/processed time, and processing state. Payload inspection is permission-gated and redacts secrets and personal data.
- **Health:** dependency checks with last checked time, current state, and actionable detail; distinguish a failed check from an unknown or stale check.

## Interaction and accessibility

- Keep tenant identity visible on detail and confirmation screens.
- Use keyboard-operable navigation, visible focus, semantic headings, and labeled form controls.
- Tables need stable sorting, pagination, and accessible row/action names.
- Use skeletons only while loading; provide retry actions for recoverable failures and never display fabricated success data.
