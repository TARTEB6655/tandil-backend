# Visit Status When Created by Admin

## 📋 Answer

**Default Status:** `"pending"`

When an admin creates a visit, the status is **`"pending"`** by default, unless explicitly specified.

---

## 🎯 Status Options

### Valid Statuses:
1. `"pending"` - Default status when created
2. `"scheduled"` - Visit is scheduled
3. `"in_progress"` - Visit is in progress
4. `"completed"` - Visit is completed
5. `"approved"` - Visit is approved by supervisor
6. `"rejected"` - Visit is rejected by supervisor

---

## 📝 Code Reference

**From `VisitController@store`:**

```php
$visit = Visit::create([
    'subscription_id' => $request->subscription_id,
    'technician_id' => $request->technician_id,
    'supervisor_id' => $request->supervisor_id,
    'area_id' => $request->area_id,
    'scheduled_date' => $request->scheduled_date,
    'status' => $request->status ?? 'pending',  // ← Defaults to "pending"
]);
```

**Validation:**
```php
'status' => 'nullable|string|in:pending,scheduled,in_progress,completed,approved,rejected',
```

---

## 🎯 Examples

### Example 1: Admin Creates Visit (No Status Specified)

**Request:**
```json
POST /api/visits
Authorization: Bearer {{admin_token}}

{
  "subscription_id": 123,
  "technician_id": 68,
  "scheduled_date": "2025-01-20"
  // No "status" field
}
```

**Result:**
```json
{
  "status": true,
  "message": "Visit created successfully",
  "data": {
    "id": 62,
    "subscription_id": 123,
    "technician_id": 68,
    "scheduled_date": "2025-01-20",
    "status": "pending"  ← Default status
  }
}
```

---

### Example 2: Admin Creates Visit with Specific Status

**Request:**
```json
POST /api/visits
Authorization: Bearer {{admin_token}}

{
  "subscription_id": 123,
  "technician_id": 68,
  "scheduled_date": "2025-01-20",
  "status": "scheduled"  ← Explicitly set
}
```

**Result:**
```json
{
  "status": true,
  "message": "Visit created successfully",
  "data": {
    "id": 62,
    "subscription_id": 123,
    "technician_id": 68,
    "scheduled_date": "2025-01-20",
    "status": "scheduled"  ← As specified
  }
}
```

---

### Example 3: Admin Creates Visit with All Status Options

**Admin can set any valid status:**

```json
// Status: pending
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "pending"
}

// Status: scheduled
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "scheduled"
}

// Status: in_progress
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "in_progress"
}

// Status: completed
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "completed"
}

// Status: approved
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "approved"
}

// Status: rejected
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "rejected"
}
```

**All of these are valid!** Admin has full control.

---

## 📊 Status Flow

### Typical Visit Lifecycle:

```
pending → scheduled → in_progress → completed → approved/rejected
```

### Who Can Set Each Status:

| Status | Who Can Set |
|--------|------------|
| `pending` | Admin, Client |
| `scheduled` | Admin, Client |
| `in_progress` | Admin, Technician (assigned) |
| `completed` | Admin, Technician (assigned) |
| `approved` | Admin, Supervisor (area supervised) |
| `rejected` | Admin, Supervisor (area supervised) |

**Note:** Admin can set **any status** at any time!

---

## 🔐 Admin Privileges

### Admin Can:
- ✅ Create visit with any status
- ✅ Set status to any valid value
- ✅ Change status at any time
- ✅ Override status restrictions

### Other Roles:
- **Client:** Can only set `pending` or `scheduled`
- **Technician:** Can only set `in_progress` or `completed` (for assigned visits)
- **Supervisor:** Can only set `approved` or `rejected` (for supervised areas)

**Admin has no restrictions!**

---

## 💡 Best Practices

### Recommended Status When Creating:

1. **New Visit (Not Yet Scheduled):**
   ```json
   {
     "status": "pending"
   }
   ```
   Or omit status (defaults to `pending`)

2. **Pre-Scheduled Visit:**
   ```json
   {
     "status": "scheduled"
   }
   ```

3. **Visit Already in Progress:**
   ```json
   {
     "status": "in_progress"
   }
   ```

4. **Visit Already Completed:**
   ```json
   {
     "status": "completed"
   }
   ```

---

## ✅ Summary

**When Admin Creates Visit:**

1. **Default Status:** `"pending"` (if not specified)
2. **Can Set Any Status:** Admin can explicitly set any valid status
3. **Valid Statuses:**
   - `pending` (default)
   - `scheduled`
   - `in_progress`
   - `completed`
   - `approved`
   - `rejected`

**Example:**
```json
POST /api/visits
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20"
  // Status defaults to "pending"
}

// Or explicitly set:
{
  "subscription_id": 123,
  "scheduled_date": "2025-01-20",
  "status": "scheduled"  // Any valid status
}
```

---

## 🎯 Quick Reference

| Scenario | Status | Notes |
|----------|--------|-------|
| **Default (no status)** | `pending` | Most common |
| **Pre-scheduled** | `scheduled` | Visit is scheduled |
| **Already started** | `in_progress` | Visit in progress |
| **Already done** | `completed` | Visit completed |
| **Approved** | `approved` | Supervisor approved |
| **Rejected** | `rejected` | Supervisor rejected |

**Admin has full control over status!** 🚀

