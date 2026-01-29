# Changes Report – Tandil Backend

This document summarizes all changes made to the project during the development session.

---

## 1. Project Setup (Run Locally)

**Action:** Project was run locally; no source code was added or modified for this.

**Steps performed:**
- Verified/ran `composer install` (dependencies already present)
- Verified/ran `npm install` (dependencies already present)
- Confirmed `.env` exists with `APP_KEY` and SQLite database
- Ensured SQLite DB file and ran `php artisan migrate --seed`
- Confirmed `php artisan storage:link` (link already existed)
- Started dev server with `php artisan serve` (background)

**Result:** App available at **http://127.0.0.1:8000** (or http://localhost:8000).

---

## 2. Recent Activity API – No Code Changes

**Action:** Checked whether a “Recent Activity” API exists.

**Finding:** The API was already implemented.

- **Endpoint:** `GET /api/admin/dashboard/recent-activities`
- **Module:** Admin Dashboard
- **Controller:** `App\Http\Controllers\Admin\AdminDashboardController::recentActivities`
- **Auth:** Bearer token + admin role
- **Postman:** Under folder **10. Admin & HR Routes** → request **Admin Dashboard Recent Activities**

No code was added or changed for this.

---

## 3. Admin Reports Management API (New Feature)

A full **Admin Reports Management** API was added as per the requirements document. All endpoints are under **`/api/admin/reports`** and require **Bearer token + admin role**.

### 3.1 New Files

| File | Purpose |
|------|--------|
| `database/migrations/2026_01_29_000001_create_admin_reports_table.php` | Creates `admin_reports` table (title, type, status, scheduled_at, recurrence, generated_at, file_path, file_size, format, parameters, created_by, failure_reason, etc.) |
| `app/Models/AdminReport.php` | Eloquent model for admin reports; constants for TYPES, STATUSES, RECURRENCE; `creator()` relation to User |
| `app/Http/Controllers/Admin/AdminReportController.php` | API controller with all 9 endpoints (see below) |
| `app/Jobs/GenerateReportJob.php` | Queued job: generates report file (text/CSV placeholder), updates status to `generated`, stores file path/size, notifies creator |
| `app/Notifications/ReportGeneratedNotification.php` | Notification when report is ready (database + mail); includes download link |

### 3.2 API Endpoints Implemented

| # | Method | Endpoint | Description |
|---|--------|----------|-------------|
| 1 | GET | `/api/admin/reports` | List all reports with filters `status`, `type`, pagination `page`, `per_page` |
| 2 | GET | `/api/admin/reports/{id}` | Get single report with creator (id, name, email) and parameters |
| 3 | POST | `/api/admin/reports/generate` | Start async report generation (dispatches `GenerateReportJob`) |
| 4 | POST | `/api/admin/reports/schedule` | Schedule report at `scheduled_at` with optional `recurrence` |
| 5 | DELETE | `/api/admin/reports/{id}/cancel` | Cancel a scheduled report |
| 6 | GET | `/api/admin/reports/{id}/download` | Download generated report file (PDF/CSV/Excel) |
| 7 | POST | `/api/admin/reports/{id}/share` | Share via `email` (recipients + message) or `link` (signed URL) |
| 8 | GET | `/api/admin/reports/statistics` | Counts by status (total, pending, generated, scheduled, failed) and by type |
| 9 | DELETE | `/api/admin/reports/{id}` | Delete report and its file from storage |

### 3.3 Report Types Supported

- `financial`, `performance`, `customer`, `operational`, `user`, `subscription`

### 3.4 Report Statuses

- `pending` – generation in progress  
- `generated` – ready, file available  
- `scheduled` – scheduled for future  
- `failed` – generation failed (optional)

### 3.5 Routes Added

**File:** `routes/api.php`

- New route group: `Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/reports')` with all 9 routes above.
- Download route named: `api.admin.reports.download` (used for signed share links).

### 3.6 Behaviour Summary

- **Generate:** Creates `AdminReport` with status `pending`, dispatches `GenerateReportJob`. Job builds a simple report file (text/CSV), saves to `storage/app/admin_reports/{id}/`, sets status to `generated`, and notifies creator.
- **Schedule:** Creates report with status `scheduled` and `scheduled_at`; no cron/command was added to auto-run scheduled reports (can be added later).
- **Download:** Serves file from local disk; response uses correct extension and filename `report-{id}.{ext}`.
- **Share (email):** Sends plain email with download URL to given recipients.
- **Share (link):** Returns a temporary signed URL for the download route (e.g. 7-day expiry).
- **Delete:** Deletes record and file from storage if present.
- **Error responses:** Validation and business logic errors return JSON in the format specified (`success`, `message`, `errors` where applicable).

---

## 4. Summary Table

| Category | Action |
|----------|--------|
| **Run project** | Started `php artisan serve`; no code changes |
| **Recent Activity API** | Verified existing; no code changes |
| **Admin Reports API** | New migration, model, controller, job, notification, and routes for full Reports Management API |

---

## 5. What You Need To Do

1. **Run migration** (if not already):  
   `php artisan migrate`  
   This creates the `admin_reports` table.

2. **Queue worker** (for async report generation):  
   `php artisan queue:listen`  
   Or run the scheduler if you add a command for scheduled reports.

3. **Optional:** Add a scheduled task (e.g. `ProcessScheduledReports`) to turn `scheduled` reports into `pending` and dispatch `GenerateReportJob` when `scheduled_at` is due.

4. **Optional:** Add Admin Reports requests to the Postman collection under a folder like “Admin Reports Management” for easier testing.

---

**Report generated:** Based on the current codebase and conversation.  
**Base URL for API:** `http://localhost:8000/api`
