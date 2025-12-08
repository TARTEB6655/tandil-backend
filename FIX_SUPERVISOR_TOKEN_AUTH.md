# Fix Supervisor Token Authentication

## Problem
Getting "Unauthenticated" error when using supervisor token to update complaints.

## Solution

### Step 1: Login as Supervisor to Get Fresh Token

**Request:**
```
POST /api/auth/login
Content-Type: application/json
Accept: application/json

{
  "email": "supervisor1@example.com",
  "password": "password"
}
```

**Expected Response:**
```json
{
  "status": true,
  "message": "Login successful.",
  "token": "21|IHWjDe6mLmZ8Tlvl6JKxC36Q1g135NPTCgaYc0FSf9c75d27",
  "role": "supervisor",
  "user": { ... }
}
```

**⚠️ IMPORTANT:** Copy the ENTIRE token including the `ID|` prefix:
- ✅ Correct: `21|IHWjDe6mLmZ8Tlvl6JKxC36Q1g135NPTCgaYc0FSf9c75d27`
- ❌ Wrong: `IHWjDe6mLmZ8Tlvl6JKxC36Q1g135NPTCgaYc0FSf9c75d27` (missing ID)

---

### Step 2: Use Token in Postman

#### Option A: Using Authorization Tab (Recommended)

1. Open Postman
2. Go to **Authorization** tab
3. Select **Type: Bearer Token**
4. Paste the **ENTIRE** token (including `ID|` prefix) in the **Token** field
5. Make sure it looks like: `21|IHWjDe6mLmZ8Tlvl6JKxC36Q1g135NPTCgaYc0FSf9c75d27`

#### Option B: Using Headers Tab

1. Go to **Headers** tab
2. Add:
   - **Key:** `Authorization`
   - **Value:** `Bearer 21|IHWjDe6mLmZ8Tlvl6JKxC36Q1g135NPTCgaYc0FSf9c75d27`
3. Also add:
   - **Key:** `Accept`
   - **Value:** `application/json`

---

### Step 3: Update Complaint

**Request:**
```
PUT /api/auth/complaints/19
Authorization: Bearer 21|IHWjDe6mLmZ8Tlvl6JKxC36Q1g135NPTCgaYc0FSf9c75d27
Content-Type: application/json
Accept: application/json

{
  "status": "in_progress",
  "notes": "Working on resolving the issue"
}
```

**Expected Response:**
```json
{
  "status": true,
  "message": "Complaint updated successfully.",
  "data": {
    "id": 19,
    "status": "in_progress",
    "notes": "Working on resolving the issue",
    ...
  }
}
```

---

## Common Issues & Fixes

### Issue 1: "Unauthenticated" Error

**Possible Causes:**
1. Token format is wrong (missing `ID|` prefix)
2. Token was deleted (user logged out)
3. User status is not 'active'
4. Authorization header is missing or incorrect

**Fix:**
1. Login again to get a fresh token
2. Make sure to copy the ENTIRE token (including `ID|`)
3. Verify user status is 'active'

### Issue 2: Token Not Working

**Verify Token:**
```bash
php verify_token.php "YOUR_TOKEN_HERE"
```

This will show:
- ✅ If token exists in database
- ✅ User details
- ✅ User status
- ⚠️ Any issues

### Issue 3: User Status Not Active

**Check User Status:**
```bash
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'supervisor1@example.com')->first();
$user->status; // Should be 'active'
```

If not active, update it:
```php
$user->status = 'active';
$user->save();
```

---

## Quick Test Script

Run this to get a fresh supervisor token:

```bash
php test_supervisor_token.php
```

This will:
1. Find a supervisor
2. Create a new token
3. Verify the token
4. Show you exactly how to use it

---

## Summary

1. ✅ Login as supervisor: `POST /api/auth/login`
2. ✅ Copy ENTIRE token (including `ID|` prefix)
3. ✅ Use in Postman: Authorization → Bearer Token
4. ✅ Add `Accept: application/json` header
5. ✅ Make request: `PUT /api/auth/complaints/19`

The token format is: `TOKEN_ID|TOKEN_HASH` - both parts are required!

