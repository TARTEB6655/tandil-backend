# Technician Leave Page – Reasons / Leave Types

Your client asked for **leave reasons** on the leave page so the technician can choose from: Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other (with notes).

## Do you need to build new APIs?

**No new “leave request” APIs are required.** Leave (vacations) is already part of the **Technician Availability** API:

- **GET** `/api/technician/availability` – returns `vacations[]` (each has `id`, `start_date`, `end_date`, `reason`).
- **PUT** `/api/technician/availability` – send `vacations` as a JSON array of `{ start_date, end_date, reason? }` to set/replace all leave entries.

So the “leave page” in the app should:

1. **Fetch leave types for the dropdown**  
   Call **GET** `/api/technician/leave-types` (Bearer token). Response shape:

   ```json
   {
     "success": true,
     "data": [
       { "value": "sick", "label": "Sick leave" },
       { "value": "annual", "label": "Annual Leave" },
       { "value": "unpaid", "label": "Unpaid leave" },
       { "value": "paternity", "label": "Paternity Leave" },
       { "value": "other", "label": "Other", "requires_notes": true }
     ]
   }
   ```

2. **Show a dropdown** with these options. When the technician selects one, store the chosen `value` (or `label`) for the current leave entry.

3. **For “Other”** (`requires_notes === true`), show a notes field. When saving, send in `reason` either:
   - `"Other"` and put the user’s text in a separate field if you add one later, or  
   - `"Other: " + userNotes` in the single `reason` field (recommended with current API).

4. **Save leave** by calling **PUT** `/api/technician/availability` with form-data (or JSON) including:

   - `vacations`: JSON array of leave entries, e.g.  
     `[{ "start_date": "2026-03-01", "end_date": "2026-03-05", "reason": "Annual Leave" }]`  
   - For Other with notes:  
     `{ "start_date": "...", "end_date": "...", "reason": "Other: personal matter" }`

So:

- **Existing APIs:** GET/PUT `/api/technician/availability` (already there).
- **New API added for you:** GET `/api/technician/leave-types` – use this to drive the reason dropdown so the backend owns the list (Sick, Annual, Unpaid, Paternity, Other). No need to hardcode the list in the app.

In **Postman**: see **Module 9 – Technician** → **GET Technician – Leave types** and **GET/PUT Technician – Availability**.
