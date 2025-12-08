# Client API Documentation - Tandil Backend

## Overview
This document outlines all available APIs and flows for **Client** users in the Tandil backend.

---

## ✅ **EXISTING CLIENT APIs**

### 1. **Authentication APIs** ✅
**Base URL:** `/api/auth`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/auth/register` | POST | Register new client account | ✅ Implemented |
| `/api/auth/login` | POST | Login client | ✅ Implemented |
| `/api/auth/logout` | POST | Logout (requires auth) | ✅ Implemented |
| `/api/auth/profile` | GET | Get client profile (requires auth) | ✅ Implemented |

**Controller:** `App\Http\Controllers\Auth\AuthController`

---

### 2. **Subscription APIs** ✅
**Base URL:** `/api/subscriptions`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/subscriptions/plans` | GET | **PUBLIC** - Get all subscription plans with prices | ✅ Implemented |
| `/api/subscriptions` | GET | Get client's subscriptions (requires auth) | ✅ Implemented |
| `/api/subscriptions` | POST | Create new subscription (requires auth) | ✅ Implemented |
| `/api/subscriptions/{id}` | GET | Get subscription details (requires auth) | ✅ Implemented |
| `/api/subscriptions/{id}` | PUT | Update subscription (requires auth) | ✅ Implemented |
| `/api/subscriptions/{id}/mark-paid` | POST | Mark subscription as paid (requires auth) | ✅ Implemented |
| `/api/subscriptions/{id}` | DELETE | Cancel subscription (requires auth) | ✅ Implemented |

**Controller:** `App\Http\Controllers\Subscription\SubscriptionController`

**Available Plans:**
- `1_month`: AED 500
- `3_month`: AED 1,450
- `6_month`: AED 2,900
- `12_month`: AED 5,500

**Note:** When a subscription is created, visits are automatically generated via `GenerateVisitsForSubscription` job.

---

### 3. **Visit Tracking APIs** ✅
**Base URL:** `/api/visits`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/visits` | GET | Get all visits for client's subscriptions | ✅ Implemented |
| `/api/visits/{id}` | GET | Get visit details with before/after photos | ✅ Implemented |

**Controller:** `App\Http\Controllers\Visit\VisitController`

**Features:**
- Clients can view all visits from their subscriptions
- Includes before/after photos
- Shows technician, supervisor, area, and status information
- Authorization: Clients can only see their own visits

---

### 4. **Reports APIs** ✅
**Base URL:** `/api/reports`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/reports` | GET | Get all reports for client's visits | ✅ Implemented |
| `/api/reports/{id}` | GET | Get report details | ✅ Implemented |

**Controller:** `App\Http\Controllers\Report\ReportController`

**Features:**
- Clients receive supervisor reports after visit completion
- Reports include recommendations (Fertilizer/Vitamins/Watering/New Soil/Pruning)
- Authorization: Clients can only see reports for their own visits

---

### 5. **Shop/Products APIs** ✅
**Base URL:** `/api/shop`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/shop/products` | GET | **PUBLIC** - Browse all products | ✅ Implemented |
| `/api/shop/products/{id}` | GET | **PUBLIC** - Get product details | ✅ Implemented |
| `/api/shop/cart/add` | POST | Add product to cart (requires auth) | ✅ Implemented |
| `/api/shop/cart` | GET | View cart (requires auth) | ✅ Implemented |
| `/api/shop/cart/{id}` | DELETE | Remove item from cart (requires auth) | ✅ Implemented |
| `/api/shop/checkout` | POST | Checkout and create order (requires auth) | ✅ Implemented |
| `/api/shop/orders` | GET | Get client's orders (requires auth) | ✅ Implemented |
| `/api/shop/orders/{id}` | GET | Get order details (requires auth) | ✅ Implemented |
| `/api/shop/orders/{id}/mark-paid` | POST | Mark order as paid (requires auth) | ✅ Implemented |

**Controllers:**
- `App\Http\Controllers\Shop\ProductController`
- `App\Http\Controllers\Shop\CartController`
- `App\Http\Controllers\Shop\OrderController`

---

### 6. **Tips & Notifications APIs** ✅
**Base URL:** `/api`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/tips` | GET | Get all tips (requires auth) | ✅ Implemented |
| `/api/tips/{id}` | GET | Get tip details (requires auth) | ✅ Implemented |
| `/api/notifications` | GET | Get client notifications (requires auth) | ✅ Implemented |
| `/api/notifications/{id}/mark-read` | POST | Mark notification as read (requires auth) | ✅ Implemented |

**Controllers:**
- `App\Http\Controllers\Tips\TipsController`
- `App\Http\Controllers\Notification\NotificationController`

---

### 7. **Complaints APIs** ✅
**Base URL:** `/api/complaints`

