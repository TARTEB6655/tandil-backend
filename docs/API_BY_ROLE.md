# API by Role – Kon Role Ko Kaunsi API

Is document se aap dekh sakte ho: **kis role ke liye kaunsi API use karni hai** aur **sahi data ja raha hai ya nahi** (mismatch check).

---

## 1. Quick Reference – Role → API Prefix

| Role | API Prefix (base) | Kaunsi app/screen use kare |
|------|-------------------|----------------------------|
| **Client** | `/api/client/*`, `/api/user/*`, `/api/support/*`, `/api/shop/*`, `/api/visits/*` (own), `/api/subscriptions/*` | Customer app – home, profile, orders, subscriptions, help |
| **Technician** | `/api/technician/*`, `/api/support/*`, `/api/user/*` | Technician app – dashboard, tasks, jobs, leave, availability, reports |
| **Supervisor** | `/api/supervisor/*`, `/api/support/*`, `/api/user/*` | Supervisor app – dashboard, team, assignments, reports, leave |
| **Area Manager** | `/api/area-manager/*`, `/api/support/*`, `/api/user/*`, `/api/areas/*` | Area Manager app – dashboard, teams, reports, region |
| **HR** | `/api/hr/*`, `/api/support/*`, `/api/user/*` | HR app – dashboard, employees, leave requests (approve/reject) |
| **Admin** | `/api/admin/*`, `/api/admin/dashboard/*`, `/api/admin/users/*`, etc. | Admin app – users, roles, tips, support tickets, reports, settings |

**Auth:** Sab protected routes ke liye **Bearer token** (login se milega) use karo.  
**Base URL:** `https://your-domain.com/api` (ya `.env` me `APP_URL`)

---

## 2. Role-Wise API Detail (Kya Data Milega)

### Client
- **Dedicated:** `GET/PUT /api/client/settings/dashboard`, `.../settings/sections`, `.../memberships`
- **Data scope:** Sirf apna profile, apni subscriptions, apne visits, apne orders, apni support tickets
- **Shared:** `/api/user/profile`, `/api/user/notifications`, `/api/support/tickets`, `/api/shop/*`, `/api/visits` (sirf apne), `/api/subscriptions` (apni)

**Verify:** Client token se `/api/technician/dashboard` ya `/api/hr/employees` call karo → **403 Forbidden** aana chahiye (mismatch nahi).

---

### Technician
- **Dedicated prefix:** `/api/technician/*`
  - Dashboard: `GET /api/technician/dashboard`
  - Tasks/Jobs: `GET /api/technician/tasks`, `.../jobs`, `.../jobs/status-counts`
  - Leave: `GET/POST /api/technician/leave-requests`, `GET /api/technician/leave-request-types`
  - Availability: `GET/PUT /api/technician/availability`, `GET /api/technician/schedule`
  - Reports: `POST /api/technician/report/{visit_id}`, `GET /api/technician/field-notes`
  - Notifications/Alerts: `GET /api/technician/alerts`, `GET /api/technician/notifications`
- **Data scope:** Sirf apne assigned tasks/jobs, apni leave requests, apna availability, apne reports

**Verify:** Technician token se `/api/supervisor/team` ya `/api/hr/leave-requests` call karo → **403**. Sirf `/api/technician/*` aur shared routes (support, user) chalne chahiye.

---

### Supervisor
- **Dedicated prefix:** `/api/supervisor/*`
  - Dashboard: `GET /api/supervisor/dashboard/summary`, `.../kpis`, `.../alerts`
  - Team: `GET /api/supervisor/team`, `GET /api/supervisor/team/{id}`
  - Assignments: `GET /api/supervisor/assignments`, `POST /api/supervisor/assignments/{id}` (assign/reassign)
  - Reports: `GET /api/supervisor/reports`, `POST .../reports/{id}/accept|reject`
  - Leave: `GET/POST /api/supervisor/leave-requests`, `GET /api/supervisor/leave-request-types`
  - Alerts: `GET /api/supervisor/alerts`
- **Data scope:** Sirf apni areas ke visits, apni team (apne zones ke technicians), apne area ke reports/complaints

**Verify:** Supervisor A ke token se doosre supervisor B ke team ya areas ka data nahi aana chahiye (backend apne `supervisedAreaIds()` se filter karta hai).

---

### Area Manager
- **Dedicated prefix:** `/api/area-manager/*`
  - Dashboard: `GET /api/area-manager/dashboard/summary`, `.../alerts`
  - Teams: `GET /api/area-manager/teams`, `GET .../teams/{id}/members`, `.../teams/{id}/jobs`
  - Reports: `GET /api/area-manager/reports`, `POST .../reports/generate`
- **Data scope:** Sirf apne region ke supervisors/teams, apne region ke visits/reports

**Verify:** Area Manager token se `/api/admin/users` ya `/api/hr/employees` → **403**. Sirf area-manager aur shared routes.

---

### HR
- **Dedicated prefix:** `/api/hr/*`
  - Dashboard: `GET /api/hr/dashboard/summary`, `.../visit-assignments`
  - Employees: `GET/POST /api/hr/employees`, `GET/PUT /api/hr/employees/{id}`
  - Leave: `GET /api/hr/leave-requests`, `GET .../leave-requests/{id}`, `POST .../leave-requests/{id}/approve|reject`, `POST .../leave-requests` (create on behalf)
- **Data scope:** Sab employees (list/detail), sab leave requests (technician + supervisor dono), visit assignments – koi role filter nahi (HR sab dekhta hai)

