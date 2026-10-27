# Customer Storefront

The public shopping experience is tenant-branded and resolved from the storefront host. This document describes the customer journey from discovery through order history; it is a visual product specification, not an implementation.

## Visual direction

- **Feel:** editorial, warm, and product-led. Use a tenant-configurable logo, brand colors, typography, and corner radius; keep text contrast and focus indicators accessible regardless of theme.
- **Example store for the mockup:** **Atelier Supply**, a small home-goods shop. Product names and prices below are illustrative.
- **Layout:** responsive single-column mobile experience; wider screens use a restrained content width, large product photography, and two-column detail/checkout layouts.
- **Trust:** clearly show price, currency, availability, delivery/return information when configured, and the current checkout step. Never invent policies or inventory.

## Store home

```text
┌──────────────────────────────────────────────────────────────────────┐
│ ATELIER SUPPLY      Shop   Journal              Search  Account  Bag  │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Everyday objects, made to last.              [Shop new arrivals →]  │
│  Considered essentials for home and life.                            │
│                                      [large lifestyle hero image]    │
│                                                                      │
│  New arrivals                                          View all →    │
│  [image]       [image]       [image]       [image]                   │
│  Linen throw   Stoneware     Oak tray      Glass set                 │
│  $68           $42           $36           $54                       │
│                                                                      │
│  Small-batch pieces. Thoughtful materials.                           │
├──────────────────────────────────────────────────────────────────────┤
│ About     Shipping & returns     Contact        Newsletter   Social  │
└──────────────────────────────────────────────────────────────────────┘
```

Hero content, editorial sections, and newsletter signup are presentation ideas; only render them when the tenant has supplied the content and the required backend capability exists. The configured theme comes from tenant settings.

## Catalog and product detail

### Category/search results

- Breadcrumb, category title/description, result count, sort control, and product grid.
- Product cards show image, name, price/currency, compare-at price only when valid, and an availability cue.
- Filters and pagination should reflect supported catalog data. Empty category, no search matches, and unavailable products need clear states.

### Product page

```text
Home / Textiles
┌──────────────────────────────┬───────────────────────────────────────┐
│                              │ Linen throw                           │
│      Product image           │ $68 USD                               │
│      [thumbnail row]         │ Soft washed linen, woven in Portugal. │
│                              │ Colour   [Natural ▾]                  │
│                              │ Size     [Select size ▾]              │
│                              │ Quantity [−] 1 [+]                    │
│                              │ [Add to bag]                          │
│                              │ Shipping details · Product details    │
└──────────────────────────────┴───────────────────────────────────────┘
```

Variant selection is required when the product has variants. Show unavailable options as unavailable, prevent invalid quantities, and confirm add-to-bag without losing the user's place. Product and variant stock are separate in the current data model; the UI must use one authoritative availability rule supplied by the API.

## Bag and checkout

### Bag

Show item image, selected variant, unit price, quantity controls, remove action, subtotal, and a clear checkout button. Explain when prices or stock are revalidated. An empty bag should link back to shopping. Support a guest session as well as a signed-in customer.

### Checkout

Use a focused, low-distraction layout with a progress indicator: **Contact → Delivery → Payment → Review**. On mobile keep the order summary available in a disclosure; on desktop show it beside the form.

1. Contact email/phone and delivery address.
2. Payment method and final price breakdown.
3. Review items, address, totals, and explicit place-order action.
4. Confirmation page with order number, amount, and next steps.

Validate addresses and payment details with backend responses, preserve safe form progress where possible, and prevent duplicate submissions while a request is pending. Do not collect or store raw card data in application forms; use the configured payment provider's secure flow. Show failures inline and provide a safe retry.

The current schema stores order address snapshots and totals, but no shipping-rate, tax-rule, promotion, or discount-code entities. Keep those controls out of the committed design until their calculation and persistence rules are agreed.

## Account and authentication

- Login, registration, email/phone verification, forgot password, and reset password screens.
- Account overview, orders/order detail, saved addresses, payment methods, and settings.
- Use clear verification and retry states; never reveal whether a private account exists in a password-reset response.
- Guest checkout should not require account creation. Offer account creation after purchase only if supported.

## Responsive, accessible, and resilient behavior

- Use semantic headings, descriptive image alt text, keyboard-operable variant controls, visible focus, and labeled form errors.
- Announce cart quantity changes and checkout errors to assistive technology.
- Provide loading, empty, unavailable, offline/error, and success states for catalog, cart, and checkout.
- Honor tenant theme settings while enforcing contrast and readable text sizes; provide a safe fallback when a theme value is invalid.