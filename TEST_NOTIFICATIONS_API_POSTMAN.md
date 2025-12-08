# How to Test Notifications API in Postman

## 📋 Step-by-Step Guide

### Step 1: Open Postman
1. Open Postman application
2. Create a new request or use existing collection

---

### Step 2: Set Request Method and URL

**Method:** `GET`

**URL:** 
```
http://127.0.0.1:8000/api/notifications
```

Or if you have a base URL variable:
```
{{base_url}}/api/notifications
```

---

### Step 3: Set Headers

Go to the **Headers** tab and add:

| Key | Value |
|-----|-------|
| `Accept` | `application/json` |
| `Authorization` | `Bearer 17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162` |

**OR** use the Authorization tab:
1. Go to **Authorization** tab
2. Select **Type: Bearer Token**
3. Paste your token: `17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162`

---

### Step 4: Send Request

Click the **Send** button.

---

### Step 5: Expected Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
    "status": true,
    "message": "Notifications retrieved successfully.",
    "data": {
        "notifications": {
            "data": [
                {
                    "id": "uuid-here",
                    "type": "App\\Notifications\\AdminNotification",
                    "notifiable_type": "App\\Models\\User",
                    "notifiable_id": 64,
                    "data": {
                        "title": "Welcome to Tandil!",
                        "message": "Thank you for joining our platform."
                    },
                    "read_at": null,
                    "created_at": "2025-12-08T12:30:00.000000Z",
                    "updated_at": "2025-12-08T12:30:00.000000Z"
                }
            ],
            "current_page": 1,
            "per_page": 20,
            "total": 1
        },
        "unread_count": 1
    }
}
```

**Note:** Each user only sees their own notifications. If you see an empty array, it means:
- No notifications have been sent to this user yet
- All notifications have been read and deleted

---

## 🎯 Mark Notification as Read

### Mark Single Notification as Read

**Method:** `POST`

**URL:**
```
http://127.0.0.1:8000/api/notifications/{notification_id}/mark-read
```

**Example:**
```
http://127.0.0.1:8000/api/notifications/abc123-def456-ghi789/mark-read
```

**Headers:** Same as above (Authorization with Bearer token)

**Body:** None required (empty body)

**Expected Response:**
```json
{
    "status": true,
    "message": "Notification marked as read."
}
```

---

## 📸 Visual Guide

### Postman Setup for Get Notifications:

1. **Request Tab:**
   ```
   GET http://127.0.0.1:8000/api/notifications
   ```

2. **Authorization Tab:**
   - Type: `Bearer Token`
   - Token: `17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162`

3. **Headers Tab (Optional - if not using Authorization tab):**
   ```
   Accept: application/json
   Authorization: Bearer 17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162
   ```

### Postman Setup for Mark as Read:

1. **Request Tab:**
   ```
   POST http://127.0.0.1:8000/api/notifications/{notification_id}/mark-read
   ```

2. **Authorization Tab:**
   - Type: `Bearer Token`
   - Token: `17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162`

3. **Body Tab:**
   - Select: `none` (no body required)

---

## 🔔 How to Create Notifications (For Testing)

Notifications are created by **Admin only** via the Admin Panel. However, you can create test notifications using a script:

### Option 1: Use Admin Panel
1. Go to: `http://127.0.0.1:8000/admin`
2. Login as admin
3. Navigate to Notifications section
4. Click "Send Notification"
5. Select recipient (all users, specific role, or specific users)
6. Enter title and message
7. Send notification

### Option 2: Create Test Notification via Script
Run the script I'll create below to send a test notification to your user.

---

## ⚠️ Common Issues

### Issue 1: Empty Notifications Array
**Problem:** Response shows `"notifications": {"data": []}`

**Possible Causes:**
- No notifications have been sent to this user
- All notifications have been read and deleted
- User hasn't received any notifications yet

**Solution:**
- Create a notification via Admin Panel
- Or run the test script to create a notification

### Issue 2: Unauthenticated Error
**Problem:** `{"status": false, "message": "Unauthenticated."}`

**Solution:**
- Make sure you're using a valid token
- Token format should be: `ID|hash` (include the `ID|` prefix)
- Login again to get a fresh token if needed

### Issue 3: Notification Not Found (Mark as Read)
**Problem:** `404 Not Found` when trying to mark notification as read

**Solution:**
- Make sure you're using the correct notification ID
- The notification ID is a UUID, not a simple number
- Get the notification ID from the `GET /api/notifications` response

---

## 📝 Quick Test Checklist

### Get Notifications:
- [ ] Postman is open
- [ ] Request method is `GET`
- [ ] URL is correct: `http://127.0.0.1:8000/api/notifications`
- [ ] Authorization header is set with Bearer token
- [ ] Accept header is set to `application/json`
- [ ] Laravel server is running (`php artisan serve`)
- [ ] Click Send button

### Mark as Read:
- [ ] Request method is `POST`
- [ ] URL includes notification ID: `/api/notifications/{id}/mark-read`
- [ ] Authorization header is set with Bearer token
- [ ] Click Send button

---

## 🎉 Success Indicators

✅ **Status Code:** `200 OK`  
✅ **Response has:** `"status": true`  
✅ **Response has:** `"message": "Notifications retrieved successfully."`  
✅ **Response has:** `"data"` object with:
   - `notifications` (paginated list)
   - `unread_count` (number of unread notifications)

---

## 📚 Related Endpoints

- **Get All Notifications:** `GET /api/notifications`
- **Mark Notification as Read:** `POST /api/notifications/{id}/mark-read`
- **Create Notification (Admin Panel):** `http://127.0.0.1:8000/admin/notifications/create`

---

## 💡 Pro Tips

1. **User-Specific:** Each user only sees their own notifications
2. **Unread Count:** Check `unread_count` to see how many unread notifications exist
3. **Pagination:** Notifications are paginated (20 per page by default)
4. **Notification Types:** Different notification types may have different data structures
5. **Mark as Read:** Once marked as read, `read_at` field will have a timestamp

---

## 🔄 Testing Flow

1. **Step 1:** Login as a user (client, admin, etc.)
2. **Step 2:** Get notifications - should be empty initially
3. **Step 3:** Create a notification via Admin Panel (as admin) targeting that user
4. **Step 4:** Get notifications again - should see the new notification
5. **Step 5:** Mark notification as read
6. **Step 6:** Get notifications again - `read_at` should now have a timestamp

---

## 🚀 Next Steps

After testing the Notifications API, you can test:
- Tips API: `GET /api/tips`
- Categories API: `GET /api/categories`
- Areas API: `GET /api/areas` (requires area_manager token)

Happy Testing! 🎉

