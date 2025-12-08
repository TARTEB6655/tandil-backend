# Complete Guide: Testing All Supervisor APIs

## 📋 Prerequisites

1. **Postman** installed and running
2. **Laravel backend** running (`php artisan serve`)
3. **Postman Collection** imported
4. **Base URL** set: `{{base_url}} = http://127.0.0.1:8000`

---

## 🔧 Step 1: Register/Login as Supervisor

### Option A: Register New Supervisor

**Request:**
```
POST /api/auth/register
Content-Type: application/json

{
  "name": "Test Supervisor",
  "email": "supervisor@test.com",
  "phone": "+971501234568",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "supervisor"
}
```

**Expected Response (201):**
```json
{
  "status": true,
  "message": "User registered successfully.",
  "token": "1|xxxxxxxxxxxxxxxxxxxxx",  ← Copy this token!
  "role": "supervisor",
  "user": {
    "id": 32,
    "name": "Test Supervisor",
    "email": "supervisor@test.com",
    "role": "supervisor"
  }
}
```

### Option B: Login with Seeded Account

**Request:**
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "supervisor1@example.com",
  "password": "password"
}
```

**Copy the `token` from response!**

---

## 🔧 Step 2: Set Token in Postman

1. Click on your **Collection** name in Postman
2. Go to **Variables** tab
3. Set `token` = (paste the supervisor token you copied)
4. **Save**

---

## 📋 Step 3: All Supervisor APIs

All supervisor endpoints are in folder: **"8. Technician & Supervisor Routes"**

### ✅ API 1: List Visits in Supervised Areas

**Request:**
```
GET /api/supervisor/visits
Authorization: Bearer {{token}}
Accept: application/json
```

**Expected Response (200):**
```json
{
  "status": true,
  "data": [
    {
      "id": 62,
      "subscription_id": 123,
      "technician_id": 68,
      "supervisor_id": 32,
      "area_id": 1,
      "scheduled_date": "2025-01-20",
      "status": "completed",
      "subscription": {...},
      "technician": {...},
      "report": {...},
      "photos": [...]
    }
  ]
}
```

**Note:** Returns visits in areas supervised by this supervisor. If empty, supervisor needs to be assigned to areas.

---

### ✅ API 2: Review Visit Details

**Request:**
```
GET /api/supervisor/visits/{{visit_id}}
Authorization: Bearer {{token}}
Accept: application/json
```

**Expected Response (200):**
```json
{
  "status": true,
  "data": {
    "id": 62,
    "subscription_id": 123,
    "technician_id": 68,
    "supervisor_id": 32,
    "area_id": 1,
    "scheduled_date": "2025-01-20",
    "status": "completed",
    "photos": [...],
    "subscription": {
      "id": 123,
      "client_id": 64,
      "plan": "1_month"
    },
    "technician": {
      "id": 68,
      "name": "Technician Name"
    },
    "report": {...}
  }
}
```

**Note:** Visit must be in a supervised area, otherwise returns 403 Forbidden.

---

### ✅ API 3: Recommend Products

**Request:**
```
POST /api/supervisor/visits/{{visit_id}}/recommend
Authorization: Bearer {{token}}
Content-Type: application/json
Accept: application/json

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
    "visit_id": 62,
    "recommended_products": [
      {
        "id": 1,
        "name": "Product 1",
        "price": 100.00
      },
      {
        "id": 2,
        "name": "Product 2",
        "price": 150.00
      }
    ]
  }
}
```

**Note:** Visit must be in a supervised area.

---

### ✅ API 4: Finalize Report

**Request:**
```
POST /api/supervisor/visits/{{visit_id}}/finalize
Authorization: Bearer {{token}}
Content-Type: application/json
Accept: application/json

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
    "id": 62,
    "status": "approved",
    "finalized_at": "2025-01-13T10:00:00.000000Z",
    "report": {
      "summary": "Visit completed successfully. All recommendations provided."
    }
  }
}
```

**Note:** Status can be `approved` or `rejected`.

---

### ✅ API 5: Update Visit Status

**Request:**
```
POST /api/supervisor/visits/{{visit_id}}/status
Authorization: Bearer {{token}}
Content-Type: application/json
Accept: application/json

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
    "id": 62,
    "status": "approved"
  }
}
```

**Valid Statuses:** `approved`, `rejected`

**Note:** Visit must be in a supervised area.

---

### ✅ API 6: List Supervised Areas

**Request:**
```
GET /api/supervisor/areas
Authorization: Bearer {{token}}
Accept: application/json
```

**Expected Response (200):**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "name": "Dubai",
      "description": "Service area for Dubai",
      "technicians": [...],
      "visits": [...]
    },
    {
      "id": 2,
      "name": "Abu Dhabi",
      "description": "Service area for Abu Dhabi"
    }
  ]
}
```

