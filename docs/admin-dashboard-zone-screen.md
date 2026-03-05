# Admin Dashboard: Zone & Assignment Screen

This doc describes the **admin dashboard screen** where you can see supervisors and technicians by zone/specialization and assign them easily. After that, the supervisor can assign jobs to technicians; if a technician rejects or doesn’t respond in time, the job is auto-offered to another technician in the same zone with the same specialization.

---

## 1. Admin screen: what to build

### 1.1 Supervisors list (with zones)

- **Purpose:** See all supervisors and which zones they are in so you can assign/change zones.
- **Data source:** `GET /api/admin/supervisors-for-zones?per_page=50`
- **Show:**
  - Name, email, phone, employee_id
  - Region
  - **Assigned zones** (list of zone names, e.g. Dubai, Sharjah)
- **Actions:**
  - “Edit zones” → open a form/modal where you pick zones (areas). On save, call **Areas - Update** for each zone that changed, or use a bulk “Assign supervisor to zones” flow: for each selected zone, call `PUT /api/admin/areas/{zone_id}` with form-data including `supervisor_ids=2,5` (comma-separated; include this supervisor’s id).

### 1.2 Technicians list (with zone + specialization)

- **Purpose:** See all technicians, their zone(s), and specializations so you can assign the right people to the right zones.
- **Data source:** `GET /api/admin/technicians-for-zones?per_page=50`
- **Show:**
  - Name, email, phone, employee_id
  - **Region**
  - **Specializations** (e.g. Tree Watering, Planting)
  - **Assigned zones** (e.g. Dubai, Sharjah)
- **Actions:**
  - “Edit zones” → pick zones; on save call **Areas - Update** for the relevant areas with `technician_ids=3,4,6` (comma-separated; include this technician’s id).

### 1.3 Zones (areas) list and assign

- **Purpose:** See all zones and who (supervisors + technicians) is in each; assign from here.
- **Data source:** `GET /api/admin/areas?with=supervisors,technicians&country=UAE`
- **Show per zone:**
  - Zone name, country
  - List of supervisors in this zone
  - List of technicians in this zone (optionally show their specializations)
- **Actions:**
  - “Edit zone” → form with:
    - name, description, country
    - Multi-select **Supervisors** (use ids from **Supervisors for Zones**)
    - Multi-select **Technicians** (use ids from **Technicians for Zones**)
  - On save: `PUT /api/admin/areas/{id}` with **form-data**: `name`, `description`, `country`, `supervisor_ids=2,5`, `technician_ids=3,4,6`

You can have one combined “Zone assignment” screen with:
- Tabs or sections: **Zones** | **Supervisors** | **Technicians**
- Same APIs and actions as above.

---

## 2. Supervisor assigns job to technician

- Supervisor uses **Assignments - Pending** and **Assignments - Create** (or Update) to assign a **visit (job)** to a **technician** in their zone.
- Backend supports **job offer with time limit**:
  - When a job is assigned to a technician, they have until **accept_by** (e.g. 15 minutes) to accept or reject.
  - If they **accept** → job is theirs (status in_progress, etc.).
  - If they **reject** → system automatically offers the job to **another technician in the same zone with the same specialization** (if any).
  - If they **do nothing** (no response by **accept_by**) → same: job is automatically offered to another technician in the same zone with the same specialization.
  - If after 2–3 attempts (reject or timeout) no one accepts → job is **escalated to supervisor** (escalated_at set) so they can assign manually.

So: many technicians can be in the same zone; when the supervisor assigns a job, one technician gets the offer first; on reject or no response, the **same zone + same specialization** rule is used to pick the next one automatically.

### Time limit and timeout (no response)

- When a job is offered to a technician, they have **15 minutes** (configurable in `VisitOfferService::ACCEPT_MINUTES`) to accept or reject.
- If they **do not respond** by that time, the backend treats it as a timeout: the offer is marked timeout and the job is automatically offered to the next available technician (same zone, same specialization), or escalated to the supervisor if none found or after **3 attempts** (`VisitOfferService::MAX_OFFERS_BEFORE_ESCALATE`).
- To process timeouts, run the scheduler **every minute** (e.g. cron):
  ```bash
  * * * * * cd /path/to/project && php artisan visits:process-offer-timeouts
  ```

---

## 3. APIs to use (summary)

| What | API |
|------|-----|
| List supervisors with zones | `GET /api/admin/supervisors-for-zones` |
| List technicians with zone + specialization | `GET /api/admin/technicians-for-zones` |
| List zones with supervisors & technicians | `GET /api/admin/areas?with=supervisors,technicians` |
| Create zone | `POST /api/admin/areas` (form-data) |
| Update zone (assign supervisors/technicians) | `PUT /api/admin/areas/{id}` (form-data: supervisor_ids, technician_ids) |
| Supervisor: pending jobs | `GET /api/supervisor/assignments/pending` |
| Supervisor: assign job to technician | `POST /api/supervisor/assignments` (form-data: visit_id, technician_id, …) |

All assignment to zones (who is in which zone) is done via the **form-based** Admin Areas APIs above.
