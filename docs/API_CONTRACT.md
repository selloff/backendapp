# API contract (v1)

Base path: `/api/v1`  
Auth: `Authorization: Bearer {sanctum_token}` (except public auth routes).

**Status:** Pass 10 — staging rehearsal (ETL import/verify, security audit, load test script).

---

## Envelope

All JSON responses use:

```json
{
  "success": true,
  "data": { },
  "message": "optional human message"
}
```

Errors:

```json
{
  "success": false,
  "message": "Summary",
  "errors": { "field": ["..."] }
}
```

HTTP **422** for validation failures; **403** for authorization; **401** when unauthenticated.

---

## Auth

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| POST | `/auth/register` | Public | Buyer or vendor registration |
| POST | `/auth/login` | Public | Returns `{ token, me }` |
| POST | `/auth/logout` | Bearer | Revoke current token |
| POST | `/auth/forgot-password` | Public | Sends reset email |
| POST | `/auth/reset-password` | Public | Token + new password |
| GET | `/auth/me` | Bearer | SPA session context |
| PATCH | `/auth/me` | Bearer | Update profile fields |

OAuth (redirect flow):

| Method | Path |
|--------|------|
| GET | `/auth/oauth/{provider}/redirect` |
| GET | `/auth/oauth/{provider}/callback` |

Providers: `facebook`, `google`, `vkontakte`.

---

## Platform (Pass 1)

| Method | Path | Auth | Permission |
|--------|------|------|------------|
| GET | `/health` | Public | — |
| GET | `/public/platform-brand` | Public | — |
| GET | `/settings` | Bearer | `admin_panel` |
| PUT | `/settings` | Bearer | `admin_panel` |
| GET | `/users/index-meta` | Bearer | `admin_panel` |
| GET/POST/GET/PUT/DELETE | `/users`, `/users/{id}` | Bearer | `admin_panel` |
| GET | `/permissions` | Bearer | `admin_panel` |
| GET | `/roles/create-meta` | Bearer | `admin_panel` |
| GET/POST/GET/PUT/DELETE | `/roles`, `/roles/{id}` | Bearer | `admin_panel` |
| POST | `/media/upload` | Bearer | any authenticated user |

### `PUT /settings`

Body: `{ "settings": { "site_name": "...", ... }, "group": "general" }`

### `POST /media/upload`

Multipart: `file` (required), `context` (`profile`|`product`|`temp`, default `temp`).

Returns `{ path, url, disk, filename }` using legacy `uploads/*` path semantics.

### `GET /auth/me` fields

| Field | Type | Notes |
|-------|------|-------|
| `user` | object | id, name, slug, email, avatar, role, vendor flags |
| `roles` | string[] | Spatie role names |
| `permissions` | string[] | All permission names |
| `platform_settings` | object | Brand, currency, feature flags |
| `image_url_prefix` | string | S3/public base for relative media paths |
| `is_demo` | boolean | Demo environment flag |

---

## Admin & vendor dashboards (Pass 5)

All admin routes require `admin_panel` unless noted. Vendor routes require `vendor`.

### Product moderation

| Method | Path | Notes |
|--------|------|-------|
| GET | `/admin/products` | `?pending_only=1`, `?status=`, `?search=` |
| POST | `/admin/products/{product}/approve` | Publish + verify |
| POST | `/admin/products/{product}/reject` | `{ reason }` required |

### Orders & refunds

| Method | Path | Notes |
|--------|------|-------|
| GET | `/admin/orders` | All orders |
| GET | `/admin/orders/{order}` | Order detail |
| POST | `/admin/orders/{order}/refunds` | Create refund request |
| GET | `/admin/refunds` | Refund queue |
| POST | `/admin/refunds/{refundRequest}/approve` | Process refund + restore stock |
| POST | `/admin/refunds/{refundRequest}/reject` | Reject refund |

