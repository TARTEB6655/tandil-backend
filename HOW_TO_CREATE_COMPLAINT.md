# How to Create a Complaint for Testing

## ✅ Current Status

Your endpoint is **working correctly!** The empty array `[]` is expected because:
- There are no complaints in the database, OR
- Complaints exist but are not related to visits in supervisor's supervised areas

---

## 🎯 Solution: Create a Complaint

Supervisors can only see complaints for visits in areas they supervise. Since supervisor 67 supervises area 1, you need to create a complaint for a visit with `area_id: 1`.

You already have visit ID 69 with `area_id: 1` - perfect for testing!

---

## 📝 Step-by-Step: Create Complaint

### Step 1: Login as Client (Owner of the Visit)

The visit belongs to subscription 125, which belongs to client ID 66. You need to login as that client to create a complaint.

**Option A: Login as the Client**

```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@test.com",  ← Client from visit (ID: 66)
  "password": "password"
}
```

**Or register a new client:**
```
POST /api/auth/register
{
  "name": "Test Client",
  "email": "client@test.com",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}
```

**Copy the client token!**

---

### Step 2: Create Complaint

**Request:**
```
POST /api/auth/complaints
Authorization: Bearer {{client_token}}
Content-Type: application/json
Accept: application/json

{
  "visit_id": 69,
  "notes": "The technician did not complete all the required tasks during the visit."
}
```

**⚠️ Important:** The route is `/api/auth/complaints` (not `/api/complaints`)!

**Expected Response (201):**
```json
{
  "status": true,
  "message": "Complaint created successfully.",
  "data": {
    "id": 1,
    "visit_id": 69,
    "client_id": 66,
    "status": "open",
    "notes": "The technician did not complete all the required tasks during the visit.",
    "created_at": "2025-12-08T11:20:00.000000Z"
  }
}
```

**Copy the complaint ID!**

---

### Step 3: Test Supervisor Complaints Endpoint

**Switch back to supervisor token** and test:

```
GET /api/supervisor/complaints
Authorization: Bearer {{supervisor_token}}
Accept: application/json
```

**Expected Response (200):**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "visit_id": 69,
      "client_id": 66,
      "status": "open",
      "notes": "The technician did not complete all the required tasks during the visit.",
      "visit": {
        "id": 69,
        "area_id": 1,  ← Matches supervisor's area!
        "scheduled_date": "2025-01-20"
      },
      "client": {
        "id": 66,
        "name": "Test Admin",
        "email": "admin@test.com"
      }
    }
  ]
}
```

✅ **Success!** The complaint should now appear!

---

## 🔍 Why It Works

1. **Visit ID 69** has `area_id: 1`
2. **Supervisor 67** supervises area 1
3. **Complaint** is linked to visit 69
4. **Supervisor** can see complaints for visits in area 1

---

## 🎯 Complete Testing Flow

### 1. Create Complaint (as Client)

```
POST /api/auth/complaints
Authorization: Bearer {{client_token}}

{
  "visit_id": 69,
  "notes": "Complaint description here"
}
```

**⚠️ Note:** Route is `/api/auth/complaints` (inside auth group)!

### 2. List Complaints (as Supervisor)

```
GET /api/supervisor/complaints
Authorization: Bearer {{supervisor_token}}
```

Should show the complaint!

### 3. Escalate Complaint (as Supervisor)

```
POST /api/supervisor/complaints/1/escalate
Authorization: Bearer {{supervisor_token}}
Content-Type: application/json

{
  "status": "escalated",
  "note": "Escalating to area manager for review"
}
```

---

## ⚠️ Important Notes

### Who Can Create Complaints?

- ✅ **Client** - Can create complaints for their own visits
- ✅ **Admin** - Can create complaints for any visit
- ❌ **Technician** - Cannot create complaints
- ❌ **Supervisor** - Cannot create complaints

### Authorization Rules:

```php
// Client can only create complaints for their own visits
if ($visit->subscription->client_id !== $user->id && !$user->hasRole('admin')) {
    return error('You can only file complaints for your own visits', 403);
}
```

---

## 📋 Quick Checklist

- [ ] Login as client (owner of visit 69)
- [ ] Create complaint for visit 69
- [ ] Switch to supervisor token
- [ ] Test `GET /api/supervisor/complaints` (should show complaint)
- [ ] Test escalate complaint endpoint

---

## 💡 Pro Tips

1. **Use Visit ID 69:**
   - You already have this visit
   - It has `area_id: 1` (matches supervisor's area)
   - Perfect for testing!

2. **Client Must Own Visit:**
   - The visit's subscription must belong to the client
   - Visit 69 belongs to subscription 125
   - Subscription 125 belongs to client 66
   - So login as client 66 (or admin)

3. **Save Complaint ID:**
   - After creating complaint, save the ID
   - Use it for escalate endpoint: `POST /api/supervisor/complaints/{id}/escalate`

---

## ✅ Summary

**To see complaints in supervisor endpoint:**

1. **Create complaint** for a visit in supervisor's area
2. **Use visit ID 69** (has area_id: 1, matches supervisor's area)
3. **Login as client** (owner of the visit) to create complaint
4. **Test supervisor endpoint** - should show complaint now!

**Quick Test:**
```
POST /api/auth/complaints (as client)
{
  "visit_id": 69,
  "notes": "Test complaint"
}

GET /api/supervisor/complaints (as supervisor)
→ Should show the complaint!
```

The endpoint is working! Just need to create a complaint for testing! 🚀

