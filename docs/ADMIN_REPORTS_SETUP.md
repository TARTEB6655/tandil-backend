# Admin Reports Management – Setup

The Admin Reports API uses the `admin_reports` table. If you see:

**`Table 'your_database.admin_reports' doesn't exist`** (500 error in Postman)

then the migration has not been run on the database your app is using.

---

## 1. Create the table (required)

From your project root, run:

```bash
cd C:\Users\pc\Desktop\tandil-backend
php artisan migrate
```

This runs any pending migrations, including `2026_01_29_000001_create_admin_reports_table`, which creates the `admin_reports` table.

- Use the **same `.env`** as when you run the API (so `DB_DATABASE`, `DB_USERNAME`, etc. point to your MySQL database, e.g. `ecmbnbvxsm`).
- After this, **Get All Reports**, **Get Report Statistics**, and the other Admin Report endpoints should work in Postman.

---

## 2. Optional: run queue worker (for async report generation)

Report generation is queued. For reports to be generated in the background:

```bash
php artisan queue:work
```

If you don’t run a worker, you can still use **sync** queue in `.env`:

```env
QUEUE_CONNECTION=sync
```

Then reports are generated during the request (no background worker needed).

---

## 3. Test the APIs

1. In Postman, use an **admin** user token: `Authorization: Bearer {{token}}`.
2. Call **GET** `{{base_url}}/api/admin/reports` and **GET** `{{base_url}}/api/admin/reports/statistics` — both should return **200** with JSON (e.g. empty list / zero counts if no reports yet).
3. Run the test suite: `php artisan test tests/Feature/AdminReportTest.php`