### Payouts & vendor earnings

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/vendor/earnings` | vendor | Balance summary |
| GET | `/vendor/payouts` | vendor | Vendor payout history |
| POST | `/vendor/payouts` | vendor | `{ amount, payout_info? }` |
| GET | `/admin/payouts` | admin | Payout queue |
| POST | `/admin/payouts/{id}/approve` | admin | Approve payout |
| POST | `/admin/payouts/{id}/reject` | admin | `{ reason? }` |
| GET | `/vendor/orders` | vendor | Orders containing seller items |

### Location, CMS, support, membership

| Method | Path | Notes |
|--------|------|-------|
| GET/POST | `/admin/locations/countries` | Country list/create |
| GET/POST | `/admin/locations/states` | State list/create |
| GET/POST | `/admin/locations/cities` | City list/create |
| GET/POST | `/admin/cms/blog/posts` | Blog posts |
| GET/POST | `/admin/cms/blog/categories` | Blog categories |
| GET/POST | `/admin/cms/pages` | Static pages |
| GET | `/admin/support/tickets` | Support queue |
| POST | `/admin/support/tickets/{id}/reply` | Admin reply |
| GET/POST | `/admin/membership-plans` | Membership CRUD (`membership` permission on POST) |

---

## Payments & wallet (Pass 4)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/payments/methods` | Public | Enabled gateways (wallet, bank transfer, Stripe, COD) |
| POST | `/wallet/deposits` | Bearer | `{ amount, payment_method: bank_transfer\|stripe\|demo }` |
| POST | `/wallet/deposits/{id}/complete` | Bearer | `payment_settings` — approve pending deposit |
| POST | `/payments/bank-transfers/{id}/approve` | Bearer | `payment_settings` — confirm bank transfer order |
| POST | `/webhooks/stripe` | Public | Stripe signature (or test payload when verify disabled) |

### Checkout payment completion

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| POST | `/checkout/stripe` | Bearer | `{ checkout_token }` → `{ payment_url, session_id }` |
| POST | `/checkout/bank-transfer` | Bearer | `{ checkout_token, payment_note? }` → pending order |

Bank transfer orders stay `awaiting_payment` until an admin approves the transfer request.

---

## Shipping (Pass 4)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/shipping/quote` | Public | `?country_id=&state_id=&seller_id=` |
| POST | `/cart/shipping` | Bearer | `{ shipping_method_id, country_id?, state_id? }` |

---

## Catalog (Pass 3)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/products` | Public | Paginated product list |
| GET | `/products/{id\|slug}` | Public | Product detail |
| POST | `/products` | Bearer | `products` permission — vendor create |
| PUT | `/products/{product}` | Bearer | Owner or admin |
| DELETE | `/products/{product}` | Bearer | Owner or admin |
| GET | `/categories` | Public | `?roots_only=1` for top-level |
| GET | `/categories/{category}` | Public | Category detail |
| GET | `/brands` | Public | Brand list |

---

## Vendor shop (Pass 3)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/vendors/{slug}` | Public | Shop profile + products |
| GET | `/vendors/me/profile` | Bearer | `vendor` permission |
| PUT | `/vendors/me/profile` | Bearer | Update shop profile |

---

## Cart & checkout (Pass 3)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/cart` | Bearer | Current user cart + totals |
| POST | `/cart/items` | Bearer | `{ product_id, quantity? }` |
| PATCH | `/cart/items/{cartItem}` | Bearer | Update quantity |
| DELETE | `/cart/items/{cartItem}` | Bearer | Remove line item |
| POST | `/cart/coupon` | Bearer | `{ coupon_code }` |
| DELETE | `/cart/coupon` | Bearer | Remove applied coupon |
| POST | `/checkout` | Bearer | `{ payment_method }` — creates checkout session |
| POST | `/checkout/wallet` | Bearer | `{ checkout_token }` — wallet payment (Pass 3) |

Supported checkout `payment_method` values in Pass 3: `wallet_balance`, `bank_transfer`, `cash_on_delivery` (wallet completion implemented).

---

## Orders (Pass 3)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/orders` | Bearer | Buyer order history |
| GET | `/orders/{order}` | Bearer | Buyer or admin |

---

## Reviews & wishlist (Pass 3)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/products/{product}/reviews` | Public | Approved reviews |
| POST | `/products/{product}/reviews` | Bearer | `{ rating, review? }` |
| GET | `/wishlist` | Bearer | User wishlist |
| POST | `/wishlist/{product}` | Bearer | Add to wishlist |
| DELETE | `/wishlist/{product}` | Bearer | Remove from wishlist |

---

## Paginated lists

