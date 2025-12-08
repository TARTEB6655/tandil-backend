# Fix: "You can only file complaints for your own visits"

## ❌ Error

```
{
  "status": false,
  "message": "You can only file complaints for your own visits"
}
```

## 🔍 Problem

The client you're logged in as **does not own the visit**. 

From your earlier visit creation:
- **Visit ID:** 69
- **Subscription ID:** 125
- **Client ID:** 66 (owner of subscription 125)

You need to login as **client ID 66** to create a complaint for visit 69.

---

## ✅ Solution 1: Login as the Correct Client

### Step 1: Login as Client ID 66

From the visit data, the subscription belongs to:
- **Client ID:** 66
- **Email:** `admin@test.com` (based on earlier response)

**Request:**
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@test.com",
  "password": "password"
}
```

**Or if that's an admin account, register/login as a client:**
```
POST /api/auth/register
{
  "name": "Test Client",
  "email": "client@test.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}
```

### Step 2: Create Subscription (as Client)

```
POST /api/subscriptions
Authorization: Bearer {{client_token}}

{
  "plan": "1_month",
  "start_date": "2025-01-15"
}
```

This will create a subscription owned by your client.

### Step 3: Create Visit (as Client)

```
POST /api/visits
Authorization: Bearer {{client_token}}

{
  "subscription_id": YOUR_SUBSCRIPTION_ID,
  "area_id": 1,
  "scheduled_date": "2025-01-20",
  "status": "pending"
}
```

### Step 4: Create Complaint (as Client)

```
POST /api/auth/complaints
Authorization: Bearer {{client_token}}

{
  "visit_id": YOUR_VISIT_ID,
  "notes": "Technician did not complete all tasks as requested"
}
```

✅ **Should work now!**

---

## ✅ Solution 2: Use Admin to Create Complaint

**Admin can create complaints for any visit:**

```
POST /api/auth/complaints
Authorization: Bearer {{admin_token}}

{
  "visit_id": 69,
  "notes": "Technician did not complete all tasks as requested"
}
```

✅ **Admin bypasses ownership check!**

---

## ✅ Solution 3: Create Visit for Your Client

If you want to use visit 69, you need to:

1. **Get your current client ID:**
   ```
   GET /api/auth/profile
   Authorization: Bearer {{client_token}}
   ```
   - Note the `user.id` value

2. **Create a new subscription for your client:**
   ```
   POST /api/subscriptions
   Authorization: Bearer {{client_token}}
   
   {
     "plan": "1_month",
     "start_date": "2025-01-15"
   }
   ```

3. **Create a visit for your subscription:**
   ```
   POST /api/visits
   Authorization: Bearer {{client_token}}
   
   {
     "subscription_id": YOUR_SUBSCRIPTION_ID,
     "area_id": 1,
     "scheduled_date": "2025-01-20"
   }
   ```

4. **Create complaint for your visit:**
   ```
   POST /api/auth/complaints
   Authorization: Bearer {{client_token}}
   
   {
     "visit_id": YOUR_VISIT_ID,
     "notes": "Complaint description"
   }
   ```

---

## 🔍 Authorization Rule

**From the code:**
```php
// Check that the visit belongs to the user or user is authorized
$visit = Visit::with('subscription')->findOrFail($request->input('visit_id'));

if ($visit->subscription && $visit->subscription->client_id !== $user->id && !$user->hasRole('admin')) {
    return ApiResponse::error('You can only file complaints for your own visits', 403);
}
```

**This means:**
- ✅ **Client** can create complaints for their own visits only
- ✅ **Admin** can create complaints for any visit
- ❌ **Client** cannot create complaints for other clients' visits

---

## 🎯 Quick Fix (Recommended)

**Option A: Use Admin Token**

```
POST /api/auth/complaints
Authorization: Bearer {{admin_token}}

{
  "visit_id": 69,
  "notes": "Technician did not complete all tasks as requested"
}
```

**Option B: Login as Client ID 66**

```
POST /api/auth/login
{
  "email": "admin@test.com",  // Or the email for client ID 66
  "password": "password"
}

Then:
POST /api/auth/complaints
{
  "visit_id": 69,
  "notes": "..."
}
```

---

## 📋 Summary

**The issue:**
- Visit 69 belongs to client ID 66
- You're logged in as a different client
- Only the owner (or admin) can create complaints

**Solutions:**
1. ✅ Login as client ID 66 (owner of visit 69)
2. ✅ Use admin token (can create for any visit)
3. ✅ Create your own visit and complaint

**Try using admin token - that's the fastest!** 🚀

