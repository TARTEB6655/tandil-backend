# Fix for Modules 8 & 9 - Testing Guide

## Problem
Modules 8 (Technician & Supervisor Routes) and 9 (Admin & HR Routes) were not working because:
1. **Admin UserController** was returning views instead of JSON for API requests
2. Users need proper roles (technician, supervisor, admin) to access these endpoints

## Fixes Applied
✅ **UserController** now returns JSON for API requests (similar to RoleController)
✅ All methods now check `$request->expectsJson() || $request->is('api/*')` before returning views

---

## How to Test Module 8: Technician & Supervisor Routes

### Step 1: Register/Login as Technician

**Register Technician:**
```json
POST /api/auth/register
{
  "name": "Test Technician",
  "email": "tech@test.com",
  "phone": "+971501234567",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "technician"
}
```

**Or Login with Seeded Account:**
```json
POST /api/auth/login
{
  "email": "technician1@example.com",
  "password": "password"
}
```

**Copy the token from response!**

### Step 2: Test Technician Endpoints

**Update Postman token variable:**
- Set `{{token}}` = (your technician token)

**Test Endpoints:**
1. `GET /api/tech/visits` - List assigned visits
2. `POST /api/tech/visits/{id}/accept` - Accept visit
3. `POST /api/tech/visits/{id}/start` - Start visit
4. `POST /api/tech/visits/{id}/complete` - Complete visit
5. `POST /api/tech/visits/{id}/photos` - Upload photo

**Note:** Visits must be assigned to the technician (`technician_id` must match technician's user ID)

---

### Step 3: Register/Login as Supervisor

**Register Supervisor:**
```json
POST /api/auth/register
{
  "name": "Test Supervisor",
  "email": "supervisor@test.com",
  "phone": "+971501234568",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "supervisor"
}
```

**Or Login with Seeded Account:**
```json
POST /api/auth/login
{
  "email": "supervisor1@example.com",
  "password": "password"
}
```

**Copy the token and update Postman!**

### Step 4: Test Supervisor Endpoints

**Update Postman token variable:**
- Set `{{token}}` = (your supervisor token)

**Test Endpoints:**
1. `GET /api/supervisor/visits` - List visits in supervised areas
2. `GET /api/supervisor/visits/{id}` - Review visit
3. `POST /api/supervisor/visits/{id}/recommend` - Recommend products
4. `POST /api/supervisor/visits/{id}/finalize` - Finalize report
5. `POST /api/supervisor/visits/{id}/status` - Update visit status
6. `GET /api/supervisor/areas` - List supervised areas
7. `GET /api/supervisor/complaints` - List complaints
8. `POST /api/supervisor/complaints/{id}/escalate` - Escalate complaint

**Note:** Supervisor needs to be assigned to areas to see visits

---

## How to Test Module 9: Admin & HR Routes

### Step 1: Register/Login as Admin

**Register Admin:**
```json
POST /api/auth/register
{
  "name": "Test Admin",
  "email": "admin@test.com",
  "phone": "+971501234569",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "admin"
}
```

**Or Login with Seeded Account:**
```json
POST /api/auth/login
{
  "email": "admin@example.com",
  "password": "password"
}
```

**Copy the token and update Postman!**

### Step 2: Test Admin Endpoints

**Update Postman token variable:**
- Set `{{token}}` = (your admin token)

**Test User Management:**
1. `GET /api/admin/users` - List all users ✅ **NOW RETURNS JSON**
2. `POST /api/admin/users` - Create user ✅ **NOW RETURNS JSON**
3. `GET /api/admin/users/{id}` - Get user ✅ **NOW RETURNS JSON**
4. `PUT /api/admin/users/{id}` - Update user ✅ **NOW RETURNS JSON**
5. `DELETE /api/admin/users/{id}` - Delete user ✅ **NOW RETURNS JSON**

**Test Roles:**
1. `GET /api/admin/roles` - List roles
2. `POST /api/admin/roles` - Create role

**Test HR:**
1. `GET /api/admin/hr/employees` - List employees
2. `POST /api/admin/hr/employees` - Create employee
3. `GET /api/admin/hr/employees/{id}` - Get employee
4. `PUT /api/admin/hr/employees/{id}` - Update employee
5. `DELETE /api/admin/hr/employees/{id}` - Delete employee

---

## Expected JSON Responses

### Admin Users - List
```json
{
  "status": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Test User",
        "email": "user@test.com",
        "role": "client",
        "status": "active",
        "roles": [...]
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

### Admin Users - Create
```json
{
  "status": true,
  "message": "User created successfully.",
  "data": {
    "id": 2,
    "name": "New User",
    "email": "newuser@example.com",
    "role": "client",
    "status": "active",
    "roles": [...]
  }
}
```

### Admin Users - Get
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "Test User",
    "email": "user@test.com",
    "role": "client",
    "status": "active",
    "roles": [...]
  }
}
```

### Admin Users - Update
```json
{
  "status": true,
  "message": "User updated successfully.",
  "data": {
    "id": 1,
    "name": "Updated Name",
    "email": "user@test.com",
    "role": "client",
    "status": "active",
    "roles": [...]
  }
}
```

### Admin Users - Delete
```json
{
  "status": true,
  "message": "User deleted successfully."
}
```

---

## Troubleshooting

### Still getting "User does not have the right roles"

1. **Check token**: Make sure you're using the correct role's token
2. **Check role in database**: Verify user has correct `role` column value
3. **Check Spatie role**: Run:
   ```bash
   php artisan tinker
   >>> $user = App\Models\User::where('email', 'admin@test.com')->first();
   >>> $user->roles; // Should show role
   >>> $user->assignRole('admin'); // If missing, assign it
   ```

### Getting HTML instead of JSON

- Make sure you're calling `/api/*` routes, not web routes
- Check `Accept: application/json` header is set in Postman
- The fix ensures API routes return JSON automatically

### No visits showing for technician

- Visits must have `technician_id` set to technician's user ID
- Create a visit with `technician_id` or assign via admin

### No visits showing for supervisor

- Supervisor must be assigned to areas
- Visits must have `area_id` matching supervisor's supervised areas
- Assign supervisor to areas via admin or seeder

---

## Quick Test Checklist

### Module 8 (Technician & Supervisor)
- [ ] Register/Login as technician
- [ ] Test `GET /api/tech/visits` (should return JSON array)
- [ ] Register/Login as supervisor
- [ ] Test `GET /api/supervisor/visits` (should return JSON array)
- [ ] Test `GET /api/supervisor/areas` (should return JSON array)

### Module 9 (Admin & HR)
- [ ] Register/Login as admin
- [ ] Test `GET /api/admin/users` (should return JSON, not HTML)
- [ ] Test `POST /api/admin/users` (should return JSON)
- [ ] Test `GET /api/admin/users/{id}` (should return JSON)
- [ ] Test `PUT /api/admin/users/{id}` (should return JSON)
- [ ] Test `DELETE /api/admin/users/{id}` (should return JSON)
- [ ] Test `GET /api/admin/roles` (should return JSON)
- [ ] Test `GET /api/admin/hr/employees` (should return JSON)

---

## Summary

✅ **Fixed:** Admin UserController now returns JSON for API requests
✅ **Working:** All endpoints in modules 8 and 9 should now work correctly
✅ **Requirement:** Users must have correct roles (technician, supervisor, admin) to access respective endpoints

