# Testing Technician Endpoints - Step by Step Guide

## Problem
You're getting: `"User does not have the right roles."` when trying to access `/api/tech/visits`

## Solution: Create/Login as Technician User

---

## Method 1: Register a New Technician (Recommended)

### Step 1: Register as Technician

**Request**: `POST /api/auth/register`

**Body:**
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

**Expected Response (201):**
```json
{
  "status": true,
  "message": "User registered successfully.",
  "token": "1|xxxxxxxxxxxxx",
  "role": "technician",
  "user": {
    "id": 1,
    "name": "Test Technician",
    "email": "technician@test.com",
    "role": "technician"
  }
}
```

**Important:** Copy the `token` from the response!

### Step 2: Update Token in Postman

1. Go to collection Variables
2. Set `token` = (paste the token you copied)
3. Save

### Step 3: Test Technician Endpoints

Now you can test:
- `GET /api/tech/visits` - List assigned visits
- `POST /api/tech/visits/{id}/accept` - Accept visit
- `POST /api/tech/visits/{id}/start` - Start visit
- `POST /api/tech/visits/{id}/complete` - Complete visit
- `POST /api/tech/visits/{id}/photos` - Upload photo

---

## Method 2: Use Seeded Technician Account

If you've run seeders, you can login with:

**Email:** `technician1@example.com`  
**Password:** `password`

**Request**: `POST /api/auth/login`

**Body:**
```json
{
  "email": "technician1@example.com",
  "password": "password"
}
```

**Expected Response:**
```json
{
  "status": true,
  "message": "Login successful.",
  "token": "2|xxxxxxxxxxxxx",
  "role": "technician",
  "user": {...}
}
```

Copy the token and update it in Postman variables.

---

## Method 3: Run Seeder to Create Test Users

If you haven't run seeders yet:

```bash
php artisan db:seed --class=ComprehensiveSeeder
```

This will create:
- `technician1@example.com` to `technician10@example.com`
- Password: `password`
- Role: `technician`

Then login with any of these accounts.

---

## Testing Technician Flow

### 1. List Assigned Visits
**Request**: `GET /api/tech/visits`

**Expected Response:**
```json
{
  "status": true,
  "data": []
}
```

(Empty if no visits assigned yet)

### 2. Assign Visit to Technician (Admin Only)

To test technician endpoints, you need a visit assigned to the technician. This is usually done by:
- Admin assigns technician to visit
- Or create visit with technician_id

**Option A: Create Visit with Technician ID**

**Request**: `POST /api/visits` (as admin or client)

**Body:**
```json
{
  "subscription_id": 1,
  "technician_id": YOUR_TECHNICIAN_USER_ID,
  "scheduled_date": "2025-01-20",
  "status": "pending"
}
```

**Option B: Admin Assigns Technician** (if admin endpoint exists)

### 3. Accept Visit
**Request**: `POST /api/tech/visits/{id}/accept`

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "status": "accepted",
    "accepted_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

### 4. Start Visit
**Request**: `POST /api/tech/visits/{id}/start`

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "status": "in_progress",
    "started_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

### 5. Complete Visit
**Request**: `POST /api/tech/visits/{id}/complete`

**Body:**
```json
{
  "notes": "Visit completed successfully"
}
```

**Expected Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "status": "completed",
    "completed_at": "2025-01-13T10:00:00.000000Z"
  }
}
```

---

## Quick Test Checklist

- [ ] Register/Login as technician
- [ ] Copy token and update in Postman
- [ ] Test `GET /api/tech/visits` (should work now)
- [ ] Create/assign a visit to technician
- [ ] Test accept, start, complete endpoints
- [ ] Test photo upload

---

## Troubleshooting

### Still getting "User does not have the right roles"

1. **Check token**: Make sure you're using the technician's token, not client token
2. **Check role**: Verify user has `role = 'technician'` in database
3. **Check Spatie role**: Run this to verify:
   ```bash
   php artisan tinker
   >>> $user = App\Models\User::where('email', 'technician@test.com')->first();
   >>> $user->roles; // Should show 'technician'
   ```
4. **Re-assign role**: If missing, run:
   ```bash
   php artisan tinker
   >>> $user = App\Models\User::where('email', 'technician@test.com')->first();
   >>> $user->assignRole('technician');
   ```

### No visits showing

- Visits need to be assigned to the technician (`technician_id` must match technician's user ID)
- Create a visit with `technician_id` set to your technician's user ID

