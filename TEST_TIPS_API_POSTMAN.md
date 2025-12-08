# How to Test Tips API in Postman

## 📋 Step-by-Step Guide

### Step 1: Open Postman
1. Open Postman application
2. Create a new request or use existing collection

---

### Step 2: Set Request Method and URL

**Method:** `GET`

**URL:** 
```
http://127.0.0.1:8000/api/tips
```

Or if you have a base URL variable:
```
{{base_url}}/api/tips
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
    "message": "Tips retrieved successfully.",
    "data": [
        {
            "id": 1,
            "title": "Water Your Plants Regularly",
            "content": "Make sure to water your plants regularly, especially during hot weather. Check the soil moisture before watering to avoid overwatering.",
            "type": "general",
            "status": "published",
            "language": "en",
            "scheduled_at": null,
            "created_by": 66,
            "created_at": "2025-12-08T12:30:00.000000Z",
            "updated_at": "2025-12-08T12:30:00.000000Z"
        },
        {
            "id": 2,
            "title": "Weekly Fertilizer Application",
            "content": "Apply fertilizer once a week during the growing season. Use organic fertilizers for better results and healthier plants.",
            "type": "weekly",
            "status": "published",
            "language": "en",
            "scheduled_at": null,
            "created_by": 66,
            "created_at": "2025-12-08T12:30:00.000000Z",
            "updated_at": "2025-12-08T12:30:00.000000Z"
        },
        {
            "id": 3,
            "title": "Monthly Garden Maintenance",
            "content": "Perform monthly maintenance tasks: prune dead branches, check for pests, and refresh mulch around plants.",
            "type": "monthly",
            "status": "published",
            "language": "en",
            "scheduled_at": null,
            "created_by": 66,
            "created_at": "2025-12-08T12:30:00.000000Z",
            "updated_at": "2025-12-08T12:30:00.000000Z"
        },
        {
            "id": 4,
            "title": "Seasonal Planting Guide",
            "content": "Plan your seasonal planting: Spring for vegetables, Summer for flowers, Fall for bulbs, and Winter for indoor plants.",
            "type": "seasonal",
            "status": "published",
            "language": "en",
            "scheduled_at": null,
            "created_by": 66,
            "created_at": "2025-12-08T12:30:00.000000Z",
            "updated_at": "2025-12-08T12:30:00.000000Z"
        },
        {
            "id": 5,
            "title": "Proper Pruning Techniques",
            "content": "Learn proper pruning techniques to promote healthy growth. Always use clean, sharp tools and prune at the right time of year for each plant type.",
            "type": "general",
            "status": "published",
            "language": "en",
            "scheduled_at": null,
            "created_by": 66,
            "created_at": "2025-12-08T12:30:00.000000Z",
            "updated_at": "2025-12-08T12:30:00.000000Z"
        }
    ]
}
```

---

## 🎯 Testing Individual Tip

### Get Single Tip by ID

**Method:** `GET`

**URL:**
```
http://127.0.0.1:8000/api/tips/1
```

**Headers:** Same as above (Authorization with Bearer token)

**Expected Response:**
```json
{
    "status": true,
    "message": "Tip retrieved successfully.",
    "data": {
        "id": 1,
        "title": "Water Your Plants Regularly",
        "content": "Make sure to water your plants regularly...",
        "type": "general",
        "status": "published",
        "language": "en",
        "scheduled_at": null,
        "created_by": 66,
        "created_at": "2025-12-08T12:30:00.000000Z",
        "updated_at": "2025-12-08T12:30:00.000000Z"
    }
}
```

---

## 📸 Visual Guide

### Postman Setup:

1. **Request Tab:**
   ```
   GET http://127.0.0.1:8000/api/tips
   ```

2. **Authorization Tab:**
   - Type: `Bearer Token`
   - Token: `17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162`

3. **Headers Tab (Optional - if not using Authorization tab):**
   ```
   Accept: application/json
   Authorization: Bearer 17|fXKHJkjT7epyj5Ymlr3h0aKjGuHeL7oRHCPcKYcbea936162
   ```

---

## ⚠️ Common Issues

### Issue 1: Empty Array
**Problem:** Response shows `"data": []`

**Solution:**
- Check if tips exist in database
- Make sure tips have `status = 'published'`
- Run: `php create_sample_tips.php` to create sample tips

### Issue 2: Unauthenticated Error
**Problem:** `{"status": false, "message": "Unauthenticated."}`

**Solution:**
- Make sure you're using a valid token
- Token format should be: `ID|hash` (include the `ID|` prefix)
- Login again to get a fresh token if needed

### Issue 3: Token Expired
**Problem:** Token no longer works

**Solution:**
1. Login again:
   ```
   POST /api/auth/login
   {
     "email": "admin@test.com",
     "password": "password"
   }
   ```
2. Copy the new token from response
3. Update Authorization header in Postman

---

## 🔄 Using Postman Collection

If you have the Postman collection (`postman/tandil-backend.postman_collection.json`):

1. Import the collection into Postman
2. Find the "Tips" folder
3. Select "Get All Tips" request
4. Make sure the `{{token}}` variable is set to your admin token
5. Click Send

---

## 📝 Quick Test Checklist

- [ ] Postman is open
- [ ] Request method is `GET`
- [ ] URL is correct: `http://127.0.0.1:8000/api/tips`
- [ ] Authorization header is set with Bearer token
- [ ] Accept header is set to `application/json`
- [ ] Laravel server is running (`php artisan serve`)
- [ ] Tips exist in database (run `php create_sample_tips.php` if needed)
- [ ] Click Send button

---

## 🎉 Success Indicators

✅ **Status Code:** `200 OK`  
✅ **Response has:** `"status": true`  
✅ **Response has:** `"message": "Tips retrieved successfully."`  
✅ **Response has:** `"data"` array with tip objects  
✅ **Each tip has:** `id`, `title`, `content`, `type`, `status`, `language`

---

## 📚 Related Endpoints

- **Get All Tips:** `GET /api/tips`
- **Get Single Tip:** `GET /api/tips/{id}`
- **Create Tip (Admin Panel):** `http://127.0.0.1:8000/admin/tips/create`

---

## 💡 Pro Tips

1. **Save Request:** Save this request in Postman for future use
2. **Use Variables:** Set `{{base_url}}` and `{{token}}` as variables
3. **Test Different Roles:** Try with different user tokens (client, technician, etc.)
4. **Check Response Time:** Monitor how long the request takes
5. **Export Collection:** Export your Postman collection to share with team

---

## 🚀 Next Steps

After testing the Tips API, you can test:
- Notifications API: `GET /api/notifications`
- Categories API: `GET /api/categories`
- Areas API: `GET /api/areas` (requires area_manager token)

Happy Testing! 🎉

