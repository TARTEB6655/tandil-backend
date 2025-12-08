# Who Can Create Visits?

## 📋 Answer

**Both Client and Admin can create visits**, but with different permissions:

### ✅ **Admin**
- Can create visits for **any subscription** (any client)
- Full access to all subscriptions

### ✅ **Client**
- Can create visits for **their own subscriptions only**
- Cannot create visits for other clients' subscriptions

---

## 🔐 Authorization Rules

### Admin
```php
if ($user->hasRole('admin')) {
    // ✅ Can create visit for any subscription
}
```

### Client
```php
if ($subscription->client_id === $user->id) {
    // ✅ Can create visit for their own subscription
}
```

**If client tries to create visit for someone else's subscription:**
- ❌ Returns: `403 Forbidden`

---

## 📝 How to Create a Visit

### Method: `POST /api/visits`

**Required Headers:**
- `Authorization: Bearer {{token}}` (admin or client token)
- `Content-Type: application/json`
- `Accept: application/json`

**Required Body:**
```json
{
  "subscription_id": 123,          ← Required: Must exist
  "technician_id": 68,              ← Optional: Can be null
  "supervisor_id": 32,              ← Optional: Can be null
  "area_id": 1,                     ← Optional: Can be null
  "scheduled_date": "2025-01-20",   ← Required: Must be valid date
  "status": "pending"                ← Optional: Defaults to "pending"
}
```

---

## 🎯 Examples

### Example 1: Client Creates Visit for Own Subscription

**Request:**
```
POST /api/visits
Authorization: Bearer {{client_token}}
Content-Type: application/json

{
  "subscription_id": 123,           ← Must be client's own subscription
  "technician_id": 68,
  "scheduled_date": "2025-01-20",
  "status": "pending"
}
```

**Expected Response (201):**
```json
{
  "status": true,
  "message": "Visit created successfully",
  "data": {
    "id": 62,
    "subscription_id": 123,
    "technician_id": 68,
    "scheduled_date": "2025-01-20",
    "status": "pending"
  }
}
```

**If client tries to use someone else's subscription:**
```json
{
  "status": false,
  "message": "Forbidden"
}
```
**Status Code:** `403`

---

### Example 2: Admin Creates Visit for Any Subscription

**Request:**
```
POST /api/visits
Authorization: Bearer {{admin_token}}
Content-Type: application/json

{
  "subscription_id": 999,           ← Can be any subscription (any client)
  "technician_id": 68,
  "supervisor_id": 32,
  "area_id": 1,
  "scheduled_date": "2025-01-20",
  "status": "pending"
}
```

**Expected Response (201):**
```json
{
  "status": true,
  "message": "Visit created successfully",
  "data": {
    "id": 63,
    "subscription_id": 999,
    "technician_id": 68,
    "supervisor_id": 32,
    "area_id": 1,
    "scheduled_date": "2025-01-20",
    "status": "pending"
  }
}
```

✅ **Admin can create for any subscription!**

---

## ❌ Who Cannot Create Visits

### Technician
- ❌ Cannot create visits
- Can only update visits assigned to them

### Supervisor
- ❌ Cannot create visits
- Can only update visits in areas they supervise

### Area Manager
- ❌ Cannot create visits
- Can only manage areas

---

## 📋 Quick Reference Table

| Role | Can Create Visit | For Which Subscriptions |
|------|-----------------|------------------------|
| **Admin** | ✅ Yes | Any subscription |
| **Client** | ✅ Yes | Own subscriptions only |
| **Technician** | ❌ No | - |
| **Supervisor** | ❌ No | - |
| **Area Manager** | ❌ No | - |

---

## 🔍 Code Reference

**From `VisitController@store`:**

```php
// Authorization: Only admin or client (for their own subscription) can create visits
$subscription = \App\Models\Subscription::find($request->subscription_id);

if (!$subscription) {
    return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
}

// Check if user is admin or owns the subscription
if (!$user->hasRole('admin') && $subscription->client_id !== $user->id) {
    return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
}
```

---

## 💡 Common Scenarios

### Scenario 1: Client Creates Visit

1. **Client has subscription** (ID: 123)
2. **Client creates visit:**
   ```json
   POST /api/visits
   {
     "subscription_id": 123,  ← Own subscription
     "scheduled_date": "2025-01-20"
   }
   ```
3. ✅ **Success** - Visit created

### Scenario 2: Client Tries to Create Visit for Other Client

1. **Client has subscription** (ID: 123)
2. **Client tries to create visit:**
   ```json
   POST /api/visits
   {
     "subscription_id": 999,  ← Other client's subscription
     "scheduled_date": "2025-01-20"
   }
   ```
3. ❌ **Forbidden** - Returns 403 error

### Scenario 3: Admin Creates Visit for Any Client

1. **Admin creates visit:**
   ```json
   POST /api/visits
   {
     "subscription_id": 999,  ← Any subscription
     "scheduled_date": "2025-01-20"
   }
   ```
2. ✅ **Success** - Visit created (admin has full access)

---

## ✅ Summary

**Answer: Both Client and Admin can create visits**

- **Admin:** Can create for any subscription
- **Client:** Can create for their own subscriptions only
- **Others:** Cannot create visits

**Endpoint:** `POST /api/visits`

**Authorization:** 
- Admin token → Full access
- Client token → Own subscriptions only

