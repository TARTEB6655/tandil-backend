# For Frontend (React Native): What is visit_id and how to send it when submitting a report

## Short answer

- **Visit = Job / Task.** In the app, one "job" or "task" that the technician sees is one **visit** in the backend.
- **visit_id = Job ID = Task ID.** It is the **same number** as the job the technician is currently viewing.

You **already have** this id on the Job Details screen. You do **not** need to ask the user for it and you do **not** need to send `supervisor_id`.

---

## Where does the frontend get visit_id?

1. **From the task list**  
   When the technician opens "My Tasks", each task has an **id**. That id is the **visit_id**.

2. **From the Job Details screen**  
   When the technician opens "Job Details" for a task, you either:
   - Called something like `GET /api/technician/tasks/58/detail` → the **58** in the URL is the **visit_id**, or
   - The Job Details API response has a field **`job_id`** → that value is the **visit_id**.

So on the Job Details screen you already have:
- The **id** you used in the URL to load that screen, **or**
- The **`job_id`** from the Job Details response.

Use that same number as **visit_id** when the technician taps "Submit Field Report to Supervisor".

---

## Submit report API

- **Endpoint:** `POST /api/technician/reports`
- **Body (form-data):**
  - **visit_id** (required) – the job id you are currently showing (same as `job_id` from Job Details or the id in `/tasks/{id}/detail`).
  - technician_notes (optional)
  - before_photo (optional, file)
  - after_photo (optional, file)

Do **not** send `supervisor_id`. The backend gets the supervisor from the visit.

---

## Example flow

1. Technician opens task list → API returns tasks, each with `id` (e.g. 58).
2. Technician taps task 58 → you open Job Details with that id (e.g. request to `/tasks/58/detail`).
3. Job Details response includes `job_id: 58` (same number).
4. Technician fills notes/photos and taps "Submit Field Report to Supervisor".
5. You call `POST /api/technician/reports` with **visit_id = 58** (and notes/photos). No supervisor_id.

So: **visit_id = the id of the job/task that is currently on the Job Details screen.**
