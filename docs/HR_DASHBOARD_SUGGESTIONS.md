# HR Dashboard – Suggestions (Kya aur karna chahiye)

Yeh list un cheezon ko suggest karti hai jo HR dashboard / web me **abhi add ya improve** ki ja sakti hain.

---

## 1. Dashboard pe hi (quick wins)

| Suggestion | Kya karna hai | Fayda |
|------------|----------------|-------|
| **Staff on leave today – count card** | Dashboard ke top cards me ek naya card: "On leave today" (number). Data already `staffOnLeaveToday` me hai, bas count dikhana. | Ek hi jagah pe dekhna ki aaj kitne log leave pe hain. |
| **Technicians & Supervisors count** | Abhi sirf "Total Staff", "New Hires", "Leave Requests", "Area Managers" cards hain. Controller me `totalTechnicians` aur `totalSupervisors` already hain – inko bhi 2 alag cards (ya ek "By role" section) me dikhao. | Role-wise headcount ek nazar me. |
| **Upcoming leaves (next 7 days)** | Naya section: "Upcoming leaves" – agle 7 din me jinke approved leaves start ho rahe hain (name, date, type). | Planning ke liye – next week me kaun absent hoga. |

---

## 2. Employees section

| Suggestion | Kya karna hai | Fayda |
|------------|----------------|-------|
| **Filters** | Employees list pe filters: **Designation**, **Region**, **Role** (Technician/Supervisor), **Status** (Active / On leave). Query params se filter karke same page pe result. | Badi list me quickly right log dhoondhna. |
| **Export (CSV/Excel)** | "Export" button – current (filtered) employees list CSV ya Excel me download. | Records, payroll, external reports ke liye. |
| **Bulk actions (optional)** | Select multiple employees → bulk "Export", ya "Send notification" (agar aage notification system expand karo). | Time bachta hai. |

---

## 3. Leaves section

| Suggestion | Kya karna hai | Fayda |
|------------|----------------|-------|
| **Leave calendar view** | Leaves list ke sath ek "Calendar" tab: month view me dikhao ki kab kaun on leave (approved only). | Visual planning – kis din kitne log absent. |
| **Apply leave on behalf** | Agar HR ko kisi employee ke liye leave daalni ho (e.g. forgot to apply): "Apply leave for employee" form – employee select, dates, type, reason. API me create-on-behalf already ho sakta hai, web form add karo. | HR control aur completeness. |
| **Export leave requests** | Pending/Approved/Rejected leave requests ka export (CSV/Excel) – date range select karke. | Audit, reports, payroll. |
| **Leave balance (agar future me add karo)** | Per employee leave balance (e.g. Annual: 14 left, Sick: 5 left). Iske liye balance table/config chahiye; abhi agar nahi hai to "Phase 2" me rakh sakte ho. | Limit exceed na ho, fair use. |

---

## 4. Reports & visibility

| Suggestion | Kya karna hai | Fayda |
|------------|----------------|-------|
| **Attendance / Who is present today** | Simple list: "Present today" (jo on leave nahi hain) vs "On leave today". Dashboard pe already "Staff on leave today" hai – iske upar "Present" count ya link se employees list (filter: not on leave) open ho. | Aaj kaun available hai ek click me. |
| **Visit assignments – link** | Visit Assignments card me "Unassigned" count ke sath link: "View/Assign" (agar assign karna HR ka kaam hai to HR view, warna supervisor dashboard ka link). | Unassigned visits jaldi resolve karne ke liye. |
| **Monthly/Weekly summary (optional)** | Dashboard pe optional section: "This month – New joins, Leaves approved, Visits completed" jaisi summary. | High-level review. |

---

## 5. Notifications & communication

| Suggestion | Kya karna hai | Fayda |
|------------|----------------|-------|
| **Already good** | Notifications list, mark read, delete selected/all – ye already hain. | — |
| **Broadcast (optional)** | "Notify all staff" ya "Notify by role" (e.g. all technicians) – agar app me push/in-app broadcast chahiye to backend endpoint + simple form. | Announcements, policy updates. |

---

## 6. Technical / consistency

| Suggestion | Kya karna hai | Fayda |
|------------|----------------|-------|
| **Dashboard staff count = API count** | Abhi "Total Staff" `Employee::count()` se hai. API employees list me Employees + technicians/supervisors without Employee record dono aate hain. Dashboard pe bhi same logic use karo taaki number match kare (optional but consistent). | HR ko same number web aur app dono pe. |
| **Spatie role in dashboard** | Technician/Supervisor/Area Manager count me bhi `users.role` + Spatie role dono use karo (jaise HR employees API), taaki seeder-only users bhi count me aaye. | Accurate headcount. |

---

## Priority order (suggested)

1. **Quick:** On leave today **count** card + Technicians/Supervisors **count** cards on dashboard.  
2. **High:** Employees list pe **filters** (designation, region, role, status).  
3. **High:** **Export** employees (CSV).  
4. **Medium:** **Upcoming leaves** (next 7 days) section.  
5. **Medium:** **Apply leave on behalf** (web form).  
6. **Later:** Leave **calendar** view, **leave balance** (agar business need ho), **broadcast** notifications.

Agar tum batao ki pehle kaunse 2–3 points implement karne hain, to unke liye step-by-step code changes bata sakta hoon.
