# Client ↔ Technician ↔ Supervisor – Report Flow

## Connection between Client, Technician, and Supervisor

1. **Client** has a **subscription**. Visits (jobs) are created for that subscription.
2. **Visit** is assigned to a **Technician** (and often has a **Supervisor** for the area).
3. **Technician** completes the visit → submits a **Report** (notes, recommended products) → the report is for that visit and is **pending supervisor review**.
4. **Supervisor** sees the report (in their reports list), reviews it, adds supervisor notes and recommendations, and **approves** (or keeps pending).
5. **Client** sees **approved** reports (e.g. on client dashboard “Recent Reports”) – so effectively the supervisor approves the report and then the client can see it. Admin can also “send to client” (web route).

So: **Technician sends report → Supervisor reviews/approves → Client sees (approved) report.**

---

## Single API: Technician → Submit Field Report to Supervisor

There is **one API** for the technician to share data (field report) with the supervisor. Use it when the technician taps **“Submit Field Report to Supervisor”** in the app.

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| **POST** | **`/api/technician/reports`** | Bearer token (technician) | **Technician-only:** Submit field report (form-data or JSON). |
| **GET** | `/api/reports` | Bearer token (technician) | List reports (technician sees only reports for their visits). |
| **GET** | `/api/reports/{id}` | Bearer token (technician) | Get one report. |

### POST /api/technician/reports – Request body (form-data or JSON)

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `visit_id` | integer | **Yes** | ID of the job/visit (e.g. Job #15). |
| `technician_notes` | string | No | Field notes (observations, issues found, work completed). Maps to “Field Notes” in the app. |
| `notes` | string | No | Additional notes (legacy). |
| `recommended_products` | array of strings | No | Product recommendations. |
| `status` | string | No | Defaults to `pending` when called by technician. Allowed: `draft`, `pending`, `approved`, `sent_to_client`. |

- **Behaviour:** Creates a report linked to the visit. Only the technician assigned to that visit can submit (403 otherwise). The report appears in the supervisor’s list for review.
- **Before/After photos:** Stored at visit level (`VisitPhoto`). Upload photos for the visit separately if your app supports it; the report is linked to the same `visit_id`.

**Example (mobile “Submit Field Report to Supervisor”):**

```json
POST /api/reports
Authorization: Bearer <technician_token>

{
  "visit_id": 15,
  "technician_notes": "Focus on the date palm section. Check drip irrigation system for any leaks. Completed watering and found one minor leak, repaired."
}
```

**In Postman:** **Module 9 – Technician Dashboard – All APIs** → **"Technician - Submit Field Report to Supervisor"** ( → “form-data” (POST `{{base_url}}/api/technician/reports`, form-data). Use **technician** token and `visit_id` in env.

---

### 2. Web flow (full report: technician_notes + recommended products)

The **full** “technician sends report to supervisor” flow (with technician notes and recommended products) is implemented on **web** only, not in the shared API:

- **Technician (web):**
  - List: `GET /technician/reports` → `Technician\ReportController@index`
  - Create form: `GET /technician/reports/create` → `Technician\ReportController@create`
  - Submit: **POST** `/technician/reports` → `Technician\ReportController@store`  
    Body: `visit_id`, `technician_notes` (required), `recommended_products[]` (optional).  
    Creates report with `technician_notes`, `recommended_products`, `status = pending`.
- **Supervisor (web):**
  - List: `GET /supervisor/reports` → `Supervisor\ReportController@index`
  - Show: `GET /supervisor/reports/{id}` → `Supervisor\ReportController@show`
  - Review: `GET /supervisor/reports/{id}/review` → `Supervisor\ReportController@review`
  - Finalize: **POST** `/supervisor/reports/{id}/finalize` → `Supervisor\ReportController@finalize`  
    Adds `supervisor_notes`, `recommended_products`, sets `status` (e.g. approved).

So: **Technician sends report to supervisor** is done by **API** (POST `/api/technician/reports` with `visit_id`, `technician_notes`, `recommended_products[]` – form-data or JSON) or by **web** (POST `/technician/reports`). Use the technician-only API from the mobile app.

---

## Supervisor API (reports)

Under **Module 10 – Supervisor** in Postman:

- **GET** `/api/supervisor/reports` – list field reports. Use **?status=pending** for Pending Field Reports (dashboard).
- **POST** `/api/supervisor/reports/generate` – generate an **AdminReport** (different from visit reports: high-level supervisor reports).
- **GET** `/api/supervisor/visits/{visit_id}` – get visit with report (Review Report screen).
- **POST** `/api/supervisor/visits/{visit_id}/finalize` – submit to client: form-data `recommendations[]`, `supervisor_notes`, **status=sent_to_client**.
- **GET** `/api/supervisor/reports/{id}/download` – download (CSV).

**Submit to client (API):** POST `/api/supervisor/visits/{visit_id}/finalize` with `recommendations[]`, `status=sent_to_client`. **Web:** Visit report review/finalize `/supervisor/reports/{id}/review` and POST `/supervisor/reports/{id}/finalize`. There is no separate API for “supervisor finalize visit report”.

---

## Summary

| Step | Who | API / Web |
|------|-----|-----------|
| Technician sends report | Technician | **API (technician-only):** POST `/api/technician/reports` with `visit_id`, `technician_notes`, `recommended_products[]` (form-data or JSON). **Web:** POST `/technician/reports`. |
| Supervisor receives (pending list) | Supervisor | **API:** GET `/api/supervisor/reports?status=pending`. **Web:** GET `/supervisor/reports`. |
| Supervisor submits to client | Supervisor | **API:** POST `/api/supervisor/visits/{visit_id}/finalize` with `recommendations[]`, `status=sent_to_client`. **Web:** POST `/supervisor/reports/{id}/finalize`. |
| Client sees report | Client | **API:** GET `/api/reports` (client sees reports for their subscriptions). **Web:** Client dashboard “Recent Reports”. |

The **technician-only API** for submitting a field report to the supervisor is **POST /api/technician/reports** (Bearer token, form-data or JSON: `visit_id`, `technician_notes`, `recommended_products[]`). Find it in Postman under **Module 9 – Technician Dashboard** → **"Technician - Submit Field Report to Supervisor"**.
