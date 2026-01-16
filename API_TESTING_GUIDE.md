# API Testing Guide

## Issue: "Unauthenticated" Error

When accessing `GET /api/admin/users` directly in browser, you get:
```json
{
  "success": false,
  "message": "Unauthenticated. Please provide a valid authentication token."
}
```

**This is EXPECTED behavior** - the API requires authentication.

## How to Test the API Properly

### Option 1: Using Postman

1. **Get Authentication Token:**
   - First, login via API:
     ```
     POST /api/auth/login
     Body: {
       "email": "admin@example.com",
       "password": "your_password"
     }
     ```
   - Copy the `token` from response

2. **Test Users API:**
   - Method: `GET`
   - URL: `http://localhost:8000/api/admin/users`
   - Headers:
     - `Accept: application/json`
     - `Authorization: Bearer YOUR_TOKEN_HERE`

### Option 2: Using cURL

```bash
# Step 1: Login and get token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "your_password"
  }'

# Step 2: Use the token to access users API
curl -X GET http://localhost:8000/api/admin/users \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Option 3: Using JavaScript (Fetch)

```javascript
// Get token first (from login)
const token = 'YOUR_TOKEN_HERE';

// Then fetch users
fetch('http://localhost:8000/api/admin/users', {
  method: 'GET',
  headers: {
    'Accept': 'application/json',
    'Authorization': `Bearer ${token}`
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

### Option 4: Using Browser Extension

Install a browser extension like:
- **ModHeader** (Chrome/Edge)
- **Requestly** (Chrome/Edge)

Add headers:
- `Accept: application/json`
- `Authorization: Bearer YOUR_TOKEN_HERE`

Then visit: `http://localhost:8000/api/admin/users`

## API Requirements

### Authentication
- **Required:** Yes
- **Type:** Bearer Token (Sanctum)
- **Header:** `Authorization: Bearer {token}`

### Authorization
- **Required Role:** `admin`
- User must have 'admin' role assigned

### Headers
- `Accept: application/json` (recommended)
- `Authorization: Bearer {token}` (required)

## Expected Response

When authenticated correctly:

```json
{
  "success": true,
  "message": "Users retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "1234567890",
      "role": "admin",
      "role_display": "Administrator",
      "employee_id": "ADM-0001",
      "status": "active",
      "avatar": "J",
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:00:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 15,
    "total": 31,
    "from": 1,
    "to": 15
  }
}
```

## Common Errors

### 401 Unauthenticated
- **Cause:** Missing or invalid token
- **Solution:** Login first and use the token

### 403 Forbidden
- **Cause:** User doesn't have 'admin' role
- **Solution:** Assign 'admin' role to user

### 404 Not Found
- **Cause:** Wrong URL or route not registered
- **Solution:** Check route: `php artisan route:list --path=api/admin/users`

## Quick Test Script

Create a test file `test_api.php`:

```php
<?php
$token = 'YOUR_TOKEN_HERE';
$url = 'http://localhost:8000/api/admin/users';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
print_r(json_decode($response, true));
```

Run: `php test_api.php`

