# Correct Complaint Route

## ❌ Wrong Route

```
POST /api/complaints  ← This doesn't exist!
```

## ✅ Correct Route

```
POST /api/auth/complaints  ← Use this!
```

---

## 📋 All Complaint Routes

### Create Complaint (Client/Admin)
```
POST /api/auth/complaints
Authorization: Bearer {{client_token}} or {{admin_token}}
Content-Type: application/json

{
  "visit_id": 69,
  "notes": "Complaint description"
}
```

### List Complaints (Client/Admin)
```
GET /api/auth/complaints
Authorization: Bearer {{client_token}} or {{admin_token}}
```

### Get Complaint (Client/Admin)
```
GET /api/auth/complaints/{id}
Authorization: Bearer {{client_token}} or {{admin_token}}
```

### Update Complaint (Admin/Supervisor)
```
PUT /api/auth/complaints/{id}
Authorization: Bearer {{admin_token}} or {{supervisor_token}}
Content-Type: application/json

{
  "status": "resolved",
  "notes": "Updated notes"
}
```

### Delete Complaint (Admin Only)
```
DELETE /api/auth/complaints/{id}
Authorization: Bearer {{admin_token}}
```

---

## 🎯 Supervisor-Specific Routes

### List Complaints (Supervisor)
```
GET /api/supervisor/complaints
Authorization: Bearer {{supervisor_token}}
```

### Escalate Complaint (Supervisor)
```
POST /api/supervisor/complaints/{id}/escalate
Authorization: Bearer {{supervisor_token}}
Content-Type: application/json

{
  "status": "escalated",
  "note": "Escalating to management"
}
```

---

## ✅ Quick Fix

**Change your request from:**
```
POST /api/complaints  ← Wrong!
```

**To:**
```
POST /api/auth/complaints  ← Correct!
```

**Complete Request:**
```
POST {{base_url}}/api/auth/complaints
Authorization: Bearer {{client_token}}
Content-Type: application/json

{
  "visit_id": 69,
  "notes": "Technician did not complete all tasks as requested"
}
```

---

## 📝 Why?

The complaints routes are inside the `auth` middleware group in `routes/api.php`:

```php
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::prefix('complaints')->group(function () {
        Route::post('/', [ComplaintController::class, 'store']);
        // ...
    });
});
```

This creates the route: `/api/auth/complaints` (not `/api/complaints`)

---

## 🎯 Summary

**To create a complaint:**
- ✅ Use: `POST /api/auth/complaints`
- ❌ Don't use: `POST /api/complaints`

**Try again with the correct route!** 🚀