**Note:** Returns areas supervised by this supervisor. If empty `[]`, supervisor is not assigned to any areas.

---

### ✅ API 7: List Complaints

**Request:**
```
GET /api/supervisor/complaints
Authorization: Bearer {{token}}
Accept: application/json
```

**Expected Response (200):**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "visit_id": 62,
      "status": "open",
      "description": "Complaint description",
      "created_at": "2025-01-13T10:00:00.000000Z"
    }
  ]
}
```

**Note:** Returns complaints related to visits in supervised areas.

---

### ✅ API 8: Escalate Complaint

**Request:**
```
POST /api/supervisor/complaints/{{complaint_id}}/escalate
Authorization: Bearer {{token}}
Content-Type: application/json
Accept: application/json

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
    "status": "escalated",
    "notes": "Escalating to management for review"
  }
}
```

**Note:** Complaint must be related to a visit in a supervised area.

---

## 🎯 Complete Testing Workflow

### Prerequisites Setup:

1. **Register/Login as Supervisor** → Get token
2. **Assign Supervisor to Area** (via admin or database)
3. **Create Visit with Area ID** (matching supervisor's area)

### Testing Order:

1. ✅ **List Areas** → `GET /api/supervisor/areas`
   - Should show supervised areas
   - If empty, assign supervisor to area first

2. ✅ **List Visits** → `GET /api/supervisor/visits`
   - Should show visits in supervised areas
   - If empty, create visit with matching `area_id`

3. ✅ **Review Visit** → `GET /api/supervisor/visits/{{visit_id}}`
   - View visit details

4. ✅ **Recommend Products** → `POST /api/supervisor/visits/{{visit_id}}/recommend`
   - Add product recommendations

5. ✅ **Finalize Report** → `POST /api/supervisor/visits/{{visit_id}}/finalize`
   - Approve/reject visit

6. ✅ **Update Status** → `POST /api/supervisor/visits/{{visit_id}}/status`
   - Change visit status

7. ✅ **List Complaints** → `GET /api/supervisor/complaints`
   - View complaints

8. ✅ **Escalate Complaint** → `POST /api/supervisor/complaints/{{complaint_id}}/escalate`
   - Escalate complaint

---

## ⚠️ Common Issues & Solutions

### Issue 1: Empty Visits List

**Problem:** `GET /api/supervisor/visits` returns empty array `[]`

**Solutions:**
1. **Check if supervisor is assigned to areas:**
   ```
   GET /api/supervisor/areas
   ```
   - If empty, assign supervisor to area first

2. **Assign supervisor to area:**
   - Use admin endpoint (if available)
   - Or use database/tinker:
   ```php
   $supervisor = App\Models\User::find(32);
   $area = App\Models\Area::find(1);
   $area->supervisors()->syncWithoutDetaching([$supervisor->id]);
   ```

3. **Create visit with matching area_id:**
   ```
   POST /api/visits
   {
     "subscription_id": 123,
     "area_id": 1,  ← Must match supervisor's area
     "scheduled_date": "2025-01-20"
   }
   ```

---

### Issue 2: "Forbidden" Error

**Problem:** Getting 403 Forbidden when accessing visit

**Solutions:**
1. **Check visit area_id matches supervisor's area:**
   - Visit must have `area_id` that supervisor supervises
   - Check: `GET /api/supervisor/areas` to see supervised areas

2. **Verify supervisor is assigned to that area:**
   - Use: `GET /api/supervisor/areas`
   - Should include the area_id from the visit

---

### Issue 3: "Visit not found" or "Complaint not found"

**Problem:** Getting 404 errors

**Solutions:**
1. **Check visit/complaint ID exists:**
   - Use correct ID from previous responses
   - Set `{{visit_id}}` variable in Postman

2. **Verify visit is in supervised area:**
   - Visit must have `area_id` matching supervisor's area

---

## 📋 Quick Reference Table

| API | Method | Endpoint | Purpose |
|-----|--------|----------|---------|
| 1 | GET | `/api/supervisor/visits` | List visits in supervised areas |
| 2 | GET | `/api/supervisor/visits/{id}` | Review visit details |
| 3 | POST | `/api/supervisor/visits/{id}/recommend` | Recommend products |
| 4 | POST | `/api/supervisor/visits/{id}/finalize` | Finalize report |
| 5 | POST | `/api/supervisor/visits/{id}/status` | Update visit status |
| 6 | GET | `/api/supervisor/areas` | List supervised areas |
| 7 | GET | `/api/supervisor/complaints` | List complaints |
| 8 | POST | `/api/supervisor/complaints/{id}/escalate` | Escalate complaint |

---

## ✅ Testing Checklist

### Setup:
- [ ] Registered/logged in as supervisor
- [ ] Token set in Postman
- [ ] Supervisor assigned to at least one area
- [ ] Visit created with matching `area_id`

### APIs:
- [ ] Test: List areas (`GET /api/supervisor/areas`)
- [ ] Test: List visits (`GET /api/supervisor/visits`)
- [ ] Test: Review visit (`GET /api/supervisor/visits/{id}`)
- [ ] Test: Recommend products (`POST /api/supervisor/visits/{id}/recommend`)
- [ ] Test: Finalize report (`POST /api/supervisor/visits/{id}/finalize`)
- [ ] Test: Update status (`POST /api/supervisor/visits/{id}/status`)
- [ ] Test: List complaints (`GET /api/supervisor/complaints`)
- [ ] Test: Escalate complaint (`POST /api/supervisor/complaints/{id}/escalate`)

---

## 🚀 Quick Start

1. **Register/Login as supervisor** → Copy token
2. **Set token in Postman** → Update `{{token}}` variable
3. **Check areas:** `GET /api/supervisor/areas`
4. **If empty, assign supervisor to area** (use admin or database)
5. **Create visit with matching area_id**
6. **Test all APIs one by one!**

---

## 💡 Pro Tips

1. **Save Variables:**
   - Set `{{visit_id}}` after creating/getting a visit
   - Set `{{complaint_id}}` after creating/getting a complaint
   - Makes testing easier!

2. **Check Areas First:**
   - Always check `GET /api/supervisor/areas` first
   - Ensures supervisor is assigned to areas

3. **Area ID Must Match:**
   - Visit `area_id` must match supervisor's supervised area
   - Check areas before creating visits

4. **Use Postman Collection:**
   - All supervisor APIs are in folder: "8. Technician & Supervisor Routes"
   - Pre-configured with correct URLs and headers

---

## 📝 Example Complete Flow

### Step 1: Setup
```json
POST /api/auth/register
{
  "name": "Test Supervisor",
  "email": "supervisor@test.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "supervisor"
}
```
→ Copy token

### Step 2: Assign to Area (via admin or database)
```php
// Using tinker or script
$supervisor = App\Models\User::where('email', 'supervisor@test.com')->first();
$area = App\Models\Area::find(1);
$area->supervisors()->syncWithoutDetaching([$supervisor->id]);
```

### Step 3: Check Areas
```
GET /api/supervisor/areas
```
→ Should show area ID 1

### Step 4: Create Visit (as admin/client)
```
POST /api/visits
{
  "subscription_id": 123,
  "area_id": 1,  ← Matches supervisor's area
  "scheduled_date": "2025-01-20"
}
```
→ Copy visit_id

### Step 5: Test All APIs
1. `GET /api/supervisor/visits` → Should show visit
2. `GET /api/supervisor/visits/62` → View details
3. `POST /api/supervisor/visits/62/recommend` → Recommend products
4. `POST /api/supervisor/visits/62/finalize` → Finalize report
5. `POST /api/supervisor/visits/62/status` → Update status
6. `GET /api/supervisor/complaints` → List complaints
7. `POST /api/supervisor/complaints/1/escalate` → Escalate complaint

---

## ✅ Summary

**All 8 Supervisor APIs:**

1. ✅ List Visits - `GET /api/supervisor/visits`
2. ✅ Review Visit - `GET /api/supervisor/visits/{id}`
3. ✅ Recommend Products - `POST /api/supervisor/visits/{id}/recommend`
4. ✅ Finalize Report - `POST /api/supervisor/visits/{id}/finalize`
5. ✅ Update Status - `POST /api/supervisor/visits/{id}/status`
6. ✅ List Areas - `GET /api/supervisor/areas`
7. ✅ List Complaints - `GET /api/supervisor/complaints`
8. ✅ Escalate Complaint - `POST /api/supervisor/complaints/{id}/escalate`

**Key Requirements:**
- Supervisor must be assigned to areas
- Visits must have `area_id` matching supervisor's areas
- Use supervisor token in Authorization header

**Ready to test!** Follow the workflow above and test each API one by one! 🚀

