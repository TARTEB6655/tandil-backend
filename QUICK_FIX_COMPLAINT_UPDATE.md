# Quick Fix: Complaint Update "Forbidden" Error

## ❌ Problem

You're using a **client token**, but only **admin, supervisor, or area_manager** can update complaints.

**Error:**
```json
{
  "status": false,
  "message": "Forbidden"
}
```

---

## ✅ Solution: Use Admin Token

### Step 1: Get Admin Token

**Option A: Login as Admin**

```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@test.com",
  "password": "password"
}
```

**Response:**
```json
{
  "status": true,
  "token": "NEW_ADMIN_TOKEN_HERE",  ← Copy this!
  "role": "admin"
}
```

**Option B: Use the Script**

Run:
```bash
php get_admin_token.php
```

This will generate an admin token for you.

---

### Step 2: Update Complaint with Admin Token

**Request:**
```
PUT /api/auth/complaints/19
Authorization: Bearer NEW_ADMIN_TOKEN_HERE  ← Use admin token, not client token!
Content-Type: application/json
Accept: application/json

{
  "status": "in_progress",
  "notes": "Working on resolving the issue"
}
```

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Complaint updated successfully.",
  "data": {
    "id": 19,
    "visit_id": 72,
    "client_id": 64,
    "status": "in_progress",
    "notes": "Working on resolving the issue"
  }
}
```

✅ **Should work now!**

---

## 🔐 Why Client Token Doesn't Work

**From the code:**
```php
// Only admin, area_manager, or supervisor can update complaint status
if (! $user->hasAnyRole(['admin', 'area_manager', 'supervisor'])) {
    return response()->json(['status' => false, 'message' => 'Forbidden'], 403);
}
```

**This means:**
- ✅ **Admin** - Can update any complaint
- ✅ **Supervisor** - Can update complaints
- ✅ **Area Manager** - Can update complaints
- ❌ **Client** - Cannot update complaints (can only create)
- ❌ **Technician** - Cannot update complaints

---

## 📋 Quick Steps

1. **Login as admin:**
   ```
   POST /api/auth/login
   {
     "email": "admin@test.com",
     "password": "password"
   }
   ```
   → Copy the `token`

2. **Update token in Postman:**
   - Set `{{token}}` = (admin token)

3. **Update complaint:**
   ```
   PUT /api/auth/complaints/19
   Authorization: Bearer {{token}}
   Content-Type: application/json
   
   {
     "status": "in_progress",
     "notes": "Working on resolving the issue"
   }
   ```

4. **Should work!**

---

## 🎯 Alternative: Use Supervisor Token

If you want to use supervisor token:

```
POST /api/auth/login
{
  "email": "supervisor@test.com",
  "password": "password"
}
```

Then use that token to update the complaint.

---

## ✅ Summary

**The issue:**
- You're using a **client token**
- Clients **cannot update** complaints
- Only **admin, supervisor, or area_manager** can update

**The fix:**
- Login as **admin** (or supervisor)
- Use **admin token** instead of client token
- Update complaint again

**Try it now with admin token!** 🚀

