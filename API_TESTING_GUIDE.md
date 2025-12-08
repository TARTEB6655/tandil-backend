# Tandil Backend API Testing Guide

## Table of Contents
1. [Auth Testing](#auth-testing)
2. [How to Test Other APIs](#how-to-test-other-apis)
3. [Module-by-Module Guide](#module-by-module-guide)
4. [Testing Order](#testing-order)

---

## A. Auth Testing

### 1. Register

**Endpoint:** `POST /api/auth/register`

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+971501234567",
  "password": "password123",
  "password_confirmation": "password123",
  "role": "client"
}
```

**Success Response (201):**
```json
{
  "status": true,
  "message": "User registered successfully.",
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "role": "client",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+971501234567",
    "role": "client",
    "status": "active"
  }
}
```

**Error Response (422):**
```json
{
  "status": false,
  "message": "Validation failed.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password confirmation does not match."]
  }
}
```

**Available Roles:**
- `client`
- `technician`
- `supervisor`
- `area_manager`
- `hr`
- `admin`

---

### 2. Login

**Endpoint:** `POST /api/auth/login`

**Request:**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Login successful.",
  "token": "2|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "role": "client",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "client",
    "status": "active"
  }
}
```

**Error Response (401):**
```json
{
  "status": false,
  "message": "Invalid login credentials."
}
```

**Inactive Account Response (403):**
```json
{
  "status": false,
  "message": "Account is not active. Please contact admin."
}
```

---

### 3. Token Usage

**Important:** The `token` field is returned in both register and login responses. **Always save this token** for subsequent API calls.

**Token Format:**
- Format: `{id}|{hash}`
- Example: `1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
- Length: ~60+ characters

**Using Token:**
Include the token in the `Authorization` header for all protected endpoints:

```
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

---

### 4. Role Field Requirement

**✅ REQUIRED:** Both `register` and `login` responses now include a `role` field.

**Response Structure:**
```json
{
  "status": true,
  "message": "...",
  "token": "...",
  "role": "client",  // ← This field is now included
  "user": {...}
}
```

**Why it's important:**
- Frontend can immediately determine user type
- No need for separate API call to get user role
- Enables role-based UI rendering

---

### 5. Logout

**Endpoint:** `POST /api/auth/logout`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "status": true,
  "message": "Logged out successfully."
}
```

**Note:** After logout, the token is invalidated and cannot be reused.

---

### 6. Profile

**Endpoint:** `GET /api/auth/profile`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "status": true,
  "role": "client",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+971501234567",
    "role": "client",
    "status": "active",
    "created_at": "2025-01-01T00:00:00.000000Z"
  }
}
```

---

### 7. Token Expiry

**Current Implementation:**
- Tokens do NOT expire automatically
- Tokens remain valid until:
  - User logs out (token deleted)
  - Token is manually revoked
  - User account is deactivated

**Best Practice:**
- Store token securely (e.g., secure storage in mobile app)
- Implement token refresh logic if needed
- Handle 401 responses by redirecting to login

---

## B. How to Test Other APIs

### 1. How to Pass Token

**Method 1: Authorization Header (Recommended)**
```
Authorization: Bearer {your_token_here}
```

**Example using cURL:**
```bash
curl -X GET "https://api.example.com/api/subscriptions" \
  -H "Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  -H "Accept: application/json"
```

**Example using Postman:**
1. Go to **Authorization** tab
2. Select **Bearer Token**
3. Paste token in **Token** field

**Example using JavaScript (Fetch):**
```javascript
fetch('https://api.example.com/api/subscriptions', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
})
```

---

### 2. Required Headers

**For ALL API requests:**
```
Accept: application/json
Content-Type: application/json
```

**For Protected Endpoints:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

### 3. Role-Based Access Rules

| Role | Can Access |
|------|------------|
| `client` | Own subscriptions, visits, reports, shop, tips, notifications, complaints |
| `technician` | Assigned visits, own reports, tech routes |
| `supervisor` | Visits in supervised areas, reports, recommendations |
| `area_manager` | All visits in managed areas, area management |
| `hr` | Employee management, tips, notifications |
| `admin` | Everything (full access) |

