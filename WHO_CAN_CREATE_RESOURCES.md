# Who Can Create Resources?

## 📋 Summary

| Resource | Who Can Create | Route | Middleware |
|----------|---------------|-------|------------|
| **Tips** | ✅ **Admin Only** | `/api/admin/tips` (if exists) or Admin Panel | `role:admin` |
| **Notifications** | ✅ **Admin Only** | Admin Panel | `role:admin` |
| **Categories** | ⚠️ **Anyone (No Auth)** | `POST /api/categories` | None |
| **Areas** | ✅ **Area Manager** | `POST /api/areas` | `auth:sanctum, role:area_manager` |
| **Payments (PayPal Orders)** | ✅ **Any Authenticated User** | `POST /api/auth/payments/paypal/create` | `auth:sanctum` |

---

## 1. Tips

### ✅ **Admin Only**

**Controller:** `App\Http\Controllers\Admin\TipController`

**Authorization:**
```php
$this->middleware('role:admin');
```

**Who Can Create:**
- ✅ **Admin** only
- ❌ All other roles cannot create tips

**How to Create:**
- Via Admin Panel (web interface)
- API route may not exist (check routes)

**Note:** The API `TipsController` only has `index` and `show` methods (read-only). Creation is done through the Admin Panel.

---

## 2. Notifications

### ✅ **Admin Only**

**Controller:** `App\Http\Controllers\Admin\NotificationController`

**Authorization:**
- Admin panel routes use `role:admin` middleware
- API `NotificationController` only has read methods (`index`, `markAsRead`)

**Who Can Create:**
- ✅ **Admin** only (via admin panel)
- ❌ All other roles cannot create notifications

**How to Create:**
- Via Admin Panel: `admin/notifications/create`
- Method: `send()` - sends notifications to users/roles

**Note:** The API `NotificationController` is read-only. Creation is done through the Admin Panel.

---

## 3. Categories

### ⚠️ **Anyone (No Authentication Required!)**

**Controller:** `App\Http\Controllers\CategoryController`

**Authorization:**
```php
// NO middleware in routes/api.php
Route::apiResource('categories', CategoryController::class);
```

**Who Can Create:**
- ⚠️ **Anyone** (no authentication required)
- This is a **SECURITY ISSUE** - categories should be protected!

**How to Create:**
```
POST /api/categories
Content-Type: application/json

{
  "name": "New Category",
  "slug": "new-category",
  "description": "Category description"
}
```

**⚠️ Recommendation:** Add authentication middleware to category routes:
```php
Route::middleware('auth:sanctum')->apiResource('categories', CategoryController::class);
```

Or restrict to admin:
```php
Route::middleware(['auth:sanctum', 'role:admin'])->apiResource('categories', CategoryController::class);
```

---

## 4. Areas

### ✅ **Area Manager Only**

**Controller:** `App\Http\Controllers\AreaController`

**Authorization:**
```php
$this->middleware(['auth:sanctum', 'role:area_manager']);
```

**Who Can Create:**
- ✅ **Area Manager** only
- ❌ All other roles cannot create areas

**How to Create:**
```
POST /api/areas
Authorization: Bearer {{area_manager_token}}
Content-Type: application/json

{
  "name": "Dubai",
  "description": "Dubai area"
}
```

**Route:**
```php
Route::middleware(['auth:sanctum', 'role:area_manager'])->prefix('areas')->group(function () {
    // Area routes
});
```

---

## 5. Payments (PayPal Orders)

### ✅ **Any Authenticated User**

**Controller:** `App\Http\Controllers\PaymentController`

**Authorization:**
```php
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('payments/paypal/create', [PaymentController::class, 'createPaypalOrder']);
});
```

**Who Can Create:**
- ✅ **Any authenticated user** (client, admin, technician, etc.)
- Users can create PayPal orders for their own subscriptions/orders

**How to Create:**
```
POST /api/auth/payments/paypal/create
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "type": "subscription",  // or "order"
  "id": 123,               // subscription_id or order_id
  "currency": "USD",
  "return_url": "https://example.com/success",
  "cancel_url": "https://example.com/cancel"
}
```

**Note:** Users can only create PayPal orders for subscriptions/orders they own (or admin can create for any).

---

## 📊 Quick Reference Table

| Resource | Admin | Client | Technician | Supervisor | Area Manager | HR | Public |
|----------|-------|--------|------------|------------|--------------|----|----|
| **Tips** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Notifications** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Categories** | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ⚠️ |
| **Areas** | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| **Payments** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |

**Legend:**
- ✅ = Can create
- ❌ = Cannot create
- ⚠️ = Can create (but should be restricted)

---

## 🔒 Security Recommendations

### 1. Categories Should Be Protected
Currently, anyone can create categories without authentication. This should be fixed:

**Recommended Fix:**
```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->apiResource('categories', CategoryController::class);
```

### 2. Tips API Endpoint
Consider adding API endpoint for creating tips (currently only admin panel):

```php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('/tips', [TipController::class, 'store']);
});
```

### 3. Notifications API Endpoint
Consider adding API endpoint for creating notifications (currently only admin panel):

```php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::post('/notifications/send', [NotificationController::class, 'send']);
});
```

---

## 📝 Summary

1. **Tips**: Admin only (via admin panel)
2. **Notifications**: Admin only (via admin panel)
3. **Categories**: ⚠️ Anyone (no auth) - **SECURITY ISSUE**
4. **Areas**: Area Manager only
5. **Payments**: Any authenticated user

**Action Required:** Fix category routes to require authentication (preferably admin only).