| Endpoint | Method | Description | Status |
|----------|--------|-------------|--------|
| `/api/complaints` | GET | Get client's complaints (requires auth) | ✅ Implemented |
| `/api/complaints` | POST | Create new complaint (requires auth) | ✅ Implemented |
| `/api/complaints/{id}` | GET | Get complaint details (requires auth) | ✅ Implemented |
| `/api/complaints/{id}` | PUT | Update complaint (requires auth) | ✅ Implemented |
| `/api/complaints/{id}` | DELETE | Delete complaint (requires auth) | ✅ Implemented |

**Controller:** `App\Http\Controllers\ComplaintController`

---

## 📋 **CLIENT USER FLOW**

### **Complete Client Journey:**

1. **Registration & Login**
   ```
   POST /api/auth/register → Get token
   POST /api/auth/login → Get token
   ```

2. **View Subscription Plans**
   ```
   GET /api/subscriptions/plans → See available plans (1, 3, 6, 12 months)
   ```

3. **Subscribe to Plan**
   ```
   POST /api/subscriptions
   Body: { "plan": "1_month", "start_date": "2025-01-01" }
   → Subscription created, visits auto-generated
   ```

4. **Track Visits**
   ```
   GET /api/visits → See all visits for subscriptions
   GET /api/visits/{id} → See visit details with before/after photos
   ```

5. **View Reports**
   ```
   GET /api/reports → See supervisor reports with recommendations
   GET /api/reports/{id} → See detailed report
   ```

6. **Shop for Products**
   ```
   GET /api/shop/products → Browse products
   POST /api/shop/cart/add → Add to cart
   GET /api/shop/cart → View cart
   POST /api/shop/checkout → Place order
   GET /api/shop/orders → Track orders
   ```

7. **View Tips & Notifications**
   ```
   GET /api/tips → See agricultural tips
   GET /api/notifications → See notifications
   ```

8. **Submit Complaints**
   ```
   POST /api/complaints → Create complaint
   GET /api/complaints → View complaints
   ```

---

## ⚠️ **MISSING / INCOMPLETE FEATURES**

### 1. **Payment Gateway Integration** ⚠️
- **Status:** Partial implementation
- **Existing:** PayPal service exists (`App\Services\PayPalService`)
- **Missing:** Full payment flow integration
- **Routes:** `/api/auth/payments/paypal/create` exists but needs testing

### 2. **In-App Mailbox** ❌
- **Status:** Not implemented
- **Required:** Backend for client messages/communication
- **Note:** Frontend expects this feature

### 3. **Push Notifications** ❌
- **Status:** Not implemented
- **Required:** FCM/APNS integration for mobile push notifications
- **Note:** Notification model exists but push delivery is missing

### 4. **Visit Reminders** ⚠️
- **Status:** Job exists (`SendVisitReminders`) but needs scheduling
- **Required:** Automated reminders before visits

### 5. **VIP Package Support** ❌
- **Status:** Not implemented
- **Required:** VIP package plans (double pricing) as per requirements
- **Current:** Only standard plans exist

### 6. **1-Day Package** ❌
- **Status:** Not implemented
- **Required:** 1-day package (AED 150) as per requirements
- **Current:** Only monthly plans (1, 3, 6, 12 months) exist

---

## 🔐 **AUTHENTICATION**

All protected endpoints require:
- **Header:** `Authorization: Bearer {token}`
- **Token:** Obtained from `/api/auth/login` or `/api/auth/register`

---

## 📝 **ROUTE SUMMARY**

### **Public Routes (No Auth Required):**
- `GET /api/health`
- `GET /api/subscriptions/plans`
- `GET /api/shop/products`
- `GET /api/shop/products/{id}`
- `POST /api/auth/register`
- `POST /api/auth/login`

### **Protected Routes (Auth Required):**
All other routes require `auth:sanctum` middleware and appropriate role (`client`).

---

## 🎯 **RECOMMENDATIONS**

1. **Create Dedicated Client Controller**
   - Consider creating `App\Http\Controllers\Client\ClientController` for client-specific operations
   - Currently, client functionality is scattered across multiple controllers

2. **Add Client Dashboard API**
   - Create endpoint: `GET /api/client/dashboard`
   - Return: subscriptions summary, upcoming visits, recent reports, notifications count

3. **Implement Missing Features**
   - Payment gateway integration
   - Push notifications
   - In-app mailbox
   - VIP and 1-day packages

4. **Add Visit Booking/Scheduling**
   - Allow clients to request specific visit dates/times
   - Currently, visits are auto-generated based on subscription

---

## 📊 **TESTING**

Test files exist:
- `tests/Feature/UserFlowTest.php` - Tests complete client flow
- `tests/Feature/SubscriptionTest.php` - Tests subscription APIs
- `tests/Feature/FullPurchaseFlowTest.php` - Tests shop flow

---

**Last Updated:** Based on current codebase analysis
**Status:** Core client APIs are implemented, but some features are pending

