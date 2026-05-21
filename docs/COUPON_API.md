# Coupon API (Admin + Shop)

Base: `/api`. JSON via `ApiResponse` (`success`, `message`, `data`, optional `meta`).

## Discount types

| `discount_type` | `discount_value` | Notes |
|-------------------|------------------|--------|
| `percentage` | Required (e.g. 10 = 10%) | Optional `max_discount_amount` cap (AED) |
| `fixed_amount` | Required (AED) | `coupon_discount = min(value, after_catalog)` |

## Where it applies (admin UI)

| Field | Values |
|-------|--------|
| `applies_to` | `all` (all store products) \| `categories` \| `services` |
| `catalog_scope` | **Auto** — `products` for `all` / `categories`, `services` for `services` (read-only in API response) |
| `category_ids` | Required when `applies_to=categories` — from `/api/admin/categories` |
| `service_ids` | Required when `applies_to=services` — from `/api/admin/services` |

Shop validation: `cart_category_ids`, `cart_service_ids`, `cart_catalog` (derived from cart when omitted).

## Discount math (server)

```
after_catalog = max(0, subtotal - catalog_discount)
```

- **Percentage:** `coupon_discount = min(after_catalog × value/100, max_discount_amount?)`
- **Fixed:** `coupon_discount = min(value, after_catalog)`
**Tax** (shop settings):

```
taxable = max(0, after_catalog - coupon_discount)
tax = taxable × (tax_percent / 100)
total = taxable + tax + shipping
```

**Order summary fields:** `subtotal`, `discount` (catalog), `coupon_discount`, `coupon_code`, `shipping`, `tax`, `total`, `currency`.

---

## Admin (Bearer admin)

| Method | Path |
|--------|------|
| GET | `/api/admin/coupons?page&per_page&search` |
| GET | `/api/admin/coupons/{id}` |
| POST | `/api/admin/coupons` |
| PUT | `/api/admin/coupons/{id}` |
| DELETE | `/api/admin/coupons/{id}` |

List response includes `meta` (`current_page`, `last_page`, `total`). Messages: `Coupons loaded.`, `Coupon created.`, `Coupon updated.`, `Coupon deleted.`

**Create body (JSON or form-data):** `code`, `title` (required), `description`, `discount_type`, `discount_value`, `min_order_amount` (required), `max_discount_amount`, `starts_at`, `ends_at`, `is_active` (required), `usage_limit`, `usage_limit_per_user`, `applies_to`, `catalog_scope`, `category_ids[]`.

**Update:** `PUT` or `PATCH` with JSON or `multipart/form-data` (same as Postman). **`code` cannot change** (422 if a different code is sent). Always send **`is_active`** as `0` or `1` when toggling off/on (do not omit the field when deactivating). Supports `is_active` or `isActive`.

**Scope IDs (`category_ids` / `service_ids`):** send as JSON array (`[1,2]`), repeated form fields **`service_ids[]`** / **`category_ids[]`**, comma-separated string (`1,2`), or single **`service_id`** / **`category_id`**. Required when `applies_to` is `categories` or `services`.

---

## Shop (Bearer client)

### POST `/api/shop/coupons/validate`

```json
{
  "code": "SAVE10",
  "subtotal": 200,
  "catalog_discount": 20,
  "cart_category_ids": [1, 2],
  "cart_catalog": "products"
}
```

`subtotal` / `catalog_discount` optional when cart has items (derived from server cart).

**200 data:** `coupon_id`, `code`, `discount_type`, `coupon_discount`, `free_shipping`, `message`, optional `order_summary`.

**422:** e.g. `Minimum order is 50 AED after discounts.`

### POST `/api/shop/coupons/apply` (checkout **Apply** button)

Call when the user taps **Apply** on the coupon field. Returns the full **payment summary** for the UI (Subtotal, VAT, Total) and optionally updates an existing Stripe PaymentIntent.

```json
{
  "code": "SAVE10",
  "payment_intent_id": "pi_xxx",
  "use_wallet": false,
  "wallet_amount": 0
}
```

| Field | Required | Notes |
|-------|----------|--------|
| `code` or `coupon_code` | Yes | Coupon to apply |
| `payment_intent_id` | No | If the app already created a PI, send it here to update the card amount |
| `product_id`, `quantity` | No | Buy-now preview (same as order-summary) |
| `use_wallet`, `wallet_amount` | No | Same as order-summary |

**200 `data.order_summary`:** `subtotal`, `discount` (catalog), `coupon_discount`, `coupon_code`, `tax`, **`vat`** (alias of `tax`), `tax_percent`, **`vat_percent`**, `shipping`, `total`, `currency`. Wallet keys when applicable.

**200 `data.payment`** (only when `payment_intent_id` sent): `client_secret`, `payment_intent_id`, `order_total`, `amount_due`, `wallet_amount_applied`.

**Mobile flow:**

1. Load checkout → `GET /api/shop/order-summary` (no coupon).
2. User enters code, taps **Apply** → `POST /api/shop/coupons/apply` with `{ "code": "SAVE10" }` → bind UI to `data.order_summary`.
3. Start card pay → `POST /api/shop/checkout/stripe/payment-intent` with **`coupon_code`** (same code) so PI amount matches summary.
4. If PI was created **before** Apply, call apply with **`payment_intent_id`** to update Stripe amount, then present Payment Sheet with returned `client_secret`.

### GET `/api/shop/order-summary?coupon_code=SAVE10`

Extends summary with `coupon_discount`, `coupon_code`; `discount` = catalog discount only.

### POST `/api/shop/checkout/stripe/payment-intent`

Body includes `coupon_code`. Stripe amount = server `total` after coupon.

### Orders

Paid orders store `coupon_id`, `coupon_code`, `coupon_discount_amount`.

---

## Demo seed

```bash
php artisan db:seed --class=DemoCouponsSeeder
```

| Code | Notes |
|------|--------|
| SAVE10 | 10%, min 50, max 30 off, products only, max 3 per user |
| FLAT20 | AED 20, min 100, all catalog |
| WELCOME15 | 15%, min 80, max 50, 1 per user |
| EXPIRED | Inactive (demo only) |

Postman: folder **Coupons – Admin & Shop (Mobile UI)**.
