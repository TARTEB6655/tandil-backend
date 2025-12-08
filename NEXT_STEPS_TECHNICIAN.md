# Next Steps: Testing Technician APIs with Real Data

## ✅ Current Status

You've successfully tested:
- ✅ `GET /api/tech/visits` - Working! (Returns empty array `[]`)

This is **expected** - the array is empty because no visits are assigned to your technician yet.

---

## 🎯 Next Steps: Get Visits Assigned to Technician

To test the full technician workflow, you need visits assigned to your technician. Here are 3 ways to do this:

---

## Method 1: Create Visit with Technician ID (Recommended)

### Step 1: Get Your Technician User ID

**Option A: From Registration Response**
- When you registered as technician, the response included `"user": {"id": X}`
- Note down that `id` value

**Option B: Check Profile**
- `GET /api/auth/profile` (with technician token)
- The response shows your user `id`

### Step 2: Create a Subscription First (as Client)

You need a subscription to create visits. If you don't have one:

1. **Register/Login as Client:**
```json
POST /api/auth/register
{
  "name": "Test Client",
  "email": "client@test.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}
```

2. **Create Subscription:**
```json
POST /api/subscriptions
{
  "plan": "1_month",
  "start_date": "2025-01-15"
}
```
- Copy the `subscription_id` from response

3. **Switch back to Technician Token** in Postman

### Step 3: Create Visit with Technician ID

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/visits`
- **Headers:**
  - `Authorization: Bearer {{token}}` (technician token)
  - `Content-Type: application/json`
- **Body:**
```json
{
  "subscription_id": YOUR_SUBSCRIPTION_ID,
  "technician_id": YOUR_TECHNICIAN_USER_ID,
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
    "id": 1,
    "subscription_id": 1,
    "technician_id": YOUR_TECHNICIAN_USER_ID,
    "scheduled_date": "2025-01-20",
    "status": "pending"
  }
}
```

**Note:** Copy the `visit_id` (e.g., `1`) for next steps!

### Step 4: Test Get Assigned Visits Again

Now test:
- `GET /api/tech/visits`

**Expected Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 1,
      "technician_id": YOUR_TECHNICIAN_USER_ID,
      "scheduled_date": "2025-01-20",
      "status": "pending",
      "subscription": {...}
    }
  ]
}
```

✅ **Now you have a visit!** Continue with the workflow below.

---

## Method 2: Use Subscription Auto-Generated Visits

When a subscription is created, visits are auto-generated. You can assign a technician to one of those visits.

### Step 1: Create Subscription (as Client)

1. Login as client
2. Create subscription:
```json
POST /api/subscriptions
{
  "plan": "1_month",
  "start_date": "2025-01-15"
}
```

3. Response includes `visits` array with auto-generated visits
4. Copy a `visit_id` from the `visits` array

### Step 2: Update Visit to Assign Technician

**Request:**
- **Method:** `PUT`
- **URL:** `{{base_url}}/api/visits/{{visit_id}}`
- **Headers:**
  - `Authorization: Bearer {{token}}` (client or admin token)
  - `Content-Type: application/json`
- **Body:**
```json
{
  "technician_id": YOUR_TECHNICIAN_USER_ID
}
```

### Step 3: Switch to Technician Token

1. Update `{{token}}` in Postman to technician's token
2. Test: `GET /api/tech/visits`
3. Should now show the visit!

---

## Method 3: Admin Assigns Visit (If Admin Endpoint Exists)

If you have admin access, you can assign visits via admin endpoints.

---

## 🚀 Complete Technician Workflow

Once you have a visit assigned, test the complete workflow:

### 1. ✅ Get Assigned Visits
```
GET /api/tech/visits
```
**Status:** ✅ Already tested - Working!

### 2. Accept Visit
```
POST /api/tech/visits/{{visit_id}}/accept
```
**Expected:** Status changes to "accepted"

### 3. Start Visit
```
POST /api/tech/visits/{{visit_id}}/start
```
**Expected:** Status changes to "in_progress"

### 4. Upload Photo
```
POST /api/tech/visits/{{visit_id}}/photos
Body: form-data, Key: photo (File)
```
**Expected:** Photo uploaded successfully

### 5. Complete Visit
```
POST /api/tech/visits/{{visit_id}}/complete
Body: {"notes": "Visit completed successfully"}
```
**Expected:** Status changes to "completed"

---

## 📋 Quick Checklist

- [x] Technician registered/logged in
- [x] Token set in Postman
- [x] Tested: `GET /api/tech/visits` (returns empty array - expected)
- [ ] Created subscription (as client)
- [ ] Created visit with `technician_id` OR updated visit to assign technician
- [ ] Tested: `GET /api/tech/visits` (should show visit now)
- [ ] Tested: Accept visit
- [ ] Tested: Start visit
- [ ] Tested: Upload photo
- [ ] Tested: Complete visit

---

## 💡 Pro Tips

1. **Keep Multiple Tokens:** 
   - Save client token and technician token separately
   - Switch between them in Postman as needed

2. **Use Variables:**
   - Set `{{visit_id}}` in Postman variables after creating a visit
   - Makes testing easier

3. **Check Visit Status:**
   - After each action (accept, start, complete), check the visit status
   - Use `GET /api/visits/{{visit_id}}` to see full visit details

---

## 🎯 What to Do Now

**Recommended Next Step:**

1. **Get your technician user ID:**
   - `GET /api/auth/profile` (with technician token)
   - Note the `id` value

2. **Create a subscription** (as client):
   - Register/login as client
   - Create subscription
   - Note the `subscription_id`

3. **Create visit with technician_id:**
   - Use the visit creation endpoint
   - Set `technician_id` to your technician's user ID

4. **Test again:**
   - `GET /api/tech/visits` should now show the visit!

5. **Continue workflow:**
   - Accept → Start → Upload Photo → Complete

---

## ❓ Need Help?

If you get stuck:
- Check `TEST_TECHNICIAN_SUPERVISOR_APIS.md` for detailed steps
- Check `ROLE_TROUBLESHOOTING.md` if you get role errors
- Make sure `technician_id` matches your technician's user ID

**You're doing great!** The endpoint is working perfectly. Now just need to assign some visits to test the full workflow. 🚀

