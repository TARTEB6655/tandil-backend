# Admin: Zone-based assignment flow

Use this flow to assign supervisors and technicians to zones (areas = cities, e.g. UAE).

---

## Step 1: See which technicians are where and what they do

**Request:** `GET /api/admin/technicians-for-zones?per_page=50`  
**Auth:** Bearer token (admin)

**Response:** List of technicians with:
- `id`, `name`, `email`, `phone`, `employee_id`
- `region` – where they are based
- `specializations` – what work they do (e.g. Tree Watering, Planting)
- `designation`
- `assigned_zone_ids`, `assigned_zones` – zones they are already assigned to

Use this to decide which technician IDs to assign to a zone (e.g. `technician_ids=3,4,6`).

---

## Step 2: See which supervisors are in which zones

**Request:** `GET /api/admin/supervisors-for-zones?per_page=50`  
**Auth:** Bearer token (admin)

**Response:** List of supervisors with:
- `id`, `name`, `email`, `phone`, `employee_id`
- `region`
- `assigned_zone_ids`, `assigned_zones` – zones they currently supervise

Use this to decide which supervisor IDs to assign to a zone (e.g. `supervisor_ids=2,5`).

---

## Step 3: Create or update a zone (area) with form-data

All zone create/update APIs use **form-data** (not JSON).

### Create zone

**Request:** `POST /api/admin/areas`  
**Body (form-data):**

| Key             | Value example | Required |
|-----------------|---------------|----------|
| name            | Dubai         | Yes      |
| description     | Dubai city    | No       |
| country         | UAE           | No (default UAE) |
| supervisor_ids  | 2,5           | No (comma-separated user ids) |
| technician_ids  | 3,4,6         | No (comma-separated user ids) |

### Update zone

**Request:** `PUT /api/admin/areas/{id}` or `POST /api/admin/areas/{id}`  
**Body (form-data):** Same keys as above. All fields optional; only send what you want to change.

- Use the `id` from **Areas - List** or **Areas - Get** as `{id}`.
- `supervisor_ids` and `technician_ids` replace the current assignment for that zone (comma-separated, e.g. `2,5` or `3,4,6`).

---

## Summary

1. Call **Technicians for Zones** → note technician `id`s and their regions/specializations.
2. Call **Supervisors for Zones** → note supervisor `id`s and their current zones.
3. **Areas - Create** or **Areas - Update** with form-data: `name`, `description`, `country`, `supervisor_ids=2,5`, `technician_ids=3,4,6`.

Admin assigns both supervisors and technicians to zones using these form-based APIs only.
