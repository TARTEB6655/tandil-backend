# Complete Guide: Testing Technician & Supervisor APIs

## 📋 Prerequisites

1. **Postman** installed and running
2. **Laravel backend** running (`php artisan serve`)
3. **Postman Collection** imported (`postman/tandil-backend.postman_collection.json`)
4. **Base URL** set in Postman variables: `{{base_url}} = http://127.0.0.1:8000`

---

## 🔧 Part 1: Testing Technician APIs

### Step 1: Register/Login as Technician

**Option A: Register New Technician**

1. Open Postman
2. Go to folder: **"2. Authentication"** → **"Register"**
3. **Method:** `POST`
4. **URL:** `{{base_url}}/api/auth/register`
5. **Body (raw JSON):**
```json
{
  "name": "Test Technician",
  "email": "technician@test.com",
  "phone": "+971501234567",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "technician"
}
```
6. **Click Send**
7. **Expected Response (201):**
```json
{
  "status": true,
  "message": "User registered successfully.",
  "token": "1|xxxxxxxxxxxxxxxxxxxxx",
  "role": "technician",
  "user": {
    "id": 1,
    "name": "Test Technician",
    "email": "technician@test.com",
    "role": "technician"
  }
}
```
8. **Copy the `token` value!**

**Option B: Login with Seeded Account**

If you've run seeders, you can login with:
- Email: `technician1@example.com`
- Password: `password`

1. Go to **"2. Authentication"** → **"Login"**
2. **Body:**
```json
{
  "email": "technician1@example.com",
  "password": "password"
}
```
3. Copy the `token` from response

### Step 2: Set Token in Postman

1. Click on your **Collection** name in Postman
2. Go to **Variables** tab
3. Set `token` = (paste the token you copied)
4. **Save**

### Step 3: Test Technician Endpoints

Now you can test all technician endpoints. They're in folder: **"8. Technician & Supervisor Routes"**

#### ✅ Test 1: Get Assigned Visits

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/tech/visits`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "data": []
}
```
*(Empty array if no visits assigned yet)*

**To assign a visit to technician:**
- Create a visit with `technician_id` set to your technician's user ID
- Or use admin endpoint to assign visit to technician

---

#### ✅ Test 2: Accept Visit

**Prerequisites:** You need a visit ID that's assigned to this technician.

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/tech/visits/{{visit_id}}/accept`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Visit accepted successfully",
  "data": {
    "id": 1,
    "status": "accepted",
    "accepted_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

---

#### ✅ Test 3: Start Visit

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/tech/visits/{{visit_id}}/start`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Visit started successfully",
  "data": {
    "id": 1,
    "status": "in_progress",
    "started_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

---

#### ✅ Test 4: Complete Visit

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/tech/visits/{{visit_id}}/complete`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`
- **Body (raw JSON):**
```json
{
  "notes": "Visit completed successfully. All tasks done."
}
```

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Visit completed successfully",
  "data": {
    "id": 1,
    "status": "completed",
    "completed_at": "2025-01-13T10:00:00.000000Z",
    "notes": "Visit completed successfully. All tasks done."
  }
}
```

---

#### ✅ Test 5: Upload Photo to Visit

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/tech/visits/{{visit_id}}/photos`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`
- **Body (form-data):**
  - Key: `photo` (type: **File**)
  - Value: Select an image file

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Photo uploaded successfully",
  "data": {
    "id": 1,
    "visit_id": 1,
    "photo_path": "visits/photos/xxxxx.jpg",
    "created_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

---

## 🔧 Part 2: Testing Supervisor APIs

### Step 1: Register/Login as Supervisor

**Option A: Register New Supervisor**

1. Go to **"2. Authentication"** → **"Register"**
2. **Body:**
```json
{
  "name": "Test Supervisor",
  "email": "supervisor@test.com",
  "phone": "+971501234568",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "supervisor"
}
```
3. Copy the `token`

**Option B: Login with Seeded Account**

- Email: `supervisor1@example.com`
- Password: `password`

### Step 2: Update Token in Postman

1. Update `{{token}}` variable with supervisor's token
2. **Save**

### Step 3: Test Supervisor Endpoints

All supervisor endpoints are in folder: **"8. Technician & Supervisor Routes"**

#### ✅ Test 1: List Visits in Supervised Areas

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/supervisor/visits`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "data": []
}
```
*(Empty if supervisor not assigned to areas or no visits in those areas)*

**Note:** Supervisor must be assigned to areas to see visits. This is usually done by admin.

---

#### ✅ Test 2: Review Visit Details

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/supervisor/visits/{{visit_id}}`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "subscription_id": 1,
    "technician_id": 1,
    "area_id": 1,
    "scheduled_date": "2025-01-20",
    "status": "completed",
    "photos": [...],
    "subscription": {...},
    "report": {...}
  }
}
```

---

#### ✅ Test 3: Recommend Products

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/supervisor/visits/{{visit_id}}/recommend`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`
- **Body (raw JSON):**
```json
{
  "product_ids": [1, 2, 3],
  "notes": "These products are recommended based on the visit findings."
}
```

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Products recommended successfully",
  "data": {
    "visit_id": 1,
    "recommended_products": [...]
  }
}
```

---

