# React Native Admin Dashboard – API Reference

All dashboard endpoints require **admin** role and **Bearer token** (except login).

### Required headers (all API requests)

| Header | Value |
|--------|--------|
| `Authorization` | `Bearer {token}` (from `POST /api/auth/login`) |
| `Accept` | `application/json` |
| `Content-Type` | `application/json` (for POST/PUT with a body) |

### Base URL

- Replace `{{base_url}}` with your API root (e.g. `https://your-domain.com` or `http://127.0.0.1:8000`).
- All paths below are relative to that (e.g. `GET {{base_url}}/api/admin/dashboard/statistics`).

### Standard error responses

All API errors return JSON with `"success": false` and a `"message"` (and optionally `"errors"` for validation):

| Status | Meaning | Example body |
|--------|--------|--------------|
| **401** | Not authenticated (missing or invalid token) | `{"success": false, "message": "Unauthenticated."}` |
| **403** | Authenticated but not allowed (e.g. not admin) | `{"success": false, "message": "Unauthorized access. You do not have the required role."}` |
| **404** | Resource or route not found | `{"success": false, "message": "The route ... could not be found."}` |
| **422** | Validation failed | `{"success": false, "message": "...", "errors": {"field": ["..."]}}` |
| **500** | Server error | `{"success": false, "message": "An error occurred."}` |

---

## 1. Recent Activities (for "Recent Activities" list)

**GET** `{{base_url}}/api/admin/dashboard/recent-activities`

**Query (optional):**
- `limit` – max activities to return (default: 20)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "type": "subscription",
      "icon_type": "success",
      "description": "New 12-month subscription by Palm Grove Estate",
      "timestamp": "5 min ago",
      "created_at": "2024-01-15T10:30:00.000000Z",
      "related_id": 1,
      "related_type": "subscription"
    },
    {
      "type": "customer",
      "icon_type": "user_add",
      "description": "New customer registered - Mohammed Ali Farm",
      "timestamp": "1 hour ago",
      "created_at": "2024-01-15T09:30:00.000000Z",
      "related_id": 5,
      "related_type": "user"
    },
    {
      "type": "inventory",
      "icon_type": "warning",
      "description": "Low inventory alert: Organic Fertilizer",
      "timestamp": "2 hours ago",
      "created_at": "2024-01-15T08:00:00.000000Z",
      "related_id": 3,
      "related_type": "product",
      "stock": 5
    },
    {
      "type": "inventory",
      "icon_type": "error",
      "description": "Out of stock: Product Name",
      "timestamp": "3 hours ago",
      "created_at": "2024-01-15T07:00:00.000000Z",
      "related_id": 4,
      "related_type": "product",
      "stock": 0
    },
    {
      "type": "order",
      "icon_type": "order",
      "description": "New order #12 by John Doe",
      "timestamp": "4 hours ago",
      "created_at": "2024-01-15T06:00:00.000000Z",
      "related_id": 12,
      "related_type": "order",
      "amount": 150.50,
      "status": "pending"
    },
  ],
  "total": 5
}
```

### Activity types and icons (for your UI)

| `type`       | `icon_type` | Suggested UI icon / color      |
|-------------|-------------|---------------------------------|
| subscription| success     | Green checkmark                 |
| customer    | user_add    | Green person + plus             |
| inventory   | warning     | Yellow/orange exclamation       |
| inventory   | error       | Red exclamation (out of stock) |
| order       | order       | Cart / order icon               |

### Fields you can use on the frontend

- **description** – Main text (e.g. "New 12-month subscription by Palm Grove Estate").
- **timestamp** – Human-readable time (e.g. "5 min ago"); use as-is or parse **created_at** for sorting/formatting.
- **created_at** – ISO 8601 for sorting or custom time formatting.
- **type** / **icon_type** – To pick icon and color.
- **related_id** + **related_type** – For deep links (e.g. open subscription, user, product, order).

### How to show real Recent Activities in your React Native app

The API returns **only real data** from your database (no dummy/seed data). To display it:

1. **Log in as admin**  
   Call `POST {{base_url}}/api/auth/login` with admin email/password (e.g. `admin@tandil.com` / `password123`).  
   Store the returned `token` (e.g. in AsyncStorage or your auth context).

2. **Call the Recent Activities API**  
   - **URL:** `GET {{base_url}}/api/admin/dashboard/recent-activities`  
   - **Headers:**  
     - `Authorization: Bearer {token}`  
     - `Accept: application/json`  
   - **Query (optional):** `?limit=20` (default 20)

3. **Handle the response**  
   - On success you get `{ "success": true, "data": [...], "total": N }`.  
   - Use `data` as the list of activities.  
   - If there is no real activity yet, `data` will be `[]` and `total` will be `0` — show an empty state (e.g. “No recent activities”).

4. **Render the list**  
   - Map over `data` and for each item show:  
     - **description** (main text)  
     - **timestamp** (e.g. “5 min ago”)  
     - Icon/color based on **type** and **icon_type** (see table above).  
   - Optionally use **related_id** and **related_type** to open the related screen (subscription, user, product, order, visit) when the user taps an item.

**Example (pseudo-code):**

```javascript
// After login: token = response.token

