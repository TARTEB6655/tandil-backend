# Fix "Notification Not Found" Error

## 🔍 Problem

You're getting:
```json
{
    "status": false,
    "message": "Notification not found. Make sure you are using the correct notification UUID from GET /api/notifications response."
}
```

## ✅ Solution: Follow These Steps

### Step 1: Verify Which User You're Logged In As

**In Postman:**
```
GET /api/auth/profile
Authorization: Bearer YOUR_TOKEN
```

**Check the response:**
```json
{
    "status": true,
    "role": "admin",
    "user": {
        "id": 66,
        "email": "admin@test.com",
        "name": "Test Admin"
    }
}
```

**Note the `email` and `id`** - this is the user you're authenticated as.

---

### Step 2: Get Notifications for That User

**In Postman:**
```
GET /api/notifications
Authorization: Bearer YOUR_TOKEN
```

**Copy the notification ID from the response:**
```json
{
    "status": true,
    "data": {
        "notifications": {
            "data": [
                {
                    "id": "5ad433c2-51e9-44ed-a949-b8842cd3bf07",  ← COPY THIS!
                    "data": {
                        "title": "Welcome",
                        "message": "Welcome to Tandil!"
                    }
                }
            ]
        }
    }
}
```

---

### Step 3: Use the Correct Notification ID

**In Postman:**
```
POST /api/notifications/5ad433c2-51e9-44ed-a949-b8842cd3bf07/mark-read
Authorization: Bearer YOUR_TOKEN
```

**Replace `5ad433c2-51e9-44ed-a949-b8842cd3bf07` with the actual ID from Step 2.**

---

## ⚠️ Common Mistakes

### Mistake 1: Using Wrong User's Token
**Problem:** You're using a token from User A, but trying to mark a notification that belongs to User B.

**Solution:** 
- Make sure the token matches the user who received the notification
- Or get a notification that belongs to the user whose token you're using

### Mistake 2: Using Wrong Notification ID Format
**Problem:** Using `64` (user ID) instead of UUID like `5ad433c2-51e9-44ed-a949-b8842cd3bf07`

**Solution:** Always copy the `id` field from `GET /api/notifications` response

### Mistake 3: Notification Doesn't Exist
**Problem:** The notification was deleted or never existed

**Solution:** 
- Check `GET /api/notifications` to see if the notification still exists
- Create a new notification if needed

---

## 🔧 Debugging Script

Run this to check which notifications belong to which user:

```bash
php get_notification_id.php admin@test.com
```

This will show:
- All notifications for that user
- The correct UUIDs
- The exact URLs to use

---

## 📋 Complete Testing Flow

### 1. Login and Get Token
```
POST /api/auth/login
{
  "email": "admin@test.com",
  "password": "password"
}
```
**Copy the `token` from response**

### 2. Verify User
```
GET /api/auth/profile
Authorization: Bearer TOKEN_FROM_STEP_1
```
**Verify the email matches the user you want**

### 3. Get Notifications
```
GET /api/notifications
Authorization: Bearer TOKEN_FROM_STEP_1
```
**Copy the `id` from the first notification**

### 4. Mark as Read
```
POST /api/notifications/{ID_FROM_STEP_3}/mark-read
Authorization: Bearer TOKEN_FROM_STEP_1
```

---

## 🎯 Quick Test

If you want to test quickly:

1. **Create a test notification:**
   ```bash
   php create_test_notification.php admin@test.com "Test" "Test message"
   ```

2. **Login as that user:**
   ```
   POST /api/auth/login
   {
     "email": "admin@test.com",
     "password": "password"
   }
   ```

3. **Get notifications:**
   ```
   GET /api/notifications
   Authorization: Bearer TOKEN_FROM_STEP_2
   ```

4. **Mark as read:**
   ```
   POST /api/notifications/{ID_FROM_STEP_3}/mark-read
   Authorization: Bearer TOKEN_FROM_STEP_2
   ```

---

## ✅ Success Response

When it works, you'll see:
```json
{
    "status": true,
    "message": "Notification marked as read."
}
```

---

## 🔍 Still Not Working?

Run the debug script:
```bash
php debug_notification.php admin@test.com 5ad433c2-51e9-44ed-a949-b8842cd3bf07
```

This will tell you:
- If the notification exists
- Which user it belongs to
- Why it's not being found