**Important:**
- Routes are protected by `role:role_name` middleware
- Users can only access resources they own or are authorized for
- Attempting to access unauthorized resources returns `403 Forbidden`

---

### 4. Example Successful Requests

**Get Subscriptions (Client):**
```bash
curl -X GET "https://api.example.com/api/subscriptions" \
  -H "Authorization: Bearer {client_token}" \
  -H "Accept: application/json"
```

**Create Subscription:**
```bash
curl -X POST "https://api.example.com/api/subscriptions" \
  -H "Authorization: Bearer {client_token}" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "plan": "1_month",
    "start_date": "2025-01-01"
  }'
```

---

### 5. Common Failed Responses

**401 Unauthenticated:**
```json
{
  "status": false,
  "message": "Unauthenticated."
}
```
**Fix:** Include valid `Authorization` header with token

**403 Forbidden:**
```json
{
  "status": false,
  "message": "Unauthorized. You do not have permission to perform this action."
}
```
**Fix:** User doesn't have required role or doesn't own the resource

**404 Not Found:**
```json
{
  "status": false,
  "message": "Resource not found."
}
```
**Fix:** Check if resource ID exists and user has access

**422 Validation Failed:**
```json
{
  "status": false,
  "message": "Validation failed.",
  "errors": {
    "plan": ["The selected plan is invalid."],
    "start_date": ["The start date must be a valid date."]
  }
}
```
**Fix:** Check validation errors and correct request data

**500 Server Error:**
```json
{
  "status": false,
  "message": "An error occurred. Please try again later."
}
```
**Fix:** Server-side issue, check logs or contact support

---

## C. Module-by-Module Guide

### 1. Subscriptions Module

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/subscriptions/plans` | Get all plans | ❌ No | - |
| GET | `/api/subscriptions` | List subscriptions | ✅ Yes | `client\|admin` |
| POST | `/api/subscriptions` | Create subscription | ✅ Yes | `client\|admin` |
| GET | `/api/subscriptions/{id}` | Get subscription | ✅ Yes | `client\|admin` |
| PUT | `/api/subscriptions/{id}` | Update subscription | ✅ Yes | `client\|admin` |
| DELETE | `/api/subscriptions/{id}` | Cancel subscription | ✅ Yes | `client\|admin` |
| POST | `/api/subscriptions/{id}/mark-paid` | Mark as paid | ✅ Yes | `client\|admin` |

#### Get Plans (Public)

**Request:**
```bash
GET /api/subscriptions/plans
```

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "plan": "1_month",
      "price": 500,
      "label": "1 month"
    },
    {
      "plan": "3_month",
      "price": 1450,
      "label": "3 month"
    },
    {
      "plan": "6_month",
      "price": 2900,
      "label": "6 month"
    },
    {
      "plan": "12_month",
      "price": 5500,
      "label": "12 month"
    }
  ]
}
```

#### Create Subscription

**Request:**
```json
POST /api/subscriptions
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan": "1_month",
  "start_date": "2025-01-01"
}
```

**Response (201):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "client_id": 1,
    "plan": "1_month",
    "start_date": "2025-01-01",
    "end_date": "2025-01-31",
    "amount": 500.00,
    "payment_status": "pending",
    "total_visits": 1,
    "completed_visits": 0,
    "visits": []
  }
}
```

**Possible Errors:**
- `422`: Invalid plan or date format
- `401`: Not authenticated
- `403`: Not authorized

**Permissions:**
- Clients can create subscriptions for themselves
- Admins can create subscriptions for any client

---

### 2. Visits Module

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/visits` | List visits | ✅ Yes | `client\|technician\|supervisor\|area_manager\|admin` |
| POST | `/api/visits` | Create visit | ✅ Yes | `admin\|supervisor` |
| GET | `/api/visits/{id}` | Get visit details | ✅ Yes | `client\|technician\|supervisor\|area_manager\|admin` |
| PUT | `/api/visits/{id}` | Update visit | ✅ Yes | `technician\|supervisor\|admin` |
| POST | `/api/visits/{id}/upload-photo` | Upload photo | ✅ Yes | `technician\|admin` |

