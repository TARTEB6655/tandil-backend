# Technician Leave Page – Reasons / Leave Types

Your client asked for **leave reasons** on the leave page so the technician can choose from: Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other (with notes).

## Do you need to build new APIs?

**No new “leave request” APIs are required.** Leave (vacations) is already part of the **Technician Availability** API:

- **GET** `/api/technician/availability` – returns `vacations[]` (each has `id`, `start_date`, `end_date`, `reason`).
- **PUT** `/api/technician/availability` – send `vacations` as a JSON array of `{ start_date, end_date, reason? }` to set/replace all leave entries.

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

---

## Set Vacation screen (mobile / React Native)

Use this to wire the **Set Vacation** UI to the API.

| UI element | API / behaviour |
|------------|------------------|
| **Screen load** | Call **GET** `/api/technician/availability`. Use `data.vacations` for “Your vacations” and `data.leave_reasons` for the Reason dropdown. |
| **Start date / End date** | Date picker; store as `YYYY-MM-DD` for the new entry. |
| **Reason (optional)** | **Don’t use free text.** Use a **dropdown or picker** with options from `data.leave_reasons`: show `label` (Sick leave, Annual Leave, Unpaid leave, Paternity Leave, Other). If user selects **Other**, show an extra notes field and send `reason: "Other: " + notes`. Otherwise send `reason` as the selected `label` or `value` (e.g. `"Annual Leave"` or `"annual"`). |
| **+ Add vacation** | Append the new `{ start_date, end_date, reason }` to your local list, then call **PUT** `/api/technician/availability` with the **full** `vacations` array (existing + new). The API replaces all vacations in one request. |
| **Done** | Same as above: **PUT** with the full final `vacations` array. |
| **Your vacations (n)** | List comes from `data.vacations` (each has `id`, `start_date`, `end_date`, `reason`). Show e.g. “2026-03-01 – 2026-03-05” and the `reason` (e.g. “Sick leave”, “Leave”, “Other: personal matter”). |
| **Delete (trash icon)** | Remove that item from the list and call **PUT** `/api/technician/availability` with the updated full `vacations` array (without the deleted entry). |

**Important:** The backend stores whatever you send in `reason` (e.g. "Sick leave", "Annual Leave", "Other: my notes"). For the **Reason** field, use the **leave_reasons** list so the technician chooses one of the five options (and notes for Other) instead of free text.
