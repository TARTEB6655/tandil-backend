# Notification APIs - Complete Documentation

This document lists all notification APIs available in the Tandil Backend system.

## Overview

The notification system provides endpoints for managing user notifications. Notifications are automatically created when various events occur in the system (e.g., order creation, visit updates, complaint status changes, etc.).

---

## API Endpoints

### 1. **List Notifications (Main Route)**
- **Endpoint:** `GET /api/notifications`
- **Method:** GET
- **Authentication:** Required (Bearer Token)
- **Authorization:** `auth:sanctum` + `role:client|admin|supervisor|area_manager|hr`
- **Controller:** `App\Http\Controllers\Notification\NotificationController@index`
- **Purpose:** Retrieve paginated list of all notifications for the authenticated user, including unread count.
- **Response Format:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully.",
  "data": {
    "notifications": {
      "data": [...],
      "current_page": 1,
      "per_page": 20,
      "total": 50
    },
    "unread_count": 5
  }
}
```
- **Use Case:** Display notifications list in dashboard, notification center, or mobile app.

---

### 2. **Mark Notification as Read (Main Route)**
- **Endpoint:** `POST /api/notifications/{id}/mark-read`
- **Method:** POST
- **Authentication:** Required (Bearer Token)
- **Authorization:** `auth:sanctum` + `role:client|admin|supervisor|area_manager|hr`
- **Controller:** `App\Http\Controllers\Notification\NotificationController@markAsRead`
- **Parameters:**
  - `id` (path parameter): Notification UUID from the list endpoint
- **Purpose:** Mark a specific notification as read.
- **Response Format:**
```json
{
  "success": true,
  "message": "Notification marked as read."
}
```
- **Error Response (404):**
```json
{
  "success": false,
  "message": "Notification not found. Make sure you are using the correct notification UUID from GET /api/notifications response."
}
```
- **Use Case:** When user clicks on a notification or views it, mark it as read.

---

### 3. **Mark All Notifications as Read (Main Route)**
- **Endpoint:** `POST /api/notifications/mark-all-read`
- **Method:** POST
- **Authentication:** Required (Bearer Token)
- **Authorization:** `auth:sanctum` + `role:client|admin|supervisor|area_manager|hr`
- **Controller:** `App\Http\Controllers\Notification\NotificationController@markAllAsRead`
- **Purpose:** Mark all unread notifications as read for the authenticated user.
- **Response Format:**
```json
{
  "success": true,
  "message": "All notifications marked as read."
}
```
- **Use Case:** "Mark all as read" button in notification center or dashboard.

---

### 4. **List User Notifications (Alternative Route)**
- **Endpoint:** `GET /api/user/notifications`
- **Method:** GET
- **Authentication:** Required (Bearer Token)
- **Authorization:** `auth:sanctum` (all authenticated users)
- **Controller:** `App\Http\Controllers\Api\UserController@getNotifications`
- **Purpose:** Alternative route to retrieve paginated list of notifications. Same functionality as `/api/notifications` but available to all authenticated users (not role-restricted).
- **Response Format:**
```json
{
  "success": true,
  "message": "Notifications retrieved successfully.",
  "data": {
    "data": [...],
    "current_page": 1,
    "per_page": 20,
    "total": 50
  }
}
```
- **Use Case:** Frontend-compatible route for user profile/notifications section.

---

### 5. **Mark Notification as Read (Alternative Route)**
- **Endpoint:** `POST /api/user/notifications/{id}/read`
- **Method:** POST
- **Authentication:** Required (Bearer Token)
- **Authorization:** `auth:sanctum` (all authenticated users)
- **Controller:** `App\Http\Controllers\Api\UserController@markNotificationAsRead`
- **Parameters:**
  - `id` (path parameter): Notification UUID
- **Purpose:** Alternative route to mark a specific notification as read. Available to all authenticated users.
- **Response Format:**
```json
{
  "success": true,
  "message": "Notification marked as read."
}
```
- **Error Response (404):**
```json
{
  "success": false,
  "message": "Notification not found"
}
```
- **Use Case:** User profile notifications section, mobile app notifications.

---

### 6. **Mark All Notifications as Read (Alternative Route)**
- **Endpoint:** `POST /api/user/notifications/read-all`
- **Method:** POST
- **Authentication:** Required (Bearer Token)
- **Authorization:** `auth:sanctum` (all authenticated users)
- **Controller:** `App\Http\Controllers\Api\UserController@markAllNotificationsAsRead`
- **Purpose:** Alternative route to mark all notifications as read. Available to all authenticated users.
- **Response Format:**
```json
{
  "success": true,
  "message": "All notifications marked as read."
}
```
- **Use Case:** "Mark all as read" in user profile or mobile app.

---

## Summary Table

| # | Endpoint | Method | Auth | Roles | Purpose |
|---|----------|--------|------|-------|---------|
| 1 | `/api/notifications` | GET | ✅ | client, admin, supervisor, area_manager, hr | List all notifications with unread count |
| 2 | `/api/notifications/{id}/mark-read` | POST | ✅ | client, admin, supervisor, area_manager, hr | Mark single notification as read |
| 3 | `/api/notifications/mark-all-read` | POST | ✅ | client, admin, supervisor, area_manager, hr | Mark all notifications as read |
| 4 | `/api/user/notifications` | GET | ✅ | All authenticated users | List notifications (alternative route) |
| 5 | `/api/user/notifications/{id}/read` | POST | ✅ | All authenticated users | Mark single notification as read (alternative) |
| 6 | `/api/user/notifications/read-all` | POST | ✅ | All authenticated users | Mark all notifications as read (alternative) |

---

## Key Differences Between Routes

### Main Routes (`/api/notifications/*`)
- **Role-restricted:** Only available to specific roles (client, admin, supervisor, area_manager, hr)
- **Includes unread count:** Returns `unread_count` in response
- **More detailed error messages:** Provides specific guidance on notification ID format

### Alternative Routes (`/api/user/notifications/*`)
- **All authenticated users:** Available to any authenticated user regardless of role
- **Frontend-compatible:** Designed for user profile/notifications sections
- **Simpler response:** Standard pagination format

---

## Notification Types

Notifications are automatically created for various events:

1. **Order Notifications:**
   - New order received (admin)
   - Order payment confirmed (client)
   - Order cancelled (client, admin)

2. **Visit Notifications:**
   - New visit scheduled (client)
   - Visit accepted (client, supervisor)
   - Visit started (client)
   - Visit completed (client, technician, supervisor)
   - Visit approved/rejected (technician)

3. **Complaint Notifications:**
   - New complaint received (admin, supervisor)
   - Complaint status updated (client)
   - Complaint escalated (admin)
   - Complaint resolved (admin, client)

4. **Subscription Notifications:**
   - Subscription created (client)
   - Subscription payment confirmed (client)

5. **User Management Notifications:**
   - New user registered (admin)

---

## Authentication

All endpoints require:
- **Bearer Token** in Authorization header
- Format: `Authorization: Bearer {token}`
- Token obtained from `/api/auth/login` endpoint

---

## Response Format

All endpoints follow a standard JSON response format:

**Success Response:**
```json
{
  "success": true,
  "message": "Operation message",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... } // Optional, for validation errors
}
```

---

## Notification Data Structure

Each notification object contains:
```json
{
  "id": "uuid-string",
  "type": "App\\Notifications\\AdminNotification",
  "notifiable_type": "App\\Models\\User",
  "notifiable_id": 1,
  "data": {
    "message": "Notification message",
    "visit_id": 123, // Optional
    "order_id": 456, // Optional
    "subscription_id": 789 // Optional
  },
  "read_at": null, // or timestamp if read
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

---

## Usage Examples

### Example 1: Get All Notifications
```bash
curl -X GET "http://localhost:8000/api/notifications" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Example 2: Mark Notification as Read
```bash
curl -X POST "http://localhost:8000/api/notifications/{notification-id}/mark-read" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Example 3: Mark All as Read
```bash
curl -X POST "http://localhost:8000/api/notifications/mark-all-read" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

---

## Postman Collection

All notification APIs are documented in the Postman collection:
- **File:** `postman/tandil_backend.json`
- **Section:** "Other modules - Complaints, Tips, Notifications, Categories, Areas, Payment, Services, User Profile"
- **Endpoints:**
  1. Notifications - List
  2. Notifications - Mark as Read
  3. Notifications - Mark All as Read
  4. User Notifications - List
  5. User Notifications - Mark as Read
  6. User Notifications - Mark All as Read

---

## Notes

1. **Notification IDs:** Use UUID format from the list endpoint response
2. **Pagination:** Default 20 items per page, can be customized
3. **Read Status:** `read_at` is `null` for unread notifications
4. **Real-time:** Notifications are stored in database, can be extended with WebSockets/Pusher for real-time updates
5. **Email Notifications:** Notifications can also be sent via email (configured in notification classes)

---

## Related Documentation

- `docs/NOTIFICATIONS_SETUP.md` - Notification system setup guide
- `docs/REACT_NATIVE_NOTIFICATIONS.md` - React Native integration guide

