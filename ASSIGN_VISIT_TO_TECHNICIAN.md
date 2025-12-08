# Assign Visit to Technician - Quick Guide

## ✅ Current Status

You have:
- ✅ Subscription created (ID: 123)
- ✅ Visit auto-generated (ID: 62)
- ✅ Visit status: `pending`
- ⚠️ Visit `technician_id`: `null` (needs to be assigned)

---

## 🎯 Next Step: Assign Visit to Your Technician

### Step 1: Get Your Technician User ID

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/auth/profile`
- **Headers:**
  - `Authorization: Bearer {{token}}` (use your **technician token**)
  - `Accept: application/json`

**Expected Response:**
```json
{
  "status": true,
  "token": "...",
  "role": "technician",
  "user": {
    "id": YOUR_TECHNICIAN_ID,  ← Copy this ID!
    "name": "Test Technician",
    "email": "technician@test.com",
    "role": "technician"
  }
}
```

**Note down:** `user.id` (this is your technician user ID)

---

### Step 2: Update Visit to Assign Technician

**Request:**
- **Method:** `PUT`
- **URL:** `{{base_url}}/api/visits/62` (use the visit ID from your subscription response)
- **Headers:**
  - `Authorization: Bearer {{token}}` (you can use **client token** or **technician token**)
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Body:**
```json
{
  "technician_id": YOUR_TECHNICIAN_USER_ID
}
```

**Replace `YOUR_TECHNICIAN_USER_ID` with the ID you got from Step 1!**

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Visit updated successfully",
  "data": {
    "id": 62,
    "subscription_id": 123,
    "technician_id": YOUR_TECHNICIAN_USER_ID,  ← Should now be set!
    "scheduled_date": "2025-01-15",
    "status": "pending"
  }
}
```

---

### Step 3: Test Technician Endpoint Again

Now switch to your **technician token** and test:

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/tech/visits`
- **Headers:**
  - `Authorization: Bearer {{token}}` (use **technician token**)
  - `Accept: application/json`

**Expected Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 62,
      "subscription_id": 123,
      "technician_id": YOUR_TECHNICIAN_USER_ID,
      "scheduled_date": "2025-01-15",
      "status": "pending",
      "subscription": {
        "id": 123,
        "plan": "1_month",
        "client_id": 64
      }
    }
  ]
}
```

✅ **Success!** The visit should now appear in the technician's assigned visits!

---

## 🚀 Complete Technician Workflow

Now you can test the complete workflow:

### 1. ✅ Get Assigned Visits
```
GET /api/tech/visits
```
**Status:** ✅ Working - Should show visit ID 62

### 2. Accept Visit
```
POST /api/tech/visits/62/accept
```
**Expected:** Status changes to "accepted"

### 3. Start Visit
```
POST /api/tech/visits/62/start
```
**Expected:** Status changes to "in_progress"

### 4. Upload Photo
```
POST /api/tech/visits/62/photos
Body: form-data
  Key: photo (File type)
  Value: Select an image file
```
**Expected:** Photo uploaded successfully

### 5. Complete Visit
```
POST /api/tech/visits/62/complete
Body: {
  "notes": "Visit completed successfully. All tasks done."
}
```
**Expected:** Status changes to "completed"

---

## 📋 Quick Reference

**Your Current Data:**
- Subscription ID: `123`
- Visit ID: `62`
- Client ID: `64`
- Technician ID: `?` (get from profile endpoint)

**Endpoints to Use:**
- Get Technician Profile: `GET /api/auth/profile` (with technician token)
- Update Visit: `PUT /api/visits/62` (with client or technician token)
- Get Assigned Visits: `GET /api/tech/visits` (with technician token)

---

## 💡 Pro Tips

1. **Save Variables in Postman:**
   - Set `{{visit_id}} = 62`
   - Set `{{subscription_id}} = 123`
   - Makes testing easier!

2. **Switch Tokens:**
   - Keep both client and technician tokens
   - Switch between them as needed

3. **Check Visit Status:**
   - After each action, check visit status
   - Use `GET /api/visits/62` to see full details

---

## ✅ Checklist

- [x] Subscription created
- [x] Visit auto-generated (ID: 62)
- [ ] Get technician user ID from profile
- [ ] Update visit with `technician_id`
- [ ] Test `GET /api/tech/visits` (should show visit now)
- [ ] Test accept visit
- [ ] Test start visit
- [ ] Test upload photo
- [ ] Test complete visit

---

## 🎯 What to Do Now

1. **Get your technician user ID:**
   - `GET /api/auth/profile` (with technician token)
   - Copy the `user.id` value

2. **Update the visit:**
   - `PUT /api/visits/62`
   - Body: `{"technician_id": YOUR_TECHNICIAN_ID}`

3. **Test technician endpoint:**
   - `GET /api/tech/visits` (with technician token)
   - Should now show visit ID 62!

4. **Continue with workflow:**
   - Accept → Start → Upload Photo → Complete

You're almost there! Just assign the technician and you can test the full workflow! 🚀