#### List Visits

**Request:**
```bash
GET /api/visits
Authorization: Bearer {token}
```

**Response (Client):**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "subscription_id": 1,
      "technician_id": 2,
      "scheduled_date": "2025-01-15",
      "status": "pending",
      "photos": [
        {
          "id": 1,
          "type": "before",
          "photo_path": "visit_photos/abc123.jpg"
        }
      ],
      "subscription": {...},
      "technician": {...}
    }
  ]
}
```

**Permissions:**
- Clients see visits from their subscriptions
- Technicians see assigned visits
- Supervisors see visits in supervised areas
- Admins see all visits

---

### 3. Reports Module

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/reports` | List reports | ✅ Yes | `client\|technician\|supervisor\|area_manager\|admin` |
| GET | `/api/reports/{id}` | Get report | ✅ Yes | `client\|technician\|supervisor\|area_manager\|admin` |
| POST | `/api/reports` | Create report | ✅ Yes | `supervisor\|admin` |

#### Get Reports

**Request:**
```bash
GET /api/reports
Authorization: Bearer {client_token}
```

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "visit_id": 1,
      "supervisor_id": 3,
      "notes": "Trees need fertilization",
      "recommended_products": [1, 2, 3],
      "status": "finalized",
      "visit": {...},
      "supervisor": {...}
    }
  ]
}
```

**Permissions:**
- Clients see reports for their visits
- Technicians see reports for their visits
- Supervisors see all reports in their areas
- Admins see all reports

---

### 4. Shop Module

#### Products Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/shop/products` | List products | ❌ No |
| GET | `/api/shop/products/{id}` | Get product | ❌ No |

#### Cart Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| POST | `/api/shop/cart/add` | Add to cart | ✅ Yes | `client\|admin\|supervisor\|area_manager` |
| GET | `/api/shop/cart` | View cart | ✅ Yes | `client\|admin\|supervisor\|area_manager` |
| DELETE | `/api/shop/cart/{id}` | Remove item | ✅ Yes | `client\|admin\|supervisor\|area_manager` |

#### Orders Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| POST | `/api/shop/checkout` | Checkout | ✅ Yes | `client\|admin\|supervisor\|area_manager` |
| GET | `/api/shop/orders` | List orders | ✅ Yes | `client\|admin\|supervisor\|area_manager` |
| GET | `/api/shop/orders/{id}` | Get order | ✅ Yes | `client\|admin\|supervisor\|area_manager` |
| POST | `/api/shop/orders/{id}/mark-paid` | Mark paid | ✅ Yes | `client\|admin` |

#### Add to Cart

**Request:**
```json
POST /api/shop/cart/add
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 2
}
```

**Response (201):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "user_id": 1,
    "product_id": 1,
    "quantity": 2,
    "product": {
      "id": 1,
      "name": "Fertilizer",
      "price": 50.00
    }
  }
}
```

---

### 5. Tips & Notifications Module

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/tips` | List tips | ✅ Yes | `client\|admin\|supervisor\|area_manager\|hr` |
| GET | `/api/tips/{id}` | Get tip | ✅ Yes | `client\|admin\|supervisor\|area_manager\|hr` |
| GET | `/api/notifications` | List notifications | ✅ Yes | `client\|admin\|supervisor\|area_manager\|hr` |
| POST | `/api/notifications/{id}/mark-read` | Mark read | ✅ Yes | `client\|admin\|supervisor\|area_manager\|hr` |

#### Get Tips

**Request:**
```bash
GET /api/tips
Authorization: Bearer {token}
```

**Response:**
```json
{
  "status": true,
  "data": [
    {
      "id": 1,
      "title": "Winter Tree Care",
      "content": "During winter, reduce watering frequency...",
      "status": "published",
      "created_at": "2025-01-01T00:00:00.000000Z"
    }
  ]
}
```

---