const response = await fetch(
  `${BASE_URL}/api/admin/dashboard/recent-activities?limit=20`,
  {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
    },
  }
);
const json = await response.json();

if (json.success) {
  const activities = json.data;  // real data only
  const total = json.total;
  // Render FlatList / SectionList with activities; show empty state if total === 0
}
```

---

## 2. Quick Overview (for "Pending Reports" and "New Orders" cards)

**GET** `{{base_url}}/api/admin/dashboard/quick-overview`

Use this for the summary cards (e.g. Pending Reports, New Orders) with count and optional growth.

**Response:**
```json
{
  "success": true,
  "data": {
    "pending_reports": {
      "count": 12,
      "growth": "+5",
      "label": "Pending Reports"
    },
    "new_orders": {
      "count": 34,
      "growth": "+10",
      "label": "New Orders"
    },
    "support_tickets": {
      "count": 3,
      "growth": "0",
      "label": "Support Tickets"
    },
    "new_customers": {
      "count": 2,
      "growth": "+100",
      "label": "New Customers"
    },
    "new_subscriptions": {
      "count": 1,
      "growth": "0",
      "label": "New Subscriptions"
    },
    "revenue_today": {
      "count": 1250.00,
      "growth": "+15",
      "label": "Revenue Today"
    },
    "pending_visits": {
      "count": 8,
      "growth": "-2",
      "label": "Pending Visits"
    }
  }
}
```

**React Native usage:**
- **Pending Reports card:** `data.pending_reports.count`, `data.pending_reports.label`, optional `data.pending_reports.growth`.
- **New Orders card:** `data.new_orders.count`, `data.new_orders.label`, optional `data.new_orders.growth`.
- Use `growth` as string (e.g. "+5", "-2") for badge or subtitle.

---

## 3. Full Statistics (optional, for detailed dashboards)

**GET** `{{base_url}}/api/admin/dashboard/statistics`

Returns detailed stats (customers, technicians, employees, subscriptions, orders, revenue, etc.) with daily/weekly/monthly/yearly counts and growth. Use if you need more than the quick-overview cards.

---

## 4. Admin profile (for header: name, role, ID)

**GET** `{{base_url}}/api/admin/dashboard/profile`

Use for the top section: "Good afternoon!", name, role, ID (e.g. "ADMIN-5001"). Response shape is in the backend; typically includes user name, role, and a formatted ID.

---

## Summary: what to call from React Native

| Screen section       | API endpoint                                      | Use |
|----------------------|----------------------------------------------------|-----|
| Greeting + name/role | `GET /api/admin/dashboard/profile`                 | User name, role, ID |
| Pending Reports card | `GET /api/admin/dashboard/quick-overview`          | `data.pending_reports` |
| New Orders card      | `GET /api/admin/dashboard/quick-overview`          | `data.new_orders` |
| Recent Activities    | `GET /api/admin/dashboard/recent-activities`       | List of activities with `description`, `timestamp`, `icon_type` |
| View All activities  | Same + optional `?limit=50`                        | More items |
| Top Selling Products | Use statistics or shop/orders APIs as needed       | Depends on backend exposure |

All requests: **admin** Bearer token + **Accept: application/json**.

---

## 5. Tips API (for "Send Tips" and "Recent Tips" screens)

Used by **client, admin, supervisor, area_manager, hr** (Bearer token).

### List published tips (Recent Tips list)
**GET** `{{base_url}}/api/tips`

**Response:**
```json
{
  "success": true,
  "message": "Tips retrieved successfully.",
  "data": [
    {
      "id": 1,
      "title": "Water early morning to reduce evaporation",
      "content": "Water your plants early in the morning...",
      "type": "general",
      "status": "published",
      "language": "en",
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ]
}
```

### Get single tip
**GET** `{{base_url}}/api/tips/{id}`

### Create/Send tip (admin or supervisor only – "Send Tip" button)
**POST** `{{base_url}}/api/tips`  
**Body (JSON):**
- `title` (required, string, max 255)
- `content` (required, string)
- `type` (optional: weekly|monthly|seasonal|general, default: general)
- `status` (optional: draft|published|archived, default: published)
- `language` (optional: en|ar|ur, default: en)

**Response (201):**
```json
{
  "success": true,
  "message": "Tip sent successfully.",
  "data": {
    "id": 10,
    "title": "...",
    "content": "...",
    "type": "general",
    "status": "published",
    "language": "en",
    "created_by": 1,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Non-admin/supervisor receive **403** with message: "Only admins or supervisors can create tips."

---

## 6. Admin Settings (React Native Settings screen)

Base path: `/api/admin/settings`  
**Auth:** Bearer token, **role: admin**

Use these endpoints to power the Settings screen: system toggles (Push Notifications, Auto-Assign Tasks, Maintenance Mode), Theme, Language & Region, Payment Settings, Privacy/Terms, Export Data, and Debug Logs.

**Admin profile (Settings header):** Use **GET** `{{base_url}}/api/admin/dashboard/profile` for the current admin user (name, email, formatted ID, role). See Dashboard API section.

### Get all settings (for initial load)
**GET** `{{base_url}}/api/admin/settings`

**Response:**
```json
{
  "success": true,
  "data": {
    "system": {
      "push_notifications_enabled": true,
      "auto_assign_tasks": false,
      "maintenance_mode": false
    },
    "app_config": {
      "theme": "system",
      "language": "en",
      "region": ""
    },
    "payment": {
      "payment_gateway": "stripe",
      "api_key_set": true,
      "api_secret_set": true
    },
    "legal": {
      "privacy_policy_url": "https://...",
      "terms_of_service_url": "https://..."
    }
  }
}
```

### System settings (toggles)
- **GET** `{{base_url}}/api/admin/settings/system` – current values
- **PUT** `{{base_url}}/api/admin/settings/system` – update toggles  
  **Body (JSON):** `push_notifications_enabled` (boolean), `auto_assign_tasks` (boolean), `maintenance_mode` (boolean). All optional; only sent keys are updated.

### Theme settings
- **GET** `{{base_url}}/api/admin/settings/theme` – returns `current` and `available` (system, light, dark)
- **PUT** `{{base_url}}/api/admin/settings/theme`  
  **Body (JSON):** `theme` (required: "system" | "light" | "dark")

### Language & Region
- **GET** `{{base_url}}/api/admin/settings/language` – returns `current_language`, `current_region`, `available` (e.g. en, ar)
- **PUT** `{{base_url}}/api/admin/settings/language`  
  **Body (JSON):** `language` (required, string), `region` (optional, string)

### Payment settings
- **GET** `{{base_url}}/api/admin/settings/payment` – gateway and whether keys are set (no secrets)
- **PUT** `{{base_url}}/api/admin/settings/payment`  
  **Body (JSON):** `payment_gateway` (required: stripe|paymob|ccavenue|tap), `api_key` (required), `api_secret` (required)

### Privacy Policy / Terms of Service
**GET** `{{base_url}}/api/admin/settings/legal?type=privacy` or `?type=terms`

**Response:**
```json
{
  "success": true,
  "data": {
    "type": "privacy",
    "url": "https://...",
    "content": "..."
  }
}
```

### Export data
**POST** `{{base_url}}/api/admin/settings/export-data`  
**Body (JSON):** `format` (optional: "json" | "csv", default: "json")

**Response (202):**
```json
{
  "success": true,
  "message": "Export requested.",
  "data": {
    "export_id": "export-...",
    "format": "json",
    "status": "pending"
  }
}
```

### Debug logs (Developer Options)
**GET** `{{base_url}}/api/admin/settings/debug-logs?lines=100`  
**Query:** `lines` (optional, 10–500, default: 100)

**Response:** `data.log` contains recent Laravel log lines.
