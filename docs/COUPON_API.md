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

### GET `/api/shop/coupons/browse`

Single endpoint for store-wide, category, or service product screens. Send **exactly one** query param:

| Query | Effect |
|-------|--------|
| `all=1` | Only coupons with admin `applies_to=all` (all products / store-wide) |
| `category_id` | Coupons with `applies_to=all` **or** `applies_to=categories` for that category |
| `service_id` | Coupons with `applies_to=all` **or** `applies_to=services` for that service |

Examples: `GET .../browse?all=1`, `GET .../browse?category_id=5`, `GET .../browse?service_id=12`.

**422** if none of the above, or if more than one is sent (e.g. `all=1` with `service_id`).

**200 `data`:** array of offer cards (`code`, `discount_label`, `scope_label`, `scope_summary`, `categories`, `services`, …). **`meta.scope`:** `all` | `category` | `service`.

### POST `/api/shop/coupons/checkout-offers`

**“Choose a promo code”** modal at checkout. Same cart context as validate (`subtotal`, `catalog_discount`, `cart_category_ids`, `cart_service_ids`, or server cart).

**200 `data`:**

| Key | Description |
|-----|-------------|
| `available_for_order` | Coupons the user can apply now |
| `not_eligible_for_cart` | Active coupons that fail min order, scope, or usage limits |
| `available_count` / `not_eligible_count` | Counts for UI (“View N available offers”) |

Each offer includes `discount_label` (e.g. `10% OFF`), `applies_to_label` (`All products`), `scope_summary` (`Categories: +1 more`), `eligible`, `ineligible_reason`, and `coupon_discount_preview` when eligible.

Example ineligible scope message: `This offer applies to specific categories. Your cart does not include eligible category items.`

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

**422:** e.g. `Minimum order is 50 AED.` (checked against cart **subtotal**, not after compare-at catalog savings)

### POST `/api/shop/coupons/apply` (checkout **Apply** button)

Send **only the coupon code** — subtotal, tax, and discount are calculated on the server from the user’s cart (or buy-now `product_id` + `quantity`).

```json
{
  "code": "SAVE10"
}
```

Optional (only when needed):

| Field | When to send |
|-------|----------------|
| `payment_intent_id` | Optional — if omitted and user has a pending checkout PI (last 24h), server **auto-updates** that Stripe amount |
| `product_id`, `quantity` | Buy-now checkout (no cart rows) |
| `use_wallet`, `wallet_amount` | Wallet preview on summary |
| `subtotal`, `catalog_discount` | Rare override; omit in normal app flow |

**Discount math:** `percentage` → `coupon_discount = min(subtotal × %, max_discount_amount?)`; `fixed_amount` → `coupon_discount = min(AED value, subtotal after catalog discount)`. Tax is on the amount **after** catalog + coupon discounts.

**200 `data`:** `code`, `discount_type` (`percentage` \| `fixed_amount`), `discount_value`, `coupon_discount`, `free_shipping`, **`order_summary`** (subtotal, `coupon_discount`, `coupon_code`, `tax` / `vat`, `total`, …).

**200 `data.payment`** (when a pending Stripe PI exists or `payment_intent_id` sent): `client_secret`, `payment_intent_id`, `order_total`, `amount_due`, **`amount_minor`** (e.g. `12025` = AED 120.25), `reinitialize_payment_sheet: true`. **Close the old Payment Sheet** and open a new one with this `client_secret` — otherwise Stripe may still show the pre-coupon amount (e.g. 141.25).

**Mobile flow:**

1. Load checkout → `GET /api/shop/order-summary` (no coupon).
2. User taps **Apply** → `POST /api/shop/coupons/apply` with `{ "code": "FLAT20" }` → bind UI to `data.order_summary`.
3. **Stripe:** Either create PI **after** apply with `coupon_code` on `POST …/payment-intent`, **or** if PI was already created, apply returns `data.payment` — use **`data.payment.client_secret`** for Payment Sheet (amount matches discounted total).
4. After apply, if `data.payment` is present: `initPaymentSheet({ paymentIntentClientSecret: data.payment.client_secret })` again. Confirm button shows `amount_due` (not the old PI amount).
5. After **Apply**, the coupon is stored in the database (`shop_applied_checkout_coupons`) for this cart fingerprint (plus cache). `order-summary` and `payment-intent` both use it even if the app omits `coupon_code`. Send `clear_coupon: true` to remove. If a PaymentIntent was created **before** Apply, the next `payment-intent` call updates Stripe to the discounted `amount_due` and returns `reinitialize_payment_sheet: true` when the amount changed.

### GET `/api/shop/order-summary?coupon_code=SAVE10`

Extends summary with `coupon_discount`, `coupon_code`; `discount` = catalog discount only.

### POST `/api/shop/checkout/stripe/payment-intent`

Uses the **same** server logic as `GET /api/shop/order-summary` (`checkoutTotalsForRequest` + wallet preview). Send the same query/body fields: `coupon_code`, `product_id`, `quantity`, `use_wallet`, `wallet_amount`.

Response includes `order_total`, `amount_due`, and **`order_summary`** (same shape as order-summary). Stripe `amount` = `amount_due` in minor units (fils).

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