### 6. Complaints Module

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/complaints` | List complaints | ✅ Yes | All authenticated |
| POST | `/api/complaints` | Create complaint | ✅ Yes | All authenticated |
| GET | `/api/complaints/{id}` | Get complaint | ✅ Yes | Owner or admin |
| PUT | `/api/complaints/{id}` | Update complaint | ✅ Yes | Admin/supervisor/area_manager |
| DELETE | `/api/complaints/{id}` | Delete complaint | ✅ Yes | Admin only |

#### Create Complaint

**Request:**
```json
POST /api/complaints
Authorization: Bearer {client_token}
Content-Type: application/json

{
  "visit_id": 1,
  "notes": "Technician did not complete all tasks"
}
```

**Response (201):**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "visit_id": 1,
    "client_id": 1,
    "status": "open",
    "notes": "Technician did not complete all tasks",
    "visit": {...},
    "client": {...}
  }
}
```

---

### 7. Categories Module

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/categories` | List categories | ✅ Yes | All authenticated |
| POST | `/api/categories` | Create category | ✅ Yes | All authenticated |
| GET | `/api/categories/{id}` | Get category | ✅ Yes | All authenticated |
| PUT | `/api/categories/{id}` | Update category | ✅ Yes | All authenticated |
| DELETE | `/api/categories/{id}` | Delete category | ✅ Yes | All authenticated |

---

### 8. Technician Routes

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/tech/visits` | Assigned visits | ✅ Yes | `technician` |
| POST | `/api/tech/visits/{id}/accept` | Accept visit | ✅ Yes | `technician` |
| POST | `/api/tech/visits/{id}/start` | Start visit | ✅ Yes | `technician` |
| POST | `/api/tech/visits/{id}/complete` | Complete visit | ✅ Yes | `technician` |
| POST | `/api/tech/visits/{id}/photos` | Upload photo | ✅ Yes | `technician` |

**Note:** Also available under `/api/auth/tech/*` routes.

---