List endpoints return Laravel paginator inside `data`:

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [],
    "last_page": 3,
    "per_page": 15,
    "total": 42,
    "from": 1,
    "to": 15
  }
}
```

### Common query parameters

| Param | Type | Default | Notes |
|-------|------|---------|-------|
| `page` | int | 1 | 1-based |
| `per_page` | int | 15 | Max **100** |
| `sort` | string | varies | Route-specific allowlist |
| `direction` | `asc` \| `desc` | `desc` | Per controller |
| `search` | string | — | Full-text where supported |

---

## Health

| Method | Path | Auth |
|--------|------|------|
| GET | `/health` | Public |

Returns DB and storage connectivity status.

---

## Module prefixes (planned)

Expand this section as passes ship. Prefix pattern mirrors legacy domains.

| Prefix | Module | Pass |
|--------|--------|------|
| `/auth/*` | User | 1 |
| `/users/*`, `/roles/*` | User, Admin | 1, 5 |
| `/settings/*` | Admin | 1 |
| `/media/*` | Media | 1 |
| `/products/*`, `/categories/*`, `/brands/*` | Catalog | 3 |
| `/vendors/*` | Vendor | 3 |
| `/cart/*`, `/checkout/*` | Cart | 3 |
| `/orders/*`, `/refunds/*`, `/quotes/*` | Order | 3 |
| `/payments/*`, `/wallet/*` | Payment | 4 |
| `/shipping/*` | Shipping | 4 |
| `/payouts/*`, `/earnings/*` | Payout | 4 |
| `/escrow/*` | Escrow | 4 |
| `/affiliate/*` | Affiliate | 4 |
| `/reviews/*`, `/wishlist/*` | Review | 3 |
| `/blog/*`, `/pages/*` | Content | 5 |
| `/support/*` | Support | 5 |
| `/locations/*` | Location | 2 |

---

## Controller action pattern

Legacy CI4 methods map to REST + action endpoints:

| Legacy CI4 | API v1 |
|------------|--------|
| `Controller::index()` | `GET /{resources}` |
| `Controller::new()` | `GET /{resources}/create-meta` |
| `Controller::create()` | `POST /{resources}` + FormRequest + Action |
| `Controller::edit($id)` | `GET /{resources}/{id}/edit-meta` |
| `Controller::update($id)` | `PUT/PATCH /{resources}/{id}` |
| `Controller::delete($id)` | `DELETE /{resources}/{id}` |
| Custom (`approve`, `ship`, `refund`) | `POST /{resources}/{id}/{action}` |

---

## Payment webhooks

Public POST endpoints (no Bearer auth; signature verified):

| Path | Gateway |
|------|---------|
| `/webhooks/stripe` | Stripe |
| `/webhooks/razorpay` | Razorpay |
| `/webhooks/paytabs` | PayTabs |
| `/webhooks/yoomoney` | YooMoney |
| `/webhooks/mercadopago` | Mercado Pago |
| `/webhooks/dlocalgo` | dLocal Go |

---

## Resources (to define per module)

Use Laravel API Resources for consistent shapes. Admin list endpoints may use `*AdminResource` variants with extra fields (mirroring DownstreamX pattern).

---

## Mobile compatibility (Pass 9)

See `docs/MOBILE_API_MIGRATION.md` for the full legacy JWT → Sanctum mapping.

### Canonical mobile routes

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/mobile/health` | Public | Sanity check |
| POST | `/mobile/login` | Public | Mobile envelope + Sanctum token |
| POST | `/mobile/register` | Public | Accepts `fullname` or `first_name`/`last_name` |
| GET | `/mobile/products` | Public | Paginated catalog, `meta.pagination` |
| GET | `/mobile/products/{id\|slug}` | Public | Product detail |
| GET | `/mobile/categories` | Public | Root categories with `image_url` |
| GET | `/mobile/profile` | Bearer | Mobile user shape |
| POST | `/mobile/cart/items` | Bearer | Add to cart |
| POST | `/mobile/checkout/wallet` | Bearer | One-step wallet checkout |
| GET | `/mobile/orders` | Bearer | Buyer orders (mobile envelope) |
| POST | `/mobile/wishlist/toggle` | Bearer | Add/remove wishlist |

### Legacy compatibility shims (same paths as CI4)

| Method | Path | Replacement |
|--------|------|-------------|
| POST | `/login` | `/mobile/login` or `/auth/login` |
| POST | `/register` | `/mobile/register` or `/auth/register` |
| GET | `/products/paginated` | `/mobile/products` |
| GET | `/parent-categories` | `/mobile/categories` |
| GET | `/users/profile` | `/mobile/profile` or `/auth/me` |

**Auth:** Sanctum Bearer token (JWT removed). Mobile responses include `status: "1"|"0"`, `meta.auth: "sanctum"`, and `meta.image_url_prefix`.

**Rate limits:** `SELLOFF_AUTH_RATE_LIMIT` (default 20/min/IP), `SELLOFF_API_RATE_LIMIT` (default 120/min/user or IP).

---

## Reference

Envelope and pagination patterns: `downstreamx/api.downstreamx/docs/API_CONTRACT.md`
