# Technician Leave Page – Reasons / Leave Types

Your client asked for **leave reasons** on the leave page so the technician can choose from: Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other (with notes).

## Do you need to build new APIs?

**No new “leave request” APIs are required.** Leave (vacations) is already part of the **Technician Availability** API:

- **GET** `/api/technician/availability` – returns `vacations[]` (each has `id`, `start_date`, `end_date`, **`leave_type`**, **`reason`**). **leave_type** = type (Sick leave, Other, etc.). **reason** = optional text (e.g. "Personal matter").
- **PUT** `/api/technician/availability` – send `vacations` as a JSON array of `{ start_date, end_date, leave_type?, reason? }`. **leave_type** = leave type (e.g. Sick leave, Other). **reason** = optional text (what they write).

**Leave type dropdown:** Use **GET** `/api/technician/leave-types` for the list. Use **GET** `/api/technician/availability` for `data.vacations` only.

**API me:** **leave_type** = type of leave (Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other). **reason** = optional text jo user likhe (e.g. "Personal matter").

So the “leave page” in the app should:

1. **Fetch leave types for the dropdown**  
   Call **GET** `/api/technician/leave-types`. Response shape:

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

3. **For “Other”** (`requires_notes === true`), show a reason/notes field. Send **leave_type**: `"Other"` and **reason**: whatever they wrote.

4. **Save leave** by calling **PUT** `/api/technician/availability` with form-data (or JSON) including:

   - `vacations`: JSON array of `{ start_date, end_date, leave_type?, reason? }`, e.g.  
     `[{ "start_date": "2026-03-01", "end_date": "2026-03-05", "leave_type": "Sick leave", "reason": null }]`  
   - With reason text (e.g. for Other):  
     `{ "start_date": "2026-03-10", "end_date": "2026-03-12", "leave_type": "Other", "reason": "Personal matter" }`

So:

- **Existing APIs:** GET/PUT `/api/technician/availability` (already there).
- **New API added for you:** GET `/api/technician/leave-types` – use this to drive the reason dropdown so the backend owns the list (Sick, Annual, Unpaid, Paternity, Other). No need to hardcode the list in the app.

In **Postman**: see **Module 9 – Technician** → **GET Technician – Leave types** and **GET/PUT Technician – Availability**.

---

## Set Vacation screen (mobile / React Native)

Use this to wire the **Set Vacation** UI to the API.

### Form fields that fit this screen

| Screen field | What to use | What to send in API |
|--------------|-------------|----------------------|
| **Start date** | Date picker → `YYYY-MM-DD` | `start_date` in the vacation object |
| **End date** | Date picker → `YYYY-MM-DD` | `end_date` in the vacation object |
| **Type of leave** | Dropdown from **GET** `/api/technician/leave-types` (labels: Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other). | **`leave_type`** = selected label (e.g. `"Sick leave"`, `"Other"`). |
| **Reason (optional)** | **Show when “Other” is selected** (or optional for any type). Whatever the user writes. | **`reason`** = this text (e.g. "Personal matter"). |

Each vacation payload: `{ start_date, end_date, leave_type, reason? }`. **leave_type** = type of leave. **reason** = optional text.

| UI action | API / behaviour |
|-----------|------------------|
| **Screen load** | **GET** `/api/technician/availability` for `data.vacations` (Your vacations). **GET** `/api/technician/leave-types` for the **Type of leave** dropdown (Sick leave, Annual Leave, etc.). |
| **+ Add vacation** | Build one object `{ start_date, end_date, leave_type, reason? }`. Append to your list, then **PUT** `/api/technician/availability` with the **full** `vacations` array (existing + new). |
| **Done** | Same: **PUT** with the full final `vacations` array. |
| **Your vacations (n)** | From `data.vacations`. Show date range, `leave_type` (e.g. “Sick leave”), and `reason` if present. |
| **Delete (trash)** | Remove that item from the list, then **PUT** with the updated full `vacations` array. |

**Important:** **leave_type** = type of leave. **reason** = optional text (what they write).
