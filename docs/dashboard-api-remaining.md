# Dashboard API Audit and Remaining Work

Last updated: 2026-02-24

This report is based on `routes/api.php` and current controller behavior.

## 1) Client Dashboard APIs

### Implemented (available now)

- `GET /api/client/settings/dashboard`
- `GET /api/client/settings/sections`
- `GET /api/client/memberships`
- `GET /api/user/profile`
- `PUT|POST|PATCH /api/user/profile`
- `GET /api/user/addresses`
- `POST /api/user/addresses`
- `PUT|PATCH|POST /api/user/addresses/{id}`
- `DELETE /api/user/addresses/{id}`
- `GET /api/user/notifications`
- `POST /api/user/notifications/{id}/read`
- `POST /api/user/notifications/read-all`
- `POST /api/user/notifications/clear-all`
- `GET /api/support/help-center`
- `GET /api/support/faqs`
- `POST /api/support/tickets`
- `GET /api/maintenance-photos`
- `GET /api/maintenance-photos/visit/{visitId}`
- Shop + checkout flow for client:
  - `POST /api/shop/cart/add`
  - `GET /api/shop/cart`
  - `PUT|PATCH /api/shop/cart/{id}`
  - `DELETE /api/shop/cart/{id}`
  - `GET /api/shop/order-summary`
  - `GET /api/shop/checkout/payment-methods`
  - `GET /api/shop/checkout/review`
  - `GET /api/shop/orders`
  - `GET /api/shop/orders/{id}`
  - `POST /api/shop/orders/{id}/mark-paid`

### Remaining / partial

1. `GET /api/user/payment-methods` is placeholder only  
   - Current behavior: returns empty array (`[]`) with comment "No saved payment method model yet".
   - Status: **Not functionally complete**.

2. Saved payment method management APIs are missing  
   - Missing endpoints (recommended):
     - `POST /api/user/payment-methods`
     - `PUT /api/user/payment-methods/{id}`
     - `DELETE /api/user/payment-methods/{id}`
     - Optional: `POST /api/user/payment-methods/{id}/set-default`
   - Status: **Not created yet**.

3. Notification read/clear persistence is partial for tips-based notifications  
   - Current list endpoint is tip-based (`GET /api/user/notifications`), but no dedicated per-user "dismissed tips" storage.
   - Status: **Works for UI actions, but persistence model can be improved**.


## 2) Technician Dashboard APIs

### Implemented (available now)

Technician module exists under two route groups:

#### A) Task/visit actions (`/api/tech/*`)
- `GET /api/tech/visits`
- `POST /api/tech/visits/{id}/accept`
- `POST /api/tech/visits/{id}/start`
- `POST /api/tech/visits/{id}/complete`
- `PUT|POST /api/tech/visits/{id}/photos`

#### B) Full technician dashboard (`/api/technician/*`)
- Dashboard/profile:
  - `GET /api/technician/dashboard`
  - `GET /api/technician/profile`
  - `PUT|POST /api/technician/profile`
- Tasks/jobs:
  - `GET /api/technician/tasks`
  - `GET /api/technician/tasks/{id}`
  - `PUT /api/technician/tasks/{id}/status`
  - `POST /api/technician/tasks/{id}/accept`
  - `POST /api/technician/tasks/{id}/reject`
  - `GET /api/technician/jobs`
  - `GET /api/technician/jobs/{id}`
- Earnings/payout:
  - `GET /api/technician/payout-summary`
  - `GET /api/technician/payouts`
  - `GET|PUT /api/technician/settings/payout`
  - `GET|POST /api/technician/bank-accounts`
  - `PUT|DELETE /api/technician/bank-accounts/{id}`
- Availability/breaks/vacations/schedule:
  - `GET|PUT /api/technician/availability`
  - `GET|POST /api/technician/breaks`
  - `PUT|DELETE /api/technician/breaks/{id}`
  - `GET|POST /api/technician/vacations`
  - `PUT|DELETE /api/technician/vacations/{id}`
  - `GET /api/technician/schedule`

### Remaining / partial

- No major technician dashboard endpoint is marked as placeholder in current route/controller audit.