**Verify:** HR token se `/api/technician/dashboard` → 403 (technician prefix). `/api/hr/leave-requests` me technician + supervisor dono ki leave requests aani chahiye.

---

### Admin
- **Dedicated prefix:** `/api/admin/*` (multiple sub-prefixes)
  - Dashboard: `GET /api/admin/dashboard/statistics`, `.../quick-overview`, `.../recent-activities`
  - Users: `GET/POST /api/admin/users`, `GET/PUT /api/admin/users/{id}`, etc.
  - Roles, Categories, Products, Tips, Support tickets, Reports, Settings, Banners, Packages, Orders export, etc.
- **Data scope:** Full system – sab users, sab data (admin only)

**Verify:** Admin token se koi bhi `/api/admin/*` route chalni chahiye. Kisi aur role ka token `/api/admin/*` pe → **403**.

---

## 3. Shared Routes (Kaun Kaun Use Kar Sakta Hai)

| Route Prefix | Allowed Roles | Use |
|--------------|----------------|-----|
| `/api/auth/*` | Login/register (public); logout, profile (authenticated) | Sab roles login karke token lete hain |
| `/api/user/*` | Sab authenticated | Profile, addresses, payment methods, **notifications** (unified list) |
| `/api/support/*` | client, technician, supervisor, area_manager, hr, admin | Help center, FAQs, **my tickets** (sirf apne), create/reply ticket |
| `/api/visits/*` | technician, supervisor, area_manager, client, admin (role-wise filter inside) | Visits list – har role ko sirf uske scope ki visits |
| `/api/reports/*` | client, technician, supervisor, area_manager, admin | Reports – scope controller me role ke hisaab se |
| `/api/shop/*` (cart, orders) | client, admin, supervisor, area_manager | Orders/cart – client apne, admin/supervisor/AM sab dekh sakte (depending on implementation) |

---

## 4. Data Mismatch Kaise Check Karo

### Step 1: Role-wise token lo
- Client login → client token  
- Technician login → technician token  
- Supervisor login → supervisor token  
- … same for HR, Area Manager, Admin  

### Step 2: Galat API call karo (403 expect karo)
- **Client token** → `GET /api/technician/dashboard` → **403**  
- **Technician token** → `GET /api/hr/leave-requests` → **403**  
- **Supervisor token** → `GET /api/admin/users` → **403**  

Agar 403 nahi aata aur data dikh jata hai to **mismatch hai** – backend me role check ya scope galat hai.

### Step 3: Sahi API call karo (200 + sahi scope)
- **Technician token** → `GET /api/technician/dashboard` → 200, sirf usi technician ka data  
- **Technician token** → `GET /api/technician/leave-requests` → sirf usi ki leave requests  
- **HR token** → `GET /api/hr/leave-requests` → sab ki (technician + supervisor) leave requests  
- **Supervisor token** → `GET /api/supervisor/team` → sirf us supervisor ke zones ke technicians  

### Step 4: Notifications / Alerts
- **Technician/Supervisor:** `GET /api/technician/alerts` ya `GET /api/supervisor/alerts` → database notifications (leave approved/rejected, admin message, etc.) – sirf us user ki.  
- **Client:** Notifications ab unified – `GET /api/user/notifications` (ya client-specific agar koi ho) – sirf us client ki.

---

## 5. Postman / Testing Tip

- **Environment:** `base_url` = `https://your-domain.com/api`, `token` = login se mila token  
- **Folder structure:** Role-wise folders banao (Client, Technician, Supervisor, HR, Area Manager, Admin)  
- Har folder me us role ki **sirf wohi** APIs rakho jo table me us role ke liye likhi hain  
- Ek “Negative tests” folder: galat role se call karke 403 verify karo  

Agar aap chaho to `php artisan route:list` se bhi dekh sakte ho kaunsi route pe kaun sa middleware (role) hai:

```bash
php artisan route:list --path=api
```

Isse aapko pata chalega kon role kon si API use kare aur sab ko correct data ja raha hai ya nahi – mismatch ke liye upar wale steps follow karo.

---

## 6. Short Verification Checklist (Sahi Data / No Mismatch)

- [ ] **Client:** Login → `GET /api/client/settings/dashboard` → 200, sirf client settings. Same token se `GET /api/technician/dashboard` → 403.
- [ ] **Technician:** Login → `GET /api/technician/dashboard` → 200, sirf us technician ka data. `GET /api/technician/leave-requests` → sirf uski leave list. Same token se `GET /api/hr/employees` → 403.
- [ ] **Supervisor:** Login → `GET /api/supervisor/team` → 200, sirf apne zones ke members. `GET /api/supervisor/leave-requests` → sirf apni leave. Same token se `GET /api/admin/users` → 403.
- [ ] **HR:** Login → `GET /api/hr/leave-requests` → 200, list me technician + supervisor dono ki leave dikhni chahiye. Same token se `GET /api/technician/dashboard` → 403.
- [ ] **Area Manager:** Login → `GET /api/area-manager/dashboard/summary` → 200, apne region ka data. Same token se `GET /api/hr/employees` → 403.
- [ ] **Admin:** Login → `GET /api/admin/dashboard/statistics` → 200. Kisi aur role ka token `GET /api/admin/users` pe → 403.
- [ ] **Notifications:** Har role ke liye notifications ek hi jagah (unified). Client/Technician/Supervisor/etc. sirf apni notifications dekhen (different tokens → different lists).
