# Who Can Assign Technicians & Supervisors to Visits?

## 📋 Summary

**Who can assign:**
1. ✅ **Admin** - Can assign to any visit
2. ✅ **Client** - Can assign to their own visits (subscriptions they own)
3. ❌ **Technician** - Cannot assign (can only update if already assigned)
4. ❌ **Supervisor** - Cannot assign (can only update if area is supervised)

---

## 🎯 Methods to Assign Technicians & Supervisors

### Method 1: When Creating a Visit (POST /api/visits)

**Who can do this:**
- ✅ **Admin** - Can create visit with technician_id/supervisor_id for any subscription
- ✅ **Client** - Can create visit with technician_id/supervisor_id for their own subscriptions

**Request:**
```
POST /api/visits
Authorization: Bearer {{token}} (admin or client token)
Content-Type: application/json

{
  "subscription_id": 123,
  "technician_id": 10,        ← Assign technician
  "supervisor_id": 5,          ← Assign supervisor (optional)
  "area_id": 1,                ← Assign area (optional)
  "scheduled_date": "2025-01-20",
  "status": "pending"
}
```

**Example Response:**
```json
{
  "status": true,
  "message": "Visit created successfully",
  "data": {
    "id": 62,
    "subscription_id": 123,
    "technician_id": 10,
    "supervisor_id": 5,
    "area_id": 1,
    "scheduled_date": "2025-01-20",
    "status": "pending"
  }
}
```

---

### Method 2: Update Existing Visit (PUT /api/visits/{id})

**Who can do this:**
- ✅ **Admin** - Can update any visit (including technician_id, supervisor_id, area_id)
- ✅ **Client** - Can update their own visits (subscriptions they own)
- ⚠️ **Technician** - Can only update if already assigned to them (but cannot change technician_id)
- ⚠️ **Supervisor** - Can only update if area is supervised (but cannot change supervisor_id)

**Request:**
```
PUT /api/visits/62
Authorization: Bearer {{token}} (admin or client token)
Content-Type: application/json

{
  "technician_id": 10,        ← Assign/change technician
  "supervisor_id": 5,          ← Assign/change supervisor
  "area_id": 1                 ← Assign/change area
}
```

**Example Response:**
```json
{
  "status": true,
  "message": "Visit updated successfully",
  "data": {
    "id": 62,
    "technician_id": 10,
    "supervisor_id": 5,
    "area_id": 1
  }
}
```

**Note:** The current `update` method validation only allows:
- `scheduled_date`
- `notes`
- `status`

**But the code allows updating `technician_id`, `supervisor_id`, and `area_id` if they're in the request!**

---

## 🔐 Authorization Rules

### Admin
- ✅ Can create visits for any subscription
- ✅ Can assign technician/supervisor to any visit
- ✅ Can update any visit field
- ✅ Full access

### Client
- ✅ Can create visits for their own subscriptions
- ✅ Can assign technician/supervisor when creating visit
- ✅ Can update their own visits (subscriptions they own)
- ✅ Can assign technician/supervisor when updating their own visits
- ❌ Cannot modify other clients' visits

### Technician
- ❌ Cannot create visits
- ❌ Cannot assign themselves or others
- ✅ Can only update visits already assigned to them
- ⚠️ Cannot change `technician_id` (can only update notes, status, scheduled_date)

### Supervisor
- ❌ Cannot create visits
- ❌ Cannot assign themselves or others
- ✅ Can only update visits in areas they supervise
- ⚠️ Cannot change `supervisor_id` (can only update notes, status)

---

## 📝 Step-by-Step: Assigning Technician to Visit

### Scenario: Client wants to assign technician to their visit

**Step 1: Client gets their subscription**
```
GET /api/subscriptions
Authorization: Bearer {{client_token}}
```
- Find subscription ID (e.g., 123)
- Visits are auto-generated

**Step 2: Client gets list of technicians** (if available)
- Currently no public endpoint to list technicians
- Admin can see all users via `GET /api/admin/users?role=technician`
- Or client can use known technician IDs

**Step 3: Client updates visit to assign technician**
```
PUT /api/visits/62
Authorization: Bearer {{client_token}}
Content-Type: application/json

{
  "technician_id": 10
}
```

**Step 4: Technician can now see the visit**
```
GET /api/tech/visits
Authorization: Bearer {{technician_token}}
```
- Visit ID 62 should now appear in the list

---

## 📝 Step-by-Step: Admin Assigning Technician & Supervisor

### Scenario: Admin assigns both technician and supervisor

**Step 1: Admin gets visit details**
```
GET /api/visits/62
Authorization: Bearer {{admin_token}}
```

