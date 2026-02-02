# APIs to Add on React Native Frontend

Use this as a checklist for which backend APIs to call from your React Native app(s).

**Base URL:** `https://your-domain.com` or `http://127.0.0.1:8000` (e.g. `{{base_url}}/api/...`)

**Headers for authenticated requests:**
- `Authorization: Bearer {token}`
- `Accept: application/json`
- `Content-Type: application/json` (for POST/PUT with body)

---

## A. React Native **Admin** App

Login: **POST** `/api/auth/login` (email, password) → save `data.token`.  
Then use that token for all requests below.

| Feature | Method | Endpoint | Use |
|--------|--------|----------|-----|
| **Login** | POST | `/api/auth/login` | Get token (no auth header) |
| **Logout** | POST | `/api/auth/logout` | Clear session |
| **Dashboard – Statistics** | GET | `/api/admin/dashboard/statistics` | Stats cards (users, orders, etc.) |
| **Dashboard – Recent Activities** | GET | `/api/admin/dashboard/recent-activities` | Activity list (optional: `?limit=20`) |
| **Dashboard – Quick Overview** | GET | `/api/admin/dashboard/quick-overview` | Overview data |
| **Dashboard – Profile** | GET | `/api/admin/dashboard/profile` | Current admin (name, email, role) |
| **Settings – All** | GET | `/api/admin/settings` | Load settings screen |
| **Settings – Theme** | GET | `/api/admin/settings/theme` | Get theme |
| **Settings – Theme** | PUT | `/api/admin/settings/theme` | Save theme (body: `{"theme":"dark"}` etc.) |
| **Settings – System** | GET/PUT | `/api/admin/settings/system` | Toggles (notifications, maintenance, etc.) |
| **Settings – Language** | GET/PUT | `/api/admin/settings/language` | Language & region |
| **Settings – Payment** | GET/PUT | `/api/admin/settings/payment` | Payment gateway config |
| **Settings – Legal** | GET | `/api/admin/settings/legal?type=privacy` or `?type=terms` | Privacy/Terms |
| **Settings – Export** | POST | `/api/admin/settings/export-data` | Request export (body: `{"format":"json"}`) |
| **Settings – Debug logs** | GET | `/api/admin/settings/debug-logs?lines=100` | Developer logs |
| **Users – List** | GET | `/api/admin/users` | User list (optional: `?search=&category=&status=&page=&per_page=`) |
| **Users – Stats** | GET | `/api/admin/users/statistics` | User statistics |
| **Users – Create** | POST | `/api/admin/users` | Add user |
| **Users – Show/Update/Delete** | GET/PUT/DELETE | `/api/admin/users/{id}` | Single user |
| **Products – List** | GET | `/api/admin/products` | Product list (optional: `?search=&category_id=&filter=`) |
| **Products – Create** | POST | `/api/admin/products` | Add product |
| **Products – Show/Update/Delete** | GET/PUT/DELETE | `/api/admin/products/{id}` | Single product |
| **Products – Toggle status** | POST | `/api/admin/products/{id}/toggle-status` | Toggle product status |
| **Products – Bulk** | POST | `/api/admin/products/bulk-delete`, `bulk-update-status`, `bulk-update-stock` | Bulk actions |
| **Reports – List/Stats** | GET | `/api/admin/reports`, `/api/admin/reports/statistics` | Reports list & stats |
| **Reports – Generate/Download** | POST/GET | `/api/admin/reports/generate`, `/api/admin/reports/{id}/download` | Generate & download |
| **Tips – List** | GET | `/api/tips` | Tips list |
| **Tips – Create** | POST | `/api/tips` | Send tip (admin/supervisor) |
| **Notifications** | GET | `/api/notifications` | Notifications list |
| **Notifications – Mark read** | POST | `/api/notifications/{id}/mark-read`, `/api/notifications/mark-all-read` | Mark read |

---

## B. React Native **Customer / Client** App

**Register:** **POST** `/api/auth/register`  
**Login:** **POST** `/api/auth/login`  
Save `data.token` (or `data.token` from login response) and send it in `Authorization: Bearer {token}` for protected routes.

| Feature | Method | Endpoint | Auth | Use |
|--------|--------|----------|------|-----|
| **Register** | POST | `/api/auth/register` | No | Sign up |
| **Login** | POST | `/api/auth/login` | No | Get token |
| **Logout** | POST | `/api/auth/logout` | Yes | Log out |
| **Profile** | GET/PUT | `/api/auth/profile` or `/api/user/profile` | Yes | View/update profile |
| **Health** | GET | `/api/health` | No | Check API is up |
| **Plans (subscription)** | GET | `/api/subscriptions/plans` | No | List plans before login |
| **Subscriptions – List/Create** | GET/POST | `/api/subscriptions` | Yes (client/admin) | My subscriptions, create new |
| **Subscriptions – Show** | GET | `/api/subscriptions/{id}` | Yes | Subscription detail |
| **Products – List** | GET | `/api/shop/products` | No | Catalog (optional: `?per_page=12&search=&category_id=&sort_by=&sort_dir=`) |
| **Products – Detail** | GET | `/api/shop/products/{id}` | No | Product detail page |
| **Products – Categories** | GET | `/api/shop/products/categories` | No | Category list / filters |
| **Products – By category** | GET | `/api/shop/products/category/{id}` | No | Products in category |
| **Cart – Add** | POST | `/api/shop/cart/add` | Yes | Add to cart (body: `product_id`, `quantity`) |
| **Cart – View** | GET | `/api/shop/cart` | Yes | Cart contents |
| **Cart – Remove** | DELETE | `/api/shop/cart/{id}` | Yes | Remove item |
| **Checkout** | POST | `/api/shop/checkout` | Yes | Create order (body: `items`, `total_amount`) |
| **Orders – List** | GET | `/api/shop/orders` | Yes | My orders |
| **Orders – Detail** | GET | `/api/shop/orders/{id}` | Yes | Order detail |
| **Banners** | GET | `/api/banners` | No | Home screen banners |
| **Tips – List** | GET | `/api/tips` | Yes | Tips list |
| **Notifications** | GET | `/api/notifications` | Yes | Notifications |
| **Notifications – Mark read** | POST | `/api/notifications/{id}/mark-read`, `mark-all-read` | Yes | Mark read |
| **Complaints** | GET/POST/PUT/DELETE | `/api/auth/complaints` (list: GET, create: POST, show: GET `/{id}`, update: PUT `/{id}`, delete: DELETE `/{id}`) | Yes | Complaints list & create |
| **Visits** | GET | `/api/visits` | Yes | My visits (if client has visits) |

---

## C. Quick copy-paste base URLs

- **Admin base:** `{{base_url}}/api/admin/...`
- **Auth base:** `{{base_url}}/api/auth/...`
- **Shop (public):** `{{base_url}}/api/shop/products`, `.../api/shop/products/categories`, etc.
- **User profile:** `{{base_url}}/api/user/profile` or `{{base_url}}/api/auth/profile`

Use the tables above to add the exact endpoints you need in your React Native app (e.g. in an API service file or env-based config).
