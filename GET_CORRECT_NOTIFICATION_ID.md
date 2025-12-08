# How to Get the Correct Notification ID

## ❌ The Problem

You're using `64` as the notification ID, but:
- `64` is a **user ID**, not a notification ID
- Notification IDs are **UUIDs** (like `c7a9dec4-a896-49ba-9921-563a420be8f2`)
- You must get the notification ID from the `GET /api/notifications` response

---

## ✅ Solution: Get the Correct Notification ID

### Step 1: Get Your Notifications

**In Postman:**

```
GET http://127.0.0.1:8000/api/notifications
Authorization: Bearer 17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162
Accept: application/json
```

**Expected Response:**
```json
{
    "status": true,
    "message": "Notifications retrieved successfully.",
    "data": {
        "notifications": {
            "data": [
                {
                    "id": "c7a9dec4-a896-49ba-9921-563a420be8f2",  ← THIS IS THE NOTIFICATION ID!
                    "type": "App\\Notifications\\AdminNotification",
                    "notifiable_type": "App\\Models\\User",
                    "notifiable_id": 66,
                    "data": {
                        "title": "Test Notification",
                        "message": "This is a test notification"
                    },
                    "read_at": null,
                    "created_at": "2025-12-08T12:30:00.000000Z"
                }
            ]
        },
        "unread_count": 1
    }
}
```

### Step 2: Copy the Notification ID

**Copy the `id` field** from the response above. It's a UUID like:
- `c7a9dec4-a896-49ba-9921-563a420be8f2` ✅ (Correct - UUID format)
- NOT `64` ❌ (Wrong - this is a user ID)

### Step 3: Use the Correct ID

**In Postman:**

```
POST http://127.0.0.1:8000/api/notifications/c7a9dec4-a896-49ba-9921-563a420be8f2/mark-read
Authorization: Bearer 17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162
Accept: application/json
```

**Replace `c7a9dec4-a896-49ba-9921-563a420be8f2` with the actual ID from Step 1.**

---

## 🔍 Visual Guide

### Wrong Way ❌:
```
POST /api/notifications/64/mark-read
```
- `64` is a user ID
- This will NOT work

### Correct Way ✅:
```
POST /api/notifications/c7a9dec4-a896-49ba-9921-563a420be8f2/mark-read
```
- `c7a9dec4-a896-49ba-9921-563a420be8f2` is a notification UUID
- This WILL work

---

## 📋 Quick Checklist

- [ ] Step 1: Call `GET /api/notifications` with your admin token
- [ ] Step 2: Find the `id` field in the response (it's a UUID)
- [ ] Step 3: Copy that UUID
- [ ] Step 4: Use it in `POST /api/notifications/{UUID}/mark-read`
- [ ] Step 5: Make sure you use the SAME token for all requests

---

## 🛠️ Helper Script

If you want to see all notification IDs for the admin user, run:

```bash
php get_notification_id.php admin@test.com
```

This will show you:
- All notifications for admin@test.com
- The correct UUID for each notification
- The exact URL to use in Postman

---

## ⚠️ Important Notes

1. **Notification IDs are UUIDs**, not integers
2. **User IDs are integers** (like 64, 66, etc.)
3. **You must get the notification ID from `GET /api/notifications`**
4. **The notification must belong to the user whose token you're using**

---

## 🎯 Example Flow

1. **Login:**
   ```
   POST /api/auth/login
   → Get token: 17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162
   ```

2. **Get Notifications:**
   ```
   GET /api/notifications
   → See notification with ID: c7a9dec4-a896-49ba-9921-563a420be8f2
   ```

3. **Mark as Read:**
   ```
   POST /api/notifications/c7a9dec4-a896-49ba-9921-563a420be8f2/mark-read
   → Success!
   ```

---

## ✅ Success Response

When you use the correct UUID, you'll see:
```json
{
    "status": true,
    "message": "Notification marked as read."
}
```