#### ✅ Test 4: Finalize Report

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/supervisor/visits/{{visit_id}}/finalize`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`
- **Body (raw JSON):**
```json
{
  "summary": "Visit completed successfully. All recommendations provided.",
  "status": "approved"
}
```

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Report finalized successfully",
  "data": {
    "id": 1,
    "status": "approved",
    "finalized_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

---

#### ✅ Test 5: Update Visit Status

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/supervisor/visits/{{visit_id}}/status`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`
- **Body (raw JSON):**
```json
{
  "status": "approved"
}
```

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Visit status updated successfully",
  "data": {
    "id": 1,
    "status": "approved"
  }
}
```

---

#### ✅ Test 6: List Supervised Areas

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/supervisor/areas`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "data": []
}
```
*(Empty if supervisor not assigned to any areas)*

---

#### ✅ Test 7: List Complaints

**Request:**
- **Method:** `GET`
- **URL:** `{{base_url}}/api/supervisor/complaints`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`

**Expected Response (200):**
```json
{
  "status": true,
  "data": []
}
```

---

#### ✅ Test 8: Escalate Complaint

**Request:**
- **Method:** `POST`
- **URL:** `{{base_url}}/api/supervisor/complaints/{{complaint_id}}/escalate`
- **Headers:**
  - `Authorization: Bearer {{token}}`
  - `Accept: application/json`
- **Body (raw JSON):**
```json
{
  "status": "escalated",
  "note": "Escalating to management for review"
}
```

**Expected Response (200):**
```json
{
  "status": true,
  "message": "Complaint escalated successfully",
  "data": {
    "id": 1,
    "status": "escalated"
  }
}
```

---

## 🎯 Complete Testing Flow

### Scenario: Full Technician Workflow

1. **Register as Technician** → Get token
2. **Get Assigned Visits** → Should be empty initially
3. **Admin/Client creates visit** → Assigns to technician
4. **Get Assigned Visits** → Should show the visit
5. **Accept Visit** → Status changes to "accepted"
6. **Start Visit** → Status changes to "in_progress"
7. **Upload Photo** → Add photo to visit
8. **Complete Visit** → Status changes to "completed"

### Scenario: Full Supervisor Workflow

1. **Register as Supervisor** → Get token
2. **Admin assigns supervisor to area** (via admin endpoint)
3. **List Supervised Areas** → Should show assigned areas
4. **List Visits** → Should show visits in supervised areas
5. **Review Visit** → View visit details
6. **Recommend Products** → Add product recommendations
7. **Finalize Report** → Approve/reject visit
8. **Update Visit Status** → Change status if needed

---

## ⚠️ Common Issues & Solutions

### Issue 1: "User does not have the right roles"

**Solution:**
1. Make sure you registered/login with the correct role (`technician` or `supervisor`)
2. Run: `php artisan users:fix-roles`
3. Use the token from the correct user

### Issue 2: Empty visits list

**For Technician:**
- Visits must have `technician_id` set to your technician's user ID
- Create a visit with `technician_id` or assign via admin

**For Supervisor:**
- Supervisor must be assigned to areas
- Visits must have `area_id` matching supervisor's supervised areas
- Assign supervisor to areas via admin endpoint

### Issue 3: "Forbidden" error

**Solution:**
- Make sure you're using the correct token
- Check that the visit belongs to your technician/supervisor
- Verify role is correctly assigned: `php artisan users:fix-roles`

### Issue 4: Visit ID not found

**Solution:**
- First create a visit (as admin or client)
- Get the visit ID from the response
- Use that ID in subsequent requests

---

## 📝 Quick Reference

### Technician Endpoints
- `GET /api/tech/visits` - List assigned visits
- `POST /api/tech/visits/{id}/accept` - Accept visit
- `POST /api/tech/visits/{id}/start` - Start visit
- `POST /api/tech/visits/{id}/complete` - Complete visit
- `POST /api/tech/visits/{id}/photos` - Upload photo

### Supervisor Endpoints
- `GET /api/supervisor/visits` - List visits in supervised areas
- `GET /api/supervisor/visits/{id}` - Review visit
- `POST /api/supervisor/visits/{id}/recommend` - Recommend products
- `POST /api/supervisor/visits/{id}/finalize` - Finalize report
- `POST /api/supervisor/visits/{id}/status` - Update visit status
- `GET /api/supervisor/areas` - List supervised areas
- `GET /api/supervisor/complaints` - List complaints
- `POST /api/supervisor/complaints/{id}/escalate` - Escalate complaint

---

## ✅ Testing Checklist

### Technician
- [ ] Registered/logged in as technician
- [ ] Token set in Postman
- [ ] Tested: Get assigned visits
- [ ] Tested: Accept visit
- [ ] Tested: Start visit
- [ ] Tested: Complete visit
- [ ] Tested: Upload photo

### Supervisor
- [ ] Registered/logged in as supervisor
- [ ] Token set in Postman
- [ ] Tested: List visits
- [ ] Tested: Review visit
- [ ] Tested: Recommend products
- [ ] Tested: Finalize report
- [ ] Tested: Update visit status
- [ ] Tested: List areas
- [ ] Tested: List complaints
- [ ] Tested: Escalate complaint

---

## 🚀 Ready to Test!

Follow the steps above in order. Start with registering/login as the role you need, then test each endpoint one by one.

**Need help?** Check `ROLE_TROUBLESHOOTING.md` for detailed troubleshooting.

