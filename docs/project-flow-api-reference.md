# Project Flow – API Reference (Created vs Existing)

**Flow:** Client buys service → Client details go to Supervisor → Supervisor assigns task to Technician → Technician submits report to Supervisor → Supervisor tells Client.

---

## 1. Client buys a service

Client details (and the service/visit) eventually reach the supervisor via visits/subscriptions. APIs used for “client buys service”:

| API | Method | Endpoint | Auth | Created? | Postman module | Notes |
|-----|--------|----------|------|----------|----------------|-------|
| List subscription plans | GET | `/api/subscriptions/plans` | Public | No | **Module 4** – Client Dashboard – Subscriptions | Client sees plans |
| Create subscription (buy service) | POST | `/api/subscriptions` | Client | No | **Module 4** – Subscriptions (Create) | Body: plan, client_id, etc. |
| List my subscriptions | GET | `/api/subscriptions` | Client | No | **Module 4** – Subscriptions (List) | |
| **OR** Shop: add to cart | POST | `/api/shop/cart/add` | Client | No | **Module 8** – Client Dashboard – Shop & Orders | For product orders |
| **OR** Shop: orders | GET | `/api/shop/orders` | Client | No | **Module 8** – Shop & Orders | |

**Conclusion:** All “client buys service” APIs **existed**; none were created for this flow.

---

## 2. Client details go to Supervisor (Supervisor sees tasks/visits)

Supervisor sees visits in their area (from subscriptions/orders). No new “client details to supervisor” API was added; supervisor uses existing visit/assignment APIs.

| API | Method | Endpoint | Auth | Created? | Postman module | Notes |
|-----|--------|----------|------|----------|----------------|-------|
| List visits (supervisor’s area) | GET | `/api/supervisor/visits` | Supervisor | No | **Module 10** – Supervisor – Visits (list) | Visits in supervised areas |
| Pending assignments (unassigned / to assign) | GET | `/api/supervisor/assignments/pending` | Supervisor | No | **Module 10** – C. Assignments – Assignments - List Pending | Visits needing technician |
| Dashboard summary | GET | `/api/supervisor/dashboard/summary` | Supervisor | No | **Module 10** – Supervisor – Dashboard | |

**Conclusion:** **Existing** APIs; we did **not** create new ones for this step.

---

## 3. Supervisor assigns task to Technician

| API | Method | Endpoint | Auth | Created? | Postman module | Notes |
|-----|--------|----------|------|----------|----------------|-------|
| Assign technician to visit | POST | `/api/supervisor/assignments` | Supervisor | No | **Module 10** – C. Assignments – Assignments - Create (POST) | Body: `visit_id`, `technician_id`, optional `scheduled_date`, `note` |
| Update assignment | PUT / POST | `/api/supervisor/assignments/{id}` | Supervisor | No | **Module 10** – C. Assignments – Assignments - Update | Update technician or date |
| Reassign (change technician) | POST | `/api/supervisor/assignments/{id}/reassign` | Supervisor | No | **Module 10** – C. Assignments – Assignments - Reassign | Body: `technician_id`, optional `reason` |

**Conclusion:** **Existing** APIs; we did **not** create new ones for this step.

---

## 4. Technician gets assigned task & submits report to Supervisor

| API | Method | Endpoint | Auth | Created? | Postman module | Notes |
|-----|--------|----------|------|----------|----------------|-------|
| Get assigned visits (tech) | GET | `/api/tech/visits` | Technician | No | **Module 9** – Technician - Get Assigned Visits | |
| Get tasks (filter: today, upcoming, etc.) | GET | `/api/technician/tasks` | Technician | No | **Module 9** – GET Technician – Tasks list | |
| Get task detail (job details screen) | GET | `/api/technician/tasks/{id}/detail` | Technician | No | **Module 9** – GET Technician – Task details | |
| Accept / Start / Complete visit | POST | `/api/tech/visits/{id}/accept`, `.../start`, `.../complete` | Technician | No | **Module 9** – Technician - Accept Visit, Start Visit, Complete Visit | |
| **Submit field report to supervisor** | **POST** | **`/api/technician/reports`** | Technician | **Yes** | **Module 9** – **Technician - Submit Field Report to Supervisor** | Body (form-data): `visit_id`, `technician_notes`, `notes`, `before_photo`, `after_photo`. Visit must be completed. |

