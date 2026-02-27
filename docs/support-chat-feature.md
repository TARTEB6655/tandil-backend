# Support Chat – Real-time chat for all roles with Admin

## Overview
- **All roles** (Client, Technician, Supervisor, Area Manager, HR, etc.) can start a chat session and get a **token** to share with admin.
- **Admin** can open any chat using that token and reply.
- **Bidirectional:** User sends messages → Admin sees and replies; Admin sends messages → User sees. Same thread for both.
- **Real-time:** Use either **polling** (GET messages every few seconds or with `?after_id=` / `?since=`) or **WebSockets** (Laravel Reverb / Pusher) so both sides see new messages without refresh.

---

## User side (all roles – same APIs for everyone)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/support/chat/sessions` | Start new chat; returns `session_id` + `token` (user shares token with admin) |
| GET | `/api/support/chat/sessions` | List my chat sessions |
| GET | `/api/support/chat/sessions/{id}/messages` | Get messages (support `?after_id=last_id` for polling new messages) |
| POST | `/api/support/chat/sessions/{id}/messages` | Send message to admin |

---

## Admin side

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/admin/support-chat/sessions` | List all chat sessions (all users); filter by status |
| GET | `/api/admin/support-chat/sessions/by-token/{token}` | Open chat by token (user shares token with admin) |
| GET | `/api/admin/support-chat/sessions/{id}/messages` | Get messages (support `?after_id=` for polling) |
| POST | `/api/admin/support-chat/sessions/{id}/messages` | Send reply to user |
| PUT | `/api/admin/support-chat/sessions/{id}/status` | Update status: open, in_progress, resolved, closed |

---

## Real-time options

1. **Polling:**  
   - Frontend calls `GET .../messages?after_id={last_message_id}` every 2–5 seconds.  
   - Backend returns only messages after `after_id`.  
   - Same for user and admin; both see new messages when they poll.

2. **WebSockets (optional later):**  
   - Laravel Reverb / Pusher: channel per session e.g. `support-chat.{session_id}`.  
   - When user or admin sends a message, broadcast to that channel so the other side gets it instantly.

---

## Data model (suggestion)

- **support_chat_sessions:** id, user_id, token (unique), status (open/in_progress/resolved/closed), created_at, updated_at
- **support_chat_messages:** id, session_id, sender_id (user or admin), message, is_admin (boolean), created_at  
  → User and admin both use POST message; `is_admin` tells who sent.

---

## CSV rows (add to `all-dashboards-apis-status-ordered.csv`)

See `support-chat-apis-to-add.csv` for the 9 rows. Close the main CSV file first, then append those lines and renumber SNo 1–341.