## 3) Admin Dashboard APIs

### Implemented (available now)

- Admin dashboard stats:
  - `GET /api/admin/dashboard/statistics`
  - `GET /api/admin/dashboard/recent-activities`
  - `GET /api/admin/dashboard/quick-overview`
  - `GET /api/admin/dashboard/profile`
  - `GET /api/admin/dashboard/top-selling-products`
- Admin reports:
  - `GET /api/admin/reports/statistics`
  - `POST /api/admin/reports/generate`
  - `POST /api/admin/reports/schedule`
  - `GET /api/admin/reports`
  - `GET /api/admin/reports/{id}`
  - `GET /api/admin/reports/{id}/download`
  - `DELETE /api/admin/reports/{id}/cancel`
  - `POST /api/admin/reports/{id}/share`
  - `DELETE /api/admin/reports/{id}`
- Admin users:
  - `GET /api/admin/users/statistics`
  - `GET|POST /api/admin/users`
  - `GET|PUT|DELETE /api/admin/users/{id}`
- Admin settings:
  - `GET /api/admin/settings`
  - `GET|PUT /api/admin/settings/system`
  - `GET|PUT /api/admin/settings/theme`
  - `GET|PUT /api/admin/settings/language`
  - `GET|PUT /api/admin/settings/payment`
  - `GET|PUT /api/admin/settings/shop`
  - `GET /api/admin/settings/legal`
  - `POST /api/admin/settings/export-data`
  - `GET /api/admin/settings/debug-logs`
- Admin commerce/content:
  - Categories, services, products CRUD (`/api/admin/categories`, `/api/admin/services`, `/api/admin/products`)
  - Banners CRUD (`/api/admin/banners/*`)
  - Packages CRUD (`/api/admin/packages/*`)
  - Exclusive offers CRUD (`/api/admin/exclusive-offers/*`)
  - Orders export/send:
    - `GET /api/admin/orders/export`
    - `POST /api/admin/orders/send-to-supplier`

### Remaining / partial

1. `POST /api/admin/settings/export-data` is placeholder behavior  
   - Comment indicates placeholder response and suggests queueing real export job later.
   - Status: **Partial**.


## 4) Payment APIs (Detailed Status)

### Implemented

- `POST /api/shop/create-payment-session` (Checkout.com session creation)
- `POST /api/shop/webhooks/checkout-com` (webhook handling)
- `GET /api/shop/checkout/payment-methods` (checkout choices for UI)
- `GET /api/shop/payments`
- `GET /api/shop/payments/{id}`
- `GET /api/shop/transactions` (alias)
- `GET /api/shop/transactions/{id}` (alias)
- `GET|PUT /api/admin/settings/payment` (admin payment configuration)

### Not done / remaining

1. Client saved cards/wallets API is not implemented
   - Existing endpoint:
     - `GET /api/user/payment-methods` -> returns empty placeholder
   - Missing endpoints for actual management:
     - `POST /api/user/payment-methods`
     - `PUT /api/user/payment-methods/{id}`
     - `DELETE /api/user/payment-methods/{id}`
     - Optional default method endpoint

2. Legacy endpoint intentionally disabled
   - `POST /api/shop/checkout` returns error directing to `/api/shop/create-payment-session`.
   - Status: **Deprecated by design** (not a bug).


## 5) Remaining Count (Current Snapshot)

- **High-priority remaining (functional gaps): 2**
  1. Real saved payment methods (client) not implemented.
  2. Payment-method CRUD endpoints for client not present.

- **Medium-priority partials: 2**
  1. Admin export-data endpoint still placeholder style.
  2. Tips-based notifications clear/read persistence can be improved (if strict per-user hide/dismiss is required).


## 6) Suggested Next APIs To Build First

1. `POST /api/user/payment-methods` (store tokenized card/wallet reference)
2. `GET /api/user/payment-methods` (replace placeholder with real data)
3. `PUT /api/user/payment-methods/{id}` (rename/default toggle)
4. `DELETE /api/user/payment-methods/{id}`
5. Optional: `POST /api/user/payment-methods/{id}/set-default`