**Conclusion:** Only **POST /api/technician/reports** was **created** for this flow; all other technician APIs existed.

---

## 5. Supervisor receives report & tells Client

| API | Method | Endpoint | Auth | Created? | Postman module | Notes |
|-----|--------|----------|------|----------|----------------|-------|
| List pending field reports | GET | `/api/supervisor/reports?status=pending` | Supervisor | **Yes (enhanced)** | **Module 10** – D. Reports – **Reports - List Pending (Pending Field Reports)** | Added `?status=pending` and `visit.technician`. |
| List all field reports | GET | `/api/supervisor/reports` | Supervisor | No | **Module 10** – D. Reports – Reports - List | Optional `status` filter. |
| Get report detail (review screen) | GET | `/api/supervisor/visits/{visit_id}` | Supervisor | No | **Module 10** – D. Reports – **Visit Report - Review Visit** | We added `technician` in response. |
| **Submit report to client (tell client)** | **POST** | **`/api/supervisor/visits/{visit_id}/finalize`** | Supervisor | **Yes (enhanced)** | **Module 10** – D. Reports – **Visit Report - Finalize / Submit to Client** | Body: `supervisor_notes`, `recommendations[]`, `recommended_products[]`, **`status=sent_to_client`**. Notifies client. |

**Conclusion:** **GET /api/supervisor/reports** was **enhanced** (status filter, technician in response). **POST /api/supervisor/visits/{id}/finalize** was **enhanced** (recommendations, `sent_to_client`, notify client). The finalize endpoint existed; we extended it for “supervisor tells client”.

---

## 6. Client sees report

| API | Method | Endpoint | Auth | Created? | Postman module | Notes |
|-----|--------|----------|------|----------|----------------|-------|
| List reports (client sees own) | GET | `/api/reports` | Client | No | **Module 7** – Client Dashboard – Reports – List Reports | Client sees only their reports. |

**Conclusion:** **Existing** API; we did **not** create a new one.

---

## Summary: What we created or enhanced

| Step in flow | API | Created or enhanced? |
|--------------|-----|----------------------|
| Technician submits report to supervisor | **POST /api/technician/reports** | **Created** (visit_id, technician_notes, before_photo, after_photo) |
| Supervisor sees pending reports | **GET /api/supervisor/reports?status=pending** | **Enhanced** (status filter, visit.technician) |
| Supervisor tells client | **POST /api/supervisor/visits/{visit_id}/finalize** | **Enhanced** (recommendations, status=sent_to_client, notify client) |

All other steps (client buys service, supervisor sees visits, supervisor assigns technician, technician gets tasks, client sees reports) use **existing** APIs; no new endpoints were added for those.

---

## Postman module index (where to find each API)

| Module no. | Module name | Contains |
|------------|-------------|----------|
| **1** | Health Check - START HERE | Health check |
| **2** | Authentication | Register, Login, Logout |
| **3** | Client Dashboard – Profile | Memberships, Personal info, Addresses, Help and Support (tickets) |
| **4** | Client Dashboard – Subscriptions | Plans, Create/List/Get/Update/Delete subscriptions (client buys service) |
| **5** | Client Dashboard – Banners | Banners (public) |
| **6** | Client Dashboard – Visits | List/Show/Update visits, upload/delete photos |
| **7** | Client Dashboard – Reports | List Reports, Get Report Details, Create Report **(client sees reports)** |
| **8** | Client Dashboard – Shop & Orders | Cart, Checkout, Orders (client buys products) |
| **9** | Technician Dashboard – All APIs (Technician Only) | Assigned visits, Accept/Start/Complete, **Submit Field Report to Supervisor**, Tasks, Jobs, Profile, etc. |
| **10** | Supervisor Dashboard – All APIs | Dashboard, Team, **C. Assignments** (pending, create, update, reassign), **D. Reports** (List, List Pending, Review Visit, **Finalize / Submit to Client**), Visits, Profile |
| 11–15 | Admin, Other | Admin stats, users, reports, support tickets; Module 15 = Support tickets (all roles) |

**Flow-specific:** Technician submit report → **Module 9** → “Technician - Submit Field Report to Supervisor”. Supervisor pending reports → **Module 10** → D. Reports → “Reports - List Pending”. Supervisor tell client → **Module 10** → D. Reports → “Visit Report - Finalize / Submit to Client”.
