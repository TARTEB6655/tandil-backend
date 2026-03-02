# Technician Leave Page – Reasons / Leave Types

Your client asked for **leave reasons** on the leave page so the technician can choose from: Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other (with notes).

## Do you need to build new APIs?

**No new “leave request” APIs are required.** Leave (vacations) is already part of the **Technician Availability** API:

- **GET** `/api/technician/availability` – returns `vacations[]` (each has `id`, `start_date`, `end_date`, `reason`, `notes`) **and** `leave_reasons` (the list of selectable leave types).
- **PUT** `/api/technician/availability` – send `vacations` as a JSON array of `{ start_date, end_date, reason?, notes? }`. **Reason** = leave type (e.g. Sick leave, Other). **Notes** = separate optional text (what they want to write).

**GET availability response** – the extra parameter for “all the leaves” (select options) is **`data.leave_reasons`**:

```json
{
  "success": true,
  "data": {
    "is_online": true,
    "auto_accept_jobs": false,
    "working_days": [],
    "working_hours_slots": [],
    "service_area": null,
    "service_areas": [],
    "breaks": [],
    "vacations": [
      { "id": 1, "start_date": "2026-03-01", "end_date": "2026-03-05", "reason": "Sick leave", "notes": null },
      { "id": 2, "start_date": "2026-03-10", "end_date": "2026-03-12", "reason": "Other", "notes": "Personal matter" }
    ],
    "leave_reasons": [
      { "value": "sick", "label": "Sick leave" },
      { "value": "annual", "label": "Annual Leave" },
      { "value": "unpaid", "label": "Unpaid leave" },
      { "value": "paternity", "label": "Paternity Leave" },
      { "value": "other", "label": "Other", "requires_notes": true }
    ]
  }
}
```

Use **`data.leave_reasons`** for the “Reason (optional)” dropdown on the Set Vacation screen; use **`data.vacations`** for “Your vacations” list.

**Notes = separate field.** API me **reason** aur **notes** alag hain. **reason** = leave type (Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other). **notes** = optional text jo user likhe (sirf “Other” par ya kisi bhi leave par extra detail). Dono alag bhejo; combine mat karo.

So the “leave page” in the app should:

1. **Fetch leave types for the dropdown**  
   Either call **GET** `/api/technician/leave-types` or use the **GET** `/api/technician/availability` response, which includes a **`leave_reasons`** array with the same list (so you can get availability and reasons in one call). Response shape for leave types:

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

3. **For “Other”** (`requires_notes === true`), show a notes field. Send **reason**: `"Other"` and **notes**: whatever they wrote (separate fields).

4. **Save leave** by calling **PUT** `/api/technician/availability` with form-data (or JSON) including:

   - `vacations`: JSON array of `{ start_date, end_date, reason?, notes? }`, e.g.  
     `[{ "start_date": "2026-03-01", "end_date": "2026-03-05", "reason": "Annual Leave", "notes": null }]`  
   - With notes (e.g. for Other):  
     `{ "start_date": "2026-03-10", "end_date": "2026-03-12", "reason": "Other", "notes": "Personal matter" }`

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
| **Type of leave** | Dropdown from `data.leave_reasons`. Show labels: Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other. | `reason` = selected label (e.g. `"Sick leave"`, `"Other"`). |
| **Reason (optional)** / **Notes** | **Show when “Other” is selected** (or optional for any type). Whatever the user writes. | **`notes`** = this text (separate from reason). Send as its own field; do not combine with reason. |

Each vacation payload: `{ start_date, end_date, reason, notes? }`. **reason** = leave type. **notes** = separate optional text (e.g. for Other: "Personal matter").

| UI action | API / behaviour |
|-----------|------------------|
| **Screen load** | **GET** `/api/technician/availability`. Use `data.vacations` for “Your vacations” and `data.leave_reasons` for the **Type of leave** dropdown. |
| **+ Add vacation** | Build one object `{ start_date, end_date, reason }` (reason from Type of leave + Reason optional when Other). Append to your list, then **PUT** `/api/technician/availability` with the **full** `vacations` array (existing + new). |
| **Done** | Same: **PUT** with the full final `vacations` array. |
| **Your vacations (n)** | From `data.vacations`. Show date range, `reason` (e.g. “Sick leave”), and `notes` if present. |
| **Delete (trash)** | Remove that item from the list, then **PUT** with the updated full `vacations` array. |

**Important:** Backend has **separate** `reason` and `notes` per vacation. Type of leave → `reason`. User’s written text → `notes`.
