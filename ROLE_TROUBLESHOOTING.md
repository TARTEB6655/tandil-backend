# Role Troubleshooting Guide

## Problem: "User does not have the right roles" Error

This error occurs when:
1. The user doesn't have the Spatie role assigned (even if `role` column is set)
2. The role doesn't exist in the database
3. The role exists but for a different guard (web vs sanctum)

## Solution

### Step 1: Ensure Roles Exist for Both Guards

Roles need to exist for both `web` and `sanctum` guards because:
- Web routes use `web` guard
- API routes use `sanctum` guard

**Run the role seeder:**
```bash
php artisan db:seed --class=RoleSeeder
```

This will create roles for both guards:
- `client` (web)
- `client` (sanctum)
- `technician` (web)
- `technician` (sanctum)
- `supervisor` (web)
- `supervisor` (sanctum)
- `area_manager` (web)
- `area_manager` (sanctum)
- `hr` (web)
- `hr` (sanctum)
- `admin` (web)
- `admin` (sanctum)

### Step 2: Fix Existing Users

**Run the fix command:**
```bash
php artisan users:fix-roles
```

This will:
- Assign Spatie roles to all users based on their `role` column
- Skip users that already have the correct role
- Show a summary of fixed/skipped users

### Step 3: Verify User Has Role

**Check a specific user:**
```bash
php artisan tinker
```

Then in tinker:
```php
$user = App\Models\User::where('email', 'your-email@example.com')->first();
$user->role; // Should show role column value
$user->hasRole('technician'); // Should return true
$user->roles; // Should show Spatie roles
```

### Step 4: Register New User (If Needed)

If you need to create a new user with a role:

**Register via API:**
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

The registration automatically assigns the Spatie role.

### Step 5: Manually Assign Role (If Needed)

If a user exists but doesn't have the role:

```bash
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'user@example.com')->first();
$user->assignRole('technician'); // Assign role
$user->syncRoles(['technician']); // Or sync (removes old, adds new)
```

## Common Issues

### Issue 1: Role exists but user still gets error

**Solution:** Make sure roles exist for the correct guard. Run:
```bash
php artisan db:seed --class=RoleSeeder
php artisan users:fix-roles
```

### Issue 2: User has role column but not Spatie role

**Solution:** Run the fix command:
```bash
php artisan users:fix-roles
```

### Issue 3: Using wrong token

**Solution:** Make sure you're using the token from the user with the correct role:
1. Login/Register as the correct role (technician, supervisor, admin)
2. Copy the token from the response
3. Update `{{token}}` in Postman

### Issue 4: Roles don't exist in database

**Solution:** Run seeders:
```bash
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=RolePermissionSeeder
```

## Testing Checklist

- [ ] Roles exist for both web and sanctum guards
- [ ] User has `role` column set correctly
- [ ] User has Spatie role assigned (check with `$user->hasRole('role_name')`)
- [ ] Using correct token (from user with correct role)
- [ ] Token is set in Postman `{{token}}` variable
- [ ] Request includes `Authorization: Bearer {{token}}` header

## Quick Fix Command

Run this to fix everything at once:
```bash
php artisan db:seed --class=RoleSeeder && php artisan users:fix-roles
```

## Verify It's Working

After fixing, test an endpoint:
```bash
# As technician
GET /api/tech/visits
Authorization: Bearer YOUR_TECHNICIAN_TOKEN

# As supervisor
GET /api/supervisor/visits
Authorization: Bearer YOUR_SUPERVISOR_TOKEN

# As admin
GET /api/admin/users
Authorization: Bearer YOUR_ADMIN_TOKEN
```

If you still get errors, check:
1. Token belongs to correct user
2. User has correct role assigned
3. Roles exist in database for correct guard

