# Delete Account API (Apple Guideline 5.1.1)

Mobile app **Profile → Delete Account** should call this API after the user confirms deletion.

## Endpoints

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/api/user/delete-account` | Recommended for mobile |
| `DELETE` | `/api/user/account` | Same handler |
| `POST` | `/api/auth/delete-account` | Alias (requires Bearer token) |
| `DELETE` | `/api/auth/account` | Alias |

**Auth:** `Authorization: Bearer {token}` (Sanctum)  
**Role:** `client` only

## Request body (JSON)

```json
{
  "confirmation": "DELETE",
  "password": "current-password"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `confirmation` | Yes | Must be exactly `DELETE` |
| `password` | Email/password accounts | Current password |
| `password` | Google / Apple sign-in | Omit (confirmation only) |

## Success (200)

```json
{
  "success": true,
  "message": "Your account and personal data have been permanently deleted."
}
```

After success: clear local token/session and navigate to login/welcome.

## Errors

| Code | When |
|------|------|
| 401 | Missing/invalid token |
| 403 | Non-client role |
| 422 | Wrong password, missing `confirmation`, or validation error |

## What is deleted

- User record, profile photo, Sanctum tokens  
- Cart, saved addresses, payment methods, wallet credits, checkout session data  
- Notifications, subscriptions, complaints (DB cascades)  
- Orders are **kept** for business records but **unlinked** (`user_id` null); guest PII columns on those orders cleared  

## Profile settings discovery

`GET /api/client/settings/sections` includes:

```json
{
  "id": "delete_account",
  "title": "Delete Account",
  "path": "/api/user/delete-account",
  "method": "POST"
}
```

## App UI flow (for App Store review)

1. Login or register  
2. Profile → **Delete Account**  
3. Warning + type `DELETE` (+ password if email login)  
4. Confirm → call API → logout → show logged-out state  
