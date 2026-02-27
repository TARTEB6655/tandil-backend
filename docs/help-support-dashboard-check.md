# Help & Support – Dashboard check (step by step)

Use this to verify the **Help & Support** system from the **web dashboard** on your local server.

**Base URL:** `http://127.0.0.1:8000`  
**Server:** Run `php artisan serve` if it’s not already running.

---

## Test users (from `FixedUsersOnlySeeder`)

| Role        | Email               | Password   |
|------------|---------------------|------------|
| Admin      | admin@tandil.com    | password123 |
| Client     | client1@test.com    | password123 |
| Technician | technician1@test.com | password123 |
| Supervisor | supervisor1@test.com | password123 |
| Area Manager | areamanager1@test.com | password123 |
| HR         | hr1@test.com        | password123 |

If you haven’t seeded yet: `php artisan db:seed`

---

## Step 1 – Open the app and log in

1. In the browser go to: **http://127.0.0.1:8000**
2. You should be redirected to the **login** page (`/login`).
3. Log in with one of the users above (e.g. **client1@test.com** / **password123**).
4. After login you should land on the **dashboard** for that role (e.g. Client dashboard).

---

## Step 2 – Client / Technician / Supervisor / Area Manager / HR: “Help & Support”

These roles use **Help & Support** to **create tickets** and **see replies**.

1. Log in as **Client**: `client1@test.com` / `password123`.
2. In the **sidebar**, find **“Help & Support”** (or “Help & support”) and click it.
   - Client: **http://127.0.0.1:8000/client/help-support**
3. You should see:
   - A list of **your existing tickets** (if any).
   - A **form to create a new ticket** (Subject, Email, Description).
4. **Create a ticket:**
   - Fill **Subject** (e.g. “Test from dashboard”).
   - Fill **Email** (e.g. `client1@test.com`).
   - Fill **Description** (e.g. “Checking Help & Support from dashboard.”).
   - Submit the form.
5. You should be redirected back to the list and see the new ticket.
6. Click the new ticket to open the **ticket detail** page.
7. In the reply form, type a message and submit – you should see your reply in the thread.

Repeat the same flow (sidebar → Help & Support → create ticket → open ticket → reply) for:

- **Technician:** **http://127.0.0.1:8000/technician/help-support** (log in as technician1@test.com).
- **Supervisor:** **http://127.0.0.1:8000/supervisor/help-support** (log in as supervisor1@test.com).
- **Area Manager:** **http://127.0.0.1:8000/areamanager/help-support** (log in as areamanager1@test.com).
- **HR:** **http://127.0.0.1:8000/hr/help-support** (log in as hr1@test.com).

---

## Step 3 – Admin: “Support tickets”

Admins see **all** tickets and can **reply** and **change status**.

1. Log out (if you’re still logged in as client/technician/etc.).
2. Log in as **Admin**: **admin@tandil.com** / **password123**.
3. In the **sidebar**, open the **“Communication”** (or similar) section.
4. Click **“Support tickets”** (or “Support Tickets”).
   - Direct URL: **http://127.0.0.1:8000/admin/support-tickets**
5. You should see a **table of all support tickets** (including the one you created as client).
6. Click one ticket to open the **ticket detail** page.
7. **Reply as admin:**
   - Type a message in the reply form and submit.
   - The thread should show your admin reply.
8. **Change status:**
   - Use the status dropdown (e.g. Open → In progress → Resolved / Closed) and save.
9. Optionally **delete** the ticket (if the UI has a delete button and you want to test it).

---

## Step 4 – End-to-end check (user ticket → admin reply → user sees reply)

1. Log in as **Client** (`client1@test.com`).
2. Go to **Help & Support** → create a new ticket → note the subject.
3. Log out and log in as **Admin** (`admin@tandil.com`).
4. Go to **Support tickets** → open the ticket you just created.
5. Add an **admin reply** and set status to e.g. **In progress** or **Resolved**.
6. Log out and log in again as **Client**.
7. Go to **Help & Support** → open the same ticket.
8. You should see the **admin’s reply** in the thread.

If all of this works, the Help & Support flow is working from the dashboard.

---

## Quick reference – URLs

| What                | URL |
|---------------------|-----|
| Login               | http://127.0.0.1:8000/login |
| Admin dashboard     | http://127.0.0.1:8000/admin/dashboard |
| Admin support tickets | http://127.0.0.1:8000/admin/support-tickets |
| Client Help & Support | http://127.0.0.1:8000/client/help-support |
| Technician Help & Support | http://127.0.0.1:8000/technician/help-support |
| Supervisor Help & Support | http://127.0.0.1:8000/supervisor/help-support |
| Area Manager Help & Support | http://127.0.0.1:8000/areamanager/help-support |
| HR Help & Support  | http://127.0.0.1:8000/hr/help-support |

---

## If something doesn’t work

- **404 on dashboard / help-support:** Make sure you’re logged in and using the correct role (e.g. client vs admin).
- **Blank or error page:** Check `storage/logs/laravel.log` and the browser console (F12).
- **“Support tickets” / “Help & Support” not in sidebar:** Your role might not have that menu; use the direct URLs above.
- **No test users:** Run `php artisan db:seed` (or `php artisan db:seed --class=FixedUsersOnlySeeder`).