### 9. Supervisor Routes

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/supervisor/visits` | List visits | ✅ Yes | `supervisor` |
| GET | `/api/supervisor/visits/{id}` | Review visit | ✅ Yes | `supervisor` |
| POST | `/api/supervisor/visits/{id}/recommend` | Recommend products | ✅ Yes | `supervisor` |
| POST | `/api/supervisor/visits/{id}/finalize` | Finalize report | ✅ Yes | `supervisor` |
| POST | `/api/supervisor/visits/{id}/status` | Update status | ✅ Yes | `supervisor` |
| GET | `/api/supervisor/areas` | List areas | ✅ Yes | `supervisor` |
| GET | `/api/supervisor/complaints` | List complaints | ✅ Yes | `supervisor` |
| POST | `/api/supervisor/complaints/{id}/escalate` | Escalate complaint | ✅ Yes | `supervisor` |

**Note:** Also available under `/api/auth/supervisor/*` routes.

---

### 10. Admin Routes

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/admin/users` | List users | ✅ Yes | `admin` |
| POST | `/api/admin/users` | Create user | ✅ Yes | `admin` |
| GET | `/api/admin/users/{id}` | Get user | ✅ Yes | `admin` |
| PUT | `/api/admin/users/{id}` | Update user | ✅ Yes | `admin` |
| DELETE | `/api/admin/users/{id}` | Delete user | ✅ Yes | `admin` |
| GET | `/api/admin/roles` | List roles | ✅ Yes | `admin` |
| POST | `/api/admin/roles` | Create role | ✅ Yes | `admin` |
| GET | `/api/admin/hr/employees` | List employees | ✅ Yes | `admin` |
| POST | `/api/admin/hr/employees` | Create employee | ✅ Yes | `admin` |

---

### 11. Area Manager Routes

#### Endpoints

| Method | Endpoint | Description | Auth Required | Role Required |
|--------|----------|-------------|---------------|---------------|
| GET | `/api/areas` | List areas | ✅ Yes | `area_manager` |
| POST | `/api/areas` | Create area | ✅ Yes | `area_manager` |
| GET | `/api/areas/{id}` | Get area | ✅ Yes | `area_manager` |
| PUT | `/api/areas/{id}` | Update area | ✅ Yes | `area_manager` |
| DELETE | `/api/areas/{id}` | Delete area | ✅ Yes | `area_manager` |

---

## D. Testing Order

### Recommended Testing Sequence

#### 1. User Auth
1. ✅ Test `/api/auth/register` - Create test users for each role
2. ✅ Test `/api/auth/login` - Login with each user
3. ✅ Verify `role` field in responses
4. ✅ Test `/api/auth/profile` - Get user profile
5. ✅ Test `/api/auth/logout` - Logout and verify token invalidated

#### 2. Roles & Permissions
1. ✅ Test role-based access (try accessing admin routes as client - should get 403)
2. ✅ Test resource ownership (client can only see own subscriptions)
3. ✅ Verify middleware protection on all routes

#### 3. Master Data
1. ✅ Test `/api/subscriptions/plans` - Get available plans (public)
2. ✅ Test `/api/shop/products` - Browse products (public)
3. ✅ Test `/api/categories` - List categories (if needed)

#### 4. Products
1. ✅ Test `/api/shop/products` - List all products
2. ✅ Test `/api/shop/products/{id}` - Get product details
3. ✅ Test product filtering, search, pagination

#### 5. Subscriptions (Client Flow)
1. ✅ Test `/api/subscriptions` POST - Create subscription as client
2. ✅ Test `/api/subscriptions` GET - List client's subscriptions
3. ✅ Test `/api/subscriptions/{id}` GET - Get subscription details
4. ✅ Verify visits are auto-generated after subscription creation
5. ✅ Test `/api/subscriptions/{id}/mark-paid` - Mark subscription as paid

#### 6. Visits
1. ✅ Test `/api/visits` GET - List visits (as client, see own visits)
2. ✅ Test `/api/visits/{id}` GET - Get visit details with photos
3. ✅ Test technician routes - Accept, start, complete visit
4. ✅ Test photo upload - Before/After photos

#### 7. Reports
1. ✅ Test supervisor routes - Review visit, recommend products
2. ✅ Test `/api/reports` GET - List reports (as client)
3. ✅ Test `/api/reports/{id}` GET - Get report details
4. ✅ Verify client receives finalized reports

#### 8. Shop & Orders
1. ✅ Test `/api/shop/cart/add` - Add product to cart
2. ✅ Test `/api/shop/cart` GET - View cart
3. ✅ Test `/api/shop/checkout` - Create order
4. ✅ Test `/api/shop/orders` GET - List orders
5. ✅ Test `/api/shop/orders/{id}` GET - Get order details

#### 9. Tips & Notifications
1. ✅ Test `/api/tips` GET - List tips
2. ✅ Test `/api/notifications` GET - List notifications
3. ✅ Test `/api/notifications/{id}/mark-read` - Mark notification as read

#### 10. Complaints
1. ✅ Test `/api/complaints` POST - Create complaint (as client)
2. ✅ Test `/api/complaints` GET - List complaints
3. ✅ Test supervisor/admin routes - Update complaint status

#### 11. Admin Functions
1. ✅ Test `/api/admin/users` - User management
2. ✅ Test `/api/admin/roles` - Role management
3. ✅ Test `/api/admin/hr/employees` - Employee management

---

## Error Response Format

All errors follow this consistent format:

```json
{
  "status": false,
  "message": "Error message here",
  "errors": {
    // Only present for validation errors
    "field_name": ["Error message"]
  }
}
```

**Status Codes:**
- `200` - Success
- `201` - Created
- `401` - Unauthenticated
- `403` - Forbidden (unauthorized)
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Testing Tools

### Recommended Tools:
1. **Postman** - GUI tool for API testing
2. **cURL** - Command-line tool
3. **Insomnia** - Alternative to Postman
4. **Thunder Client** - VS Code extension
5. **REST Client** - VS Code extension

### Postman Collection:
A Postman collection is available at: `postman/tandil-backend.postman_collection.json`

---

## Notes

1. **All API responses are JSON** - No HTML error pages
2. **All errors include `status: false`** - Easy to check success/failure
3. **Role field is included** in auth responses - No need for separate call
4. **Tokens don't expire** - But should be stored securely
5. **Public endpoints** don't require authentication
6. **Protected endpoints** require `Authorization: Bearer {token}` header

---

**Last Updated:** 2025-01-13
**Version:** 1.0

