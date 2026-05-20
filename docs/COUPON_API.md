# Coupon API (Shop + Admin)

Base path: `/api`. JSON responses use `App\Helpers\ApiResponse` shape: `{ "success": true|false, "message": "...", "data": ... }`.

## Discount types

| `discount_type`   | `discount_value` | `max_discount_amount` | Behaviour |
|-------------------|------------------|----------------------|-----------|
| `percentage`      | Required (e.g. 10 = 10%) | Optional cap (AED) on discount amount | Discount = `subtotal × (value/100)`, capped by `max_discount_amount`, never exceeds subtotal. |
| `fixed_amount`    | Required (AED) | Ignored | Discount = `min(value, subtotal)`. |
| `free_shipping`   | Omit or null     | Ignored | Waives shop shipping (customer pays `0` shipping if base shipping &gt; 0). |

All types respect `min_order_amount` on **cart subtotal** (sum of line prices before tax/shipping).

## Admin (Bearer admin token)

| Method | Path | Body / notes |
|--------|------|----------------|
| GET | `/api/admin/coupons` | List all coupons. |
| POST | `/api/admin/coupons` | Create. JSON fields below. |
| GET | `/api/admin/coupons/{id}` | Single coupon. |
| PUT | `/api/admin/coupons/{id}` | Update (same fields as create). |
| DELETE | `/api/admin/coupons/{id}` | Delete. |

### Admin create/update JSON fields

| Field | Rules |
|-------|--------|
| `code` | Required, unique, `A–Z`, `0–9`, `_`, `-` only (stored uppercase). |
| `title` | Optional string. |
| `description` | Optional string. |
| `discount_type` | `percentage` \| `fixed_amount` \| `free_shipping` |
| `discount_value` | Required for `percentage` and `fixed_amount`; omit for `free_shipping`. |
| `min_order_amount` | Optional number (default 0). |
| `max_discount_amount` | Optional; only applies to `percentage`. |
| `starts_at` | Optional date `YYYY-MM-DD`. |
| `ends_at` | Optional date; must be `>= starts_at`. |
| `is_active` | Optional boolean (default true). |
| `usage_limit` | Optional positive integer; max paid orders using this coupon (global). |
| `usage_limit_per_user` | Optional positive integer; max paid orders per customer. |

List/detail responses include `paid_redemptions` (count of paid shop orders with this coupon).

## Shop – validate coupon (Bearer client token)

**POST** `/api/shop/coupons/validate`

Body:

```json
{ "code": "SAVE10" }
```

Optional (same as order-summary / buy-now for subtotal preview):

- `product_id`, `quantity` | `qty` — Buy Now style preview when cart is empty.

Success `data`:

- `coupon` — id, code, title, description, discount_type, values, min/max.
- `merchandise_discount` — AED reduced from subtotal.
- `shipping_discount` — AED waived from shipping (free shipping).
- `order_summary` — same shape as GET order-summary (`subtotal`, `discount`, `shipping_discount`, `shipping`, `tax`, `total`, `currency`, …).

Errors: HTTP **422** with `success: false` and `message` (inactive, expired, min order, usage limits, invalid code).

## Shop – cart & summary (optional `coupon_code`)

Pass **`coupon_code`** as query string or JSON body:

- **GET** `/api/shop/cart?coupon_code=SAVE10`
- **GET** `/api/shop/order-summary?coupon_code=SAVE10` (+ optional `use_wallet`, `wallet_amount`, `product_id`, …)
- **POST** `/api/shop/buy-now/summary` body may include `coupon_code`
- **GET** `/api/shop/checkout/review?coupon_code=SAVE10` (+ optional wallet fields)

Invalid coupon → **422** (same as validate).

## Shop – Stripe PaymentIntent (mobile)

**POST** `/api/shop/checkout/stripe/payment-intent`

Add optional field:

```json
"coupon_code": "SAVE10"
```

Must match a valid coupon for the same cart / buy-now subtotal at intent creation time. Totals and Stripe `amount` include the discount. Paid order stores `coupon_id`, `coupon_code`, `coupon_discount_amount` (merchandise + shipping savings).

## Orders

Paid shop orders include (when a coupon was applied):

- `coupon_id`, `coupon_code`, `coupon_discount_amount`

## Demo coupons (seeder)

```bash
php artisan db:seed --class=DemoCouponsSeeder
```

`DemoCouponsSeeder` is also called from `DatabaseSeeder` so a full `php artisan db:seed` loads the same demo rows.

| Code | Type | Rules |
|------|------|-------|
| SAVE10 | 10% | Min AED 50, max AED 30 off |
| FLAT20 | Fixed AED 20 | Min AED 100 |
| WELCOME15 | 15% | Min AED 80, max AED 50 off |
| FREESHIP | Free shipping | Min AED 75 |
| EXPIRED | — | `is_active` false (validate should fail) |

## Usage limits

Counted on **paid** shop orders (`payment_status = paid`) with `coupon_id` set. Validated again at PaymentIntent creation to reduce race abuse.
