# API Endpoints Verification

This document verifies that all endpoints match the React Native frontend expectations.

## ✅ Authentication Endpoints

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `/api/auth/register` | POST | ✅ Working | Returns: `{status, message, token, role, user, data}` |
| `/api/auth/login` | POST | ✅ Working | Returns: `{status, message, token, role, user, data}` |
| `/api/auth/logout` | POST | ✅ Working | Requires auth |
| `/api/auth/user` | GET | ✅ Working | Alias for `/api/auth/profile`, requires auth |
| `/api/auth/profile` | GET | ✅ Working | Requires auth |
| `/api/auth/forgot-password` | POST | ⚠️ Placeholder | Returns 501 (Not Implemented) |
| `/api/auth/verify-otp` | POST | ⚠️ Placeholder | Returns 501 (Not Implemented) |
| `/api/auth/reset-password` | POST | ⚠️ Placeholder | Returns 501 (Not Implemented) |

## ✅ Product Endpoints

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `/api/products` | GET | ✅ Working | Supports `search`, `q`, `category_id`, `per_page`, `sort_by`, `sort_dir` |
| `/api/products/{id}` | GET | ✅ Working | Returns single product |
| `/api/products/search` | GET | ✅ Working | Uses same handler as index, supports `q` or `search` param |
| `/api/products/categories` | GET | ✅ Working | Returns all categories |
| `/api/products/category/{id}` | GET | ✅ Working | Returns products by category |

**Response Format:**
```json
{
  "status": true,
  "message": "Products retrieved successfully.",
  "data": { ... }
}
```

## ✅ Service Endpoints

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `/api/services` | GET | ✅ Working | Uses Categories model (can be updated to Service model) |
| `/api/services/{id}` | GET | ✅ Working | Returns single service |
| `/api/services/categories` | GET | ✅ Working | Returns service categories |
| `/api/services/category/{id}` | GET | ✅ Working | Returns services by category |

**Response Format:**
```json
{
  "status": true,
  "message": "Services retrieved successfully.",
  "data": { ... }
}
```

## ✅ Order Endpoints

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `/api/orders` | GET | ✅ Working | Returns user's orders, requires auth |
| `/api/orders/{id}` | GET | ✅ Working | Returns single order, requires auth |
| `/api/orders` | POST | ✅ Working | Creates order (checkout), requires auth |
| `/api/orders/{id}` | PUT | ✅ Working | Updates order status, requires auth |
| `/api/orders/{id}/cancel` | POST | ✅ Working | Cancels order, requires auth |
| `/api/orders/{id}/track` | GET | ✅ Working | Returns order tracking info, requires auth |
| `/api/orders/{id}/rate` | POST | ✅ Working | Rates order, requires auth |

**Response Format:**
```json
{
  "status": true,
  "message": "Orders retrieved successfully.",
  "data": { ... }
}
```

## ✅ User Profile Endpoints

| Endpoint | Method | Status | Notes |
|----------|--------|--------|-------|
| `/api/user/profile` | GET | ✅ Working | Returns user profile, requires auth |
| `/api/user/profile` | PUT | ✅ Working | Updates user profile, requires auth |
| `/api/user/addresses` | GET | ⚠️ Placeholder | Returns empty array (not implemented) |
| `/api/user/addresses` | POST | ⚠️ Placeholder | Returns success (not implemented) |
| `/api/user/addresses/{id}` | PUT | ⚠️ Placeholder | Returns success (not implemented) |
| `/api/user/addresses/{id}` | DELETE | ⚠️ Placeholder | Returns success (not implemented) |
| `/api/user/loyalty` | GET | ⚠️ Placeholder | Returns mock data (not implemented) |
| `/api/user/notifications` | GET | ✅ Working | Returns user notifications, requires auth |
| `/api/user/notifications/{id}/read` | POST | ✅ Working | Marks notification as read, requires auth |
| `/api/user/notifications/read-all` | POST | ✅ Working | Marks all notifications as read, requires auth |

**Response Format:**
```json
{
  "status": true,
  "message": "Profile retrieved successfully.",
  "data": { ... }
}
```

## 📋 Response Format Standard

All endpoints use the `ApiResponse` helper which returns:

**Success Response:**
```json
{
  "status": true,
  "message": "Success message",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "status": false,
  "message": "Error message"
}
```

## 🔐 Authentication

All protected endpoints require:
- Header: `Authorization: Bearer {token}`
- Token obtained from `/api/auth/login` or `/api/auth/register`

## ⚠️ Placeholder Endpoints

The following endpoints are placeholders and return appropriate responses:

1. **Password Reset** (`/api/auth/forgot-password`, `/api/auth/verify-otp`, `/api/auth/reset-password`)
   - Returns: `{status: false, message: "Feature not implemented yet"}` with status 501

2. **Addresses** (`/api/user/addresses/*`)
   - Returns: `{status: true, message: "Success", data: []}` 
   - Ready for implementation when addresses table is created

3. **Loyalty** (`/api/user/loyalty`)
   - Returns: `{status: true, message: "Success", data: {points: 0, level: "Bronze"}}`
   - Ready for implementation when loyalty system is created

## ✅ All Endpoints Verified

All endpoints that match the React Native frontend expectations are:
- ✅ Properly registered in `routes/api.php`
- ✅ Using consistent `ApiResponse` helper
- ✅ Returning correct response format
- ✅ Protected with `auth:sanctum` middleware where needed
- ✅ Following RESTful conventions

## 🧪 Testing

To test endpoints, use:

```bash
# Health check
curl http://localhost:8000/api/health

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "user@example.com", "password": "password123"}'

# Get products
curl http://localhost:8000/api/products

# Get user profile (with token)
curl http://localhost:8000/api/user/profile \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

## 📝 Notes

1. **Services**: Currently uses Categories model. If you have a separate Service model, update `ServiceController` accordingly.

2. **Search Parameter**: Products search endpoint accepts both `search` and `q` parameters for flexibility.

3. **Order Update**: The `PUT /api/orders/{id}` endpoint allows updating `order_status` and `payment_status`.

4. **Notifications**: Uses Laravel's built-in notification system via the `Notifiable` trait on User model.

5. **Response Consistency**: All endpoints now use `ApiResponse` helper for consistent response format.