**Step 2: Admin gets list of technicians and supervisors**
```
GET /api/admin/users?role=technician
GET /api/admin/users?role=supervisor
Authorization: Bearer {{admin_token}}
```

**Step 3: Admin updates visit**
```
PUT /api/visits/62
Authorization: Bearer {{admin_token}}
Content-Type: application/json

{
  "technician_id": 10,
  "supervisor_id": 5,
  "area_id": 1
}
```

**Step 4: Verify assignment**
```
GET /api/visits/62
Authorization: Bearer {{admin_token}}
```
- Should show technician_id, supervisor_id, area_id

---

## 🎯 Recommended Workflow

### For Clients:
1. **Create subscription** → Visits auto-generated
2. **Update visit** with `technician_id` to assign technician
3. Technician receives visit and can start working

### For Admins:
1. **View all visits** → `GET /api/visits`
2. **View all users** → `GET /api/admin/users?role=technician`
3. **Assign technician/supervisor** → `PUT /api/visits/{id}`

### For Technicians:
1. **View assigned visits** → `GET /api/tech/visits`
2. **Accept visit** → `POST /api/tech/visits/{id}/accept`
3. **Start work** → `POST /api/tech/visits/{id}/start`

### For Supervisors:
1. **View visits in supervised areas** → `GET /api/supervisor/visits`
2. **Review visit** → `GET /api/supervisor/visits/{id}`
3. **Approve/reject** → `POST /api/supervisor/visits/{id}/status`

---

## ⚠️ Important Notes

### Current Limitations:

1. **No Public Technician List:**
   - Clients cannot see list of available technicians
   - Solution: Admin provides technician IDs, or create endpoint to list technicians

2. **Update Method Validation:**
   - Current validation only checks `scheduled_date`, `notes`, `status`
   - But `technician_id`, `supervisor_id`, `area_id` can be updated if sent
   - **Recommendation:** Add these fields to validation if you want to allow updates

3. **No Bulk Assignment:**
   - Cannot assign technician to multiple visits at once
   - Must update each visit individually

4. **No Auto-Assignment:**
   - Visits are created without technician/supervisor
   - Must be manually assigned

---

## 🔧 How to Test Assignment

### Test 1: Client Assigns Technician

1. **Login as Client:**
```json
POST /api/auth/login
{
  "email": "client@test.com",
  "password": "password"
}
```

2. **Get Visit ID:**
```json
GET /api/subscriptions
```
- Find a visit ID from the subscription

3. **Get Technician ID:**
```json
GET /api/auth/profile
```
- If you're testing, you can use a known technician ID

4. **Assign Technician:**
```json
PUT /api/visits/62
{
  "technician_id": 10
}
```

5. **Switch to Technician Token:**
```json
GET /api/tech/visits
```
- Should show the visit!

---

### Test 2: Admin Assigns Both

1. **Login as Admin:**
```json
POST /api/auth/login
{
  "email": "admin@test.com",
  "password": "password"
}
```

2. **Get List of Technicians:**
```json
GET /api/admin/users?role=technician
```

3. **Get List of Supervisors:**
```json
GET /api/admin/users?role=supervisor
```

4. **Assign to Visit:**
```json
PUT /api/visits/62
{
  "technician_id": 10,
  "supervisor_id": 5,
  "area_id": 1
}
```

5. **Verify:**
```json
GET /api/visits/62
```
- Should show all assignments

---

## 📋 Quick Reference

| Role | Can Create Visit | Can Assign Technician | Can Assign Supervisor | Can Update Visit |
|------|-----------------|---------------------|---------------------|-----------------|
| **Admin** | ✅ Any | ✅ Any | ✅ Any | ✅ Any |
| **Client** | ✅ Own | ✅ Own | ✅ Own | ✅ Own |
| **Technician** | ❌ No | ❌ No | ❌ No | ⚠️ Assigned only |
| **Supervisor** | ❌ No | ❌ No | ❌ No | ⚠️ Area supervised |

---

## ✅ Summary

**To assign technician/supervisor to a visit:**

1. **Admin or Client** can use:
   - `PUT /api/visits/{id}` with `technician_id`, `supervisor_id`, `area_id`

2. **When creating visit:**
   - `POST /api/visits` with `technician_id`, `supervisor_id`, `area_id` in body

3. **Best Practice:**
   - Admin assigns technicians/supervisors after visit creation
   - Or client assigns when creating/updating their own visits

**The most common flow:**
1. Client creates subscription → Visits auto-generated
2. Admin assigns technician/supervisor → `PUT /api/visits/{id}`
3. Technician sees visit → `GET /api/tech/visits`
4. Technician accepts and starts work

