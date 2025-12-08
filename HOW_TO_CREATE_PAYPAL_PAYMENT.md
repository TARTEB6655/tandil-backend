# How to Create PayPal Payment

## ❌ Wrong Request Format

You're sending:
```json
{
  "amount": 100.00,
  "currency": "USD",
  "return_url": "http://localhost:3000/success",
  "cancel_url": "http://localhost:3000/cancel"
}
```

**This won't work!** The controller expects `type` and `id`, not `amount` directly.

---

## ✅ Correct Request Format

### Option 1: Pay for Subscription

```
POST /api/auth/payments/paypal/create
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json

{
  "type": "subscription",
  "id": 121,
  "currency": "USD",
  "return_url": "http://localhost:3000/success",
  "cancel_url": "http://localhost:3000/cancel"
}
```

**What happens:**
1. Controller finds subscription with ID 121
2. Gets amount from `subscription.amount`
3. Creates PayPal order with that amount

### Option 2: Pay for Order

```
POST /api/auth/payments/paypal/create
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json

{
  "type": "order",
  "id": 45,
  "currency": "USD",
  "return_url": "http://localhost:3000/success",
  "cancel_url": "http://localhost:3000/cancel"
}
```

**What happens:**
1. Controller finds order with ID 45
2. Gets amount from `order.total_amount`
3. Creates PayPal order with that amount

---

## 📋 Required Fields

| Field | Required | Description |
|-------|----------|-------------|
| `type` | ✅ Yes | Must be `"subscription"` or `"order"` |
| `id` | ✅ Yes | Subscription ID or Order ID |
| `currency` | ❌ No | Defaults to `"USD"` |
| `return_url` | ❌ No | Defaults to `url('/')` |
| `cancel_url` | ❌ No | Defaults to `url('/')` |

**Note:** You cannot send `amount` directly. The amount is taken from the subscription or order.

---

## 🎯 Step-by-Step Example

### Step 1: Get a Subscription ID

First, get your subscription:
```
GET /api/subscriptions/{id}
Authorization: Bearer {token}
```

Response:
```json
{
    "status": true,
    "data": {
        "id": 121,
        "amount": 500,
        "plan": "1_month",
        ...
    }
}
```

### Step 2: Create PayPal Payment

Use the subscription ID from Step 1:
```
POST /api/auth/payments/paypal/create
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "subscription",
  "id": 121,
  "currency": "USD",
  "return_url": "http://localhost:3000/success",
  "cancel_url": "http://localhost:3000/cancel"
}
```

**Response:**
```json
{
    "status": true,
    "data": {
        "id": "PAYPAL_ORDER_ID",
        "status": "CREATED",
        "links": [
            {
                "href": "https://www.sandbox.paypal.com/checkoutnow?token=...",
                "rel": "approve",
                "method": "GET"
            }
        ]
    }
}
```

### Step 3: Redirect User to PayPal

Use the `href` from the response to redirect the user to PayPal checkout.

---

## ⚠️ Common Mistakes

### Mistake 1: Sending `amount` directly
```json
{
  "amount": 100.00  ❌ Wrong!
}
```

**Fix:** Use `type` and `id` instead:
```json
{
  "type": "subscription",
  "id": 121  ✅ Correct!
}
```

### Mistake 2: Missing `type` field
```json
{
  "id": 121  ❌ Missing type!
}
```

**Fix:** Add `type`:
```json
{
  "type": "subscription",
  "id": 121  ✅ Correct!
}
```

### Mistake 3: Using wrong `type` value
```json
{
  "type": "payment",  ❌ Wrong value!
  "id": 121
}
```

**Fix:** Use `"subscription"` or `"order"`:
```json
{
  "type": "subscription",  ✅ Correct!
  "id": 121
}
```

---

## 🔍 How It Works

1. **You provide:** `type` (subscription/order) and `id` (subscription_id/order_id)
2. **Controller finds:** The subscription or order from database
3. **Controller gets:** Amount from `subscription.amount` or `order.total_amount`
4. **Controller creates:** PayPal order with that amount
5. **Controller saves:** PayPal order ID in `subscription.payment_reference` or `order.payment_reference`
6. **Controller returns:** PayPal order details with approval URL

---

## 📝 Summary

- ❌ **Don't send:** `amount` directly
- ✅ **Do send:** `type` and `id`
- ✅ **Amount is:** Automatically taken from subscription/order
- ✅ **Purpose:** Link payment to existing subscription/order

---

## 🎯 Quick Test

1. **Get your subscription ID:**
   ```
   GET /api/subscriptions
   → Find subscription ID (e.g., 121)
   ```

2. **Create PayPal payment:**
   ```
   POST /api/auth/payments/paypal/create
   {
     "type": "subscription",
     "id": 121
   }
   ```

3. **Use the approval URL** from response to redirect user to PayPal

