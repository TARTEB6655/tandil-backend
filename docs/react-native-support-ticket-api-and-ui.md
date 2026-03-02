# Support Ticket & Chat Flow – React Native Implementation Guide

This document describes the **APIs** and **UI flow** the React Native app must implement so users can create support tickets and chat with admin. All client APIs use **Bearer token** authentication (logged-in user).

**Base URL:** `{base_url}/api` (e.g. `https://yoursite.com/api`)

---

## Table of Contents

1. [Overview: Client ↔ Admin Flow](#1-overview-client--admin-flow)
2. [Authentication](#2-authentication)
3. [API Reference (Client App)](#3-api-reference-client-app)
4. [Screen-by-Screen UI & API Mapping](#4-screen-by-screen-ui--api-mapping)
5. [Response & Error Format](#5-response--error-format)
6. [Ticket Status & Reply Rules](#6-ticket-status--reply-rules)
7. [Checklist for Developer](#7-checklist-for-developer)

---

## 1. Overview: Client ↔ Admin Flow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  CLIENT APP (User)                    │  ADMIN (Web / separate app)         │
├───────────────────────────────────────┼─────────────────────────────────────┤
│  1. Submit ticket                     │                                     │
│     POST /api/support/tickets         │  → Admin gets notification           │
│                                       │                                     │
│  2. List "My tickets"                 │  2. List all tickets               │
│     GET /api/support/tickets          │     GET /api/admin/support-tickets  │
│                                       │                                     │
│  3. Open one ticket (see full chat)   │  3. Open ticket (see full chat)    │
│     GET /api/support/tickets/{id}     │     GET /api/admin/support-tickets/{id} │
│                                       │                                     │
│  4. User sends reply                  │  4. Admin sends reply               │
│     POST /api/support/tickets/{id}/reply │  POST /api/admin/support-tickets/{id}/reply │
│                                       │                                     │
│  5. User sees admin reply (refresh    │  5. Admin can set status             │
│     GET /api/support/tickets/{id})    │     PUT /api/admin/support-tickets/{id}/status │
└───────────────────────────────────────┴─────────────────────────────────────┘
```

The **same ticket** is viewed by both; **replies** are a single thread. Use `is_admin` on each reply to show "Admin" vs "You" in the chat UI.

---

## 2. Authentication

- **Header:** `Authorization: Bearer {token}`
- **Header:** `Accept: application/json`
- **Header (for POST/PUT):** `Content-Type: application/json`

All support ticket endpoints require the user to be logged in. Unauthorized requests return `401`.

---

## 3. API Reference (Client App)

### 3.1 Submit a new ticket

**When:** User taps "Submit" on the "Create support ticket" / "Contact support" form.

| Item    | Value |
|---------|--------|
| **Method** | `POST` |
| **URL**    | `/support/tickets` |
| **Auth**   | Bearer token required |

**Request body (JSON):**

| Field         | Type   | Required | Max  | Description |
|---------------|--------|----------|------|-------------|
| `subject`     | string | Yes      | 255  | Short subject/title |
| `email`       | string | Yes      | 255  | Valid email (e.g. user's contact email) |
| `description` | string | Yes      | 5000 | Full message/description |

*Backward compatible:* `message` is accepted as an alias for `description`.

**Example request:**
```json
{
  "subject": "Cannot place order",
  "email": "user@example.com",
  "description": "When I tap Place Order the app shows an error and does not proceed."
}
```

**Success response (201):**
```json
{
  "success": true,
  "message": "Support ticket submitted successfully.",
  "data": {
    "id": 2,
    "ticket_number": "TKT-9E25A9DA",
    "subject": "Cannot place order",
    "email": "user@example.com",
    "description": "When I tap Place Order...",
    "status": "open",
    "priority": "medium",
    "category": "general",
    "created_at": "2026-02-27T13:19:30+00:00"
  }
}
```

**What to do in UI:** Save `data.id` and optionally navigate to "My tickets" or directly to the ticket chat screen with this `id`.

---

### 3.2 List my tickets

**When:** "My support tickets" / "My tickets" screen loads or user pulls to refresh.

| Item    | Value |
|---------|--------|
| **Method** | `GET` |
| **URL**    | `/support/tickets` |
| **Auth**   | Bearer token required |

**Query parameters (optional):**

| Parameter  | Type   | Description |
|------------|--------|-------------|
| `status`  | string | Filter: `open`, `in_progress`, `resolved`, `closed` |
| `per_page`| number | Items per page (1–50, default 20) |
| `page`    | number | Page number for pagination |

**Success response (200):**
```json
{
  "success": true,
  "message": "Support tickets retrieved successfully.",
  "data": [
    {
      "id": 2,
      "ticket_number": "TKT-9E25A9DA",
      "subject": "Cannot place order",
      "status": "open",
      "created_at": "2026-02-27T13:19:30+00:00",
      "updated_at": "2026-02-27T13:19:30+00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

**What to do in UI:** Render each item in the list. On tap, navigate to the ticket chat screen with `item.id`.

---

### 3.3 Get one ticket (full chat thread)

**When:** Ticket chat screen opens or user pulls to refresh the conversation.

| Item    | Value |
|---------|--------|
| **Method** | `GET` |
| **URL**    | `/support/tickets/{id}` |
| **Auth**   | Bearer token required |

Replace `{id}` with the ticket ID (from list or from create response).

**Success response (200):**
```json
{
  "success": true,
  "message": "Ticket retrieved successfully.",
  "data": {
    "id": 2,
    "ticket_number": "TKT-9E25A9DA",
    "subject": "Cannot place order",
    "message": "When I tap Place Order the app shows an error.",
    "status": "in_progress",
    "created_at": "2026-02-27T13:19:30+00:00",
    "updated_at": "2026-02-27T14:00:00+00:00",
    "replies": [
      {
        "id": 1,
        "message": "Thank you for contacting us. We are looking into it.",
        "is_admin": true,
        "user_name": "Admin User",
        "created_at": "2026-02-27T13:45:00+00:00"
      },
      {
        "id": 2,
        "message": "I still see the issue after updating the app.",
        "is_admin": false,
        "user_name": "John Doe",
        "created_at": "2026-02-27T14:00:00+00:00"
      }
    ]
  }
}
```

**What to do in UI:** Show ticket header (subject, ticket_number, status) and render `replies` in order as a chat thread. Use `is_admin` to align messages (e.g. admin on one side, user on the other) and show "Admin" vs "You" or the user name.

---

### 3.4 Reply to my ticket (send message in chat)

**When:** User types a message and taps "Send" on the ticket chat screen.

| Item    | Value |
|---------|--------|
| **Method** | `POST` |
| **URL**    | `/support/tickets/{id}/reply` |
| **Auth**   | Bearer token required |

Replace `{id}` with the ticket ID.

**Request body (JSON):**

| Field     | Type   | Required | Max  |
|-----------|--------|----------|------|
| `message` | string | Yes      | 5000 |

**Example request:**
```json
{
  "message": "I still see the issue after updating the app."
}
```

**Success response (201):**
```json
{
  "success": true,
  "message": "Message sent successfully.",
  "data": {
    "id": 2,
    "message": "I still see the issue after updating the app.",
    "is_admin": false,
    "created_at": "2026-02-27T14:00:00+00:00"
  }
}
```

**What to do in UI:** On success, either call `GET /support/tickets/{id}` again to get the full thread or append the returned `data` to the local replies list so the new message appears immediately.

**Important:** If ticket `status` is `resolved` or `closed`, the API returns **422** and the user cannot reply. In the UI, disable the message input and show a note like "This ticket is closed."

---

### 3.5 Help Center (optional)

**When:** User opens the main Help & Support / Contact screen.

| Item    | Value |
|---------|--------|
| **Method** | `GET` |
| **URL**    | `/support/help-center` |
| **Auth**   | Bearer token required |

**Success response (200):**  
Returns a full payload: `heading`, `tagline`, `get_support` options, `contact_info`, `submit_ticket` (endpoint, method, fields for building the submit form), `social_links`, `faqs`. You can use `submit_ticket.fields` to render the "Create ticket" form dynamically.

---

### 3.6 FAQs only (optional)

| Item    | Value |
|---------|--------|
| **Method** | `GET` |
| **URL**    | `/support/faqs` |
| **Auth**   | Bearer token required |

**Success response (200):**  
`data` is an array of `{ id, question, answer, sort_order }`. Use for an FAQ accordion or list.

---

## 4. Screen-by-Screen UI & API Mapping

### Screen 1: Help & Support (entry)

- **Purpose:** Entry to contact support, FAQs, and "My tickets."
- **APIs (optional):** `GET /support/help-center` and/or `GET /support/faqs`.
- **UI:**
  - Link/button: **"Submit a ticket"** → navigate to **Screen 2**.
  - Link/button: **"My support tickets"** → navigate to **Screen 3**.

---

### Screen 2: Submit a ticket (create)

- **Purpose:** User fills form and creates one new ticket.
- **API:** `POST /support/tickets` when user taps Submit.
- **Form fields (required):** Subject, Email, Description (or use `submit_ticket.fields` from help-center).
- **UI:**
  - Subject (text), Email (email), Description (textarea).
  - Submit button.
  - On success (201): show success message, save `data.id`, then navigate to **Screen 3** or directly to **Screen 4** with `data.id`.

---

### Screen 3: My tickets (list)

- **Purpose:** List all tickets for the logged-in user.
- **API:** `GET /support/tickets` on load and on pull-to-refresh. Optional: `?status=...` and `per_page`, `page` for pagination.
- **UI:**
  - List/FlatList of tickets: show at least `ticket_number`, `subject`, `status`, `created_at` (or `updated_at`).
  - Tap row → navigate to **Screen 4** with `ticket.id`.
  - Empty state when `data` is empty.
  - Optional: filter by status (tabs or dropdown).

---

### Screen 4: Ticket chat (conversation with admin)

- **Purpose:** Show one ticket and full thread; let user send replies.
- **APIs:**
  - **Load/refresh thread:** `GET /support/tickets/{id}` when screen opens and on pull-to-refresh.
  - **Send message:** `POST /support/tickets/{id}/reply` when user taps Send.
- **UI:**
  - Header: ticket number (`ticket_number`), subject, status.
  - Chat thread: render `data.replies` in order. For each reply:
    - Use `is_admin` to show "Admin" vs "You" (or current user name) and align bubbles (e.g. admin left, user right).
    - Show `message` and `created_at`.
  - Input: text field + Send button.
  - **If `data.status` is `resolved` or `closed`:** disable input and show text like "This ticket is closed. You cannot send more messages."
  - After successful POST reply: refetch `GET /support/tickets/{id}` or append the new reply from the POST response to the list.

---

## 5. Response & Error Format

**Success (typical):**
```json
{
  "success": true,
  "message": "...",
  "data": { ... }   // or array for list
}
```

**Error (4xx/5xx):**
```json
{
  "success": false,
  "message": "Error description."
}
```

Validation errors (422) may include:
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "subject": ["The subject field is required."],
    "email": ["The email field must be a valid email address."]
  }
}
```

Handle `401` (unauthorized) by redirecting to login and refreshing the token.

---

## 6. Ticket Status & Reply Rules

| Status        | User can reply? | Note |
|---------------|------------------|------|
| `open`        | Yes              | First message from user is the ticket description; further messages use reply API. |
| `in_progress` | Yes              | Admin is handling; user can still send messages. |
| `resolved`    | No               | API returns 422. Disable input and show "Ticket closed." |
| `closed`      | No               | Same as resolved. |

Ticket numbers are unique (e.g. `TKT-9E25A9DA`). Show `ticket_number` in list and chat header so user and admin can reference the same ticket.

---

## 7. Checklist for Developer

- [ ] **Auth:** Send `Authorization: Bearer {token}` and `Accept: application/json` on every request.
- [ ] **Submit ticket screen:** Form with subject, email, description → `POST /support/tickets`. On success, use `data.id` for navigation.
- [ ] **My tickets screen:** `GET /support/tickets` on load and refresh; list items with tap → open chat with `id`.
- [ ] **Ticket chat screen:** `GET /support/tickets/{id}` for thread; render `replies` with `is_admin` for alignment/labels.
- [ ] **Send reply:** `POST /support/tickets/{id}/reply` with `{ "message": "..." }`; then refresh thread or append new reply.
- [ ] **Resolved/closed:** When `status` is `resolved` or `closed`, disable reply input and show closed message.
- [ ] **Errors:** Handle 401 (re-login), 404 (ticket not found), 422 (validation or "cannot reply – ticket closed").
- [ ] **Optional:** Help center screen using `GET /support/help-center`; FAQ screen or section using `GET /support/faqs`.

---

*Backend: Tandil – Support ticket APIs. Last updated: 2026-02-27.*
