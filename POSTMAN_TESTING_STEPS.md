# Postman API Testing Guide - Step by Step

This guide will walk you through testing all APIs in the correct order.

## 📋 Prerequisites

1. **Laravel server is running**: `php artisan serve` (usually at `http://127.0.0.1:8000`)
2. **Postman installed**: Download from [postman.com](https://www.postman.com/downloads/)
3. **Import the Collection**: 
   - Open Postman
   - Click "Import" button
   - Select `postman/tandil-backend.postman_collection.json`
   - The collection will appear in your workspace

## 🔧 Initial Setup

1. **Set Base URL Variable**:
   - In Postman, click on the collection name: "Tandil Backend - Flow-Based Collection"
   - Go to "Variables" tab
   - Ensure `base_url` is set to: `http://127.0.0.1:8000`
   - If not, add it manually

2. **Create Environment (Optional but Recommended)**:
   - Click "Environments" in left sidebar
   - Click "+" to create new environment
   - Name it: "Tandil Local"
   - Add variable: `base_url` = `http://127.0.0.1:8000`
   - Add variable: `token` = (leave empty, will be auto-filled)
   - Save and select this environment

---

## ✅ STEP-BY-STEP TESTING GUIDE

### **STEP 1: Health Check** ✅

**Request**: `1. Health Check` (GET)

1. Open the collection folder
2. Click on "1. Health Check"
3. Click "Send"
4. **Expected Response** (200 OK):
   ```json
   {
     "status": "API is working"
   }
   ```
5. ✅ **If successful**: API server is running correctly
6. ❌ **If failed**: Check if Laravel server is running (`php artisan serve`)

---

### **STEP 2: Authentication** 🔐

#### **2.1 Register a New User**

**Request**: `2. Authentication > Register` (POST)

1. Click on "2. Authentication" folder
2. Click on "Register"
3. Check the request body (should have sample data)
4. Click "Send"
5. **Expected Response** (201 Created):
   ```json
   {
     "status": true,
     "message": "User registered successfully",
     "token": "1|xxxxxxxxxxxxx",
     "role": "client",
     "user": {
       "id": 1,
       "name": "John Doe",
       "email": "john@example.com"
     }
   }
   ```
6. ✅ **Important**: Copy the `token` value
7. **Set Token Variable**:
   - In Postman, go to collection "Variables" tab
   - Set `token` = (paste the token you copied)
   - OR: The token should auto-save if test scripts are working

**Alternative**: If you already have a user, skip to Login.

#### **2.2 Login**

**Request**: `2. Authentication > Login` (POST)

1. Click on "Login" request
2. Update the body with your credentials:
   ```json
   {
     "email": "your-email@example.com",
     "password": "your-password"
   }
   ```
3. Click "Send"
4. **Expected Response** (200 OK):
   ```json
   {
     "status": true,
     "message": "Login successful",
     "token": "2|xxxxxxxxxxxxx",
     "role": "client",
     "user": {
       "id": 1,
       "name": "John Doe",
       "email": "your-email@example.com"
     }
   }
   ```
5. ✅ **Copy the new token** and update the `token` variable

---

### **STEP 3: Client Profile** 👤

#### **3.1 Get Profile**

**Request**: `3. Client Profile > Get Profile` (GET)

1. Click on "3. Client Profile" folder
2. Click on "Get Profile"
3. **Verify**: Authorization header should have `Bearer {{token}}`
4. Click "Send"
5. **Expected Response** (200 OK):
   ```json
   {
     "status": true,
     "message": "Profile retrieved successfully",
     "user": {
       "id": 1,
       "name": "John Doe",
       "email": "john@example.com",
       "role": "client"
     }
   }
   ```
6. ✅ **If successful**: Authentication is working

#### **3.2 Logout (Optional)**

**Request**: `3. Client Profile > Logout` (POST)

1. Click on "Logout"
2. Click "Send"
3. **Expected Response** (200 OK):
   ```json
   {
     "status": true,
     "message": "Logged out successfully"
   }
   ```
4. ⚠️ **Note**: After logout, you'll need to login again to test other endpoints

---

### **STEP 4: Subscriptions** 📅

#### **4.1 Get Subscription Plans (Public)**

**Request**: `4. Subscriptions > Plans - Get (Public)` (GET)

1. Click on "4. Subscriptions" folder
2. Click on "Plans - Get (Public)"
3. **Note**: This doesn't require authentication
4. Click "Send"
5. **Expected Response** (200 OK):
   ```json
   {
     "status": true,
     "data": [
       {
         "id": 1,
         "name": "Basic Plan",
         "price": 100,
         "visits_per_month": 2
       }
     ]
   }
   ```
6. ✅ **Copy a plan ID** if you need it for creating subscription

#### **4.2 List My Subscriptions**

**Request**: `4. Subscriptions > Subscriptions - List` (GET)

1. **Make sure you're logged in** (token is set)
2. Click on "Subscriptions - List"
3. Click "Send"
4. **Expected Response** (200 OK): List of your subscriptions

#### **4.3 Create Subscription**

**Request**: `4. Subscriptions > Subscriptions - Create` (POST)

1. Click on "Subscriptions - Create"
2. Check/update the request body:
   ```json
   {
     "plan_id": 1,
     "start_date": "2024-01-01",
     "payment_method": "cash"
   }
   ```
3. Click "Send"
4. **Expected Response** (201 Created):
   ```json
   {
     "status": true,
     "message": "Subscription created successfully",
     "data": {
       "id": 1,
       "plan_id": 1,
       "status": "active"
     }
   }
   ```
5. ✅ **Copy the subscription ID** for next steps

#### **4.4 Get Subscription Details**

**Request**: `4. Subscriptions > Subscriptions - Get Details` (GET)

1. Update `{{subscription_id}}` variable with your subscription ID
2. Click "Send"
3. **Expected Response** (200 OK): Subscription details

#### **4.5 Mark Subscription as Paid**

**Request**: `4. Subscriptions > Subscriptions - Mark Paid` (POST)

1. Click "Send"
2. **Expected Response** (200 OK): Subscription marked as paid

---

### **STEP 5: Visits** 🏡

#### **5.1 List Visits**

**Request**: `5. Visits > Visits - List` (GET)

1. Click on "5. Visits" folder
2. Click on "Visits - List"
3. Click "Send"
4. **Expected Response** (200 OK): List of visits

#### **5.2 Create Visit**

**Request**: `5. Visits > Visits - Create` (POST)

1. Click on "Visits - Create"
2. Check the request body:
   ```json
   {
     "subscription_id": 1,
     "scheduled_date": "2024-01-15",
     "notes": "Regular maintenance visit"
   }
   ```
3. Click "Send"
4. **Expected Response** (201 Created):
   ```json
   {
     "status": true,
     "message": "Visit created successfully",
     "data": {
       "id": 1,
       "subscription_id": 1,
       "status": "scheduled"
     }
   }
   ```
5. ✅ **Copy the visit ID** for next steps

#### **5.3 Get Visit Details**

**Request**: `5. Visits > Visits - Get Details` (GET)

1. Update `{{visit_id}}` variable
2. Click "Send"
3. **Expected Response** (200 OK): Visit details

#### **5.4 Update Visit**

**Request**: `5. Visits > Visits - Update` (PUT)

1. Update the body if needed
2. Click "Send"
3. **Expected Response** (200 OK): Updated visit

#### **5.5 Upload Visit Photo**

**Request**: `5. Visits > Visits - Upload Photo` (POST)

1. Click on "Visits - Upload Photo"
2. In the "Body" tab, select "form-data"
3. For "photo" field, change type to "File" and select an image
4. For "type" field, enter "before" or "after"
5. Click "Send"
6. **Expected Response** (200 OK): Photo uploaded successfully

---

### **STEP 6: Reports** 📊

#### **6.1 List Reports**

**Request**: `6. Reports > Reports - List` (GET)

1. Click on "6. Reports" folder
2. Click on "Reports - List"
3. Click "Send"
4. **Expected Response** (200 OK): List of reports

#### **6.2 Create Report**

**Request**: `6. Reports > Reports - Create` (POST)

1. Click on "Reports - Create"
2. Update the body:
   ```json
   {
     "visit_id": 1,
     "notes": "All trees are healthy",
     "recommendations": "Continue regular maintenance"
   }
   ```
3. Click "Send"
4. **Expected Response** (201 Created): Report created

#### **6.3 Get Report Details**

**Request**: `6. Reports > Reports - Get Details` (GET)

1. Update `{{report_id}}` variable
2. Click "Send"
3. **Expected Response** (200 OK): Report details

---

### **STEP 7: Shop & Orders** 🛒

#### **7.1 Browse Products (Public)**

**Request**: `7. Shop & Orders > Products - List (Public)` (GET)

1. Click on "7. Shop & Orders" folder
2. Click on "Products - List (Public)"
3. **Note**: No authentication needed
4. Click "Send"
5. **Expected Response** (200 OK): List of products
6. ✅ **Copy a product ID** for cart operations

#### **7.2 Get Product Details**

**Request**: `7. Shop & Orders > Products - Get Details (Public)` (GET)

1. Update `{{product_id}}` variable
2. Click "Send"
3. **Expected Response** (200 OK): Product details

#### **7.3 Add to Cart**

**Request**: `7. Shop & Orders > Cart - Add Item` (POST)

1. **Make sure you're logged in**
2. Click on "Cart - Add Item"
3. Update the body:
   ```json
   {
     "product_id": 1,
     "quantity": 2
   }
   ```
4. Click "Send"
5. **Expected Response** (201 Created): Item added to cart

#### **7.4 View Cart**

**Request**: `7. Shop & Orders > Cart - View` (GET)

1. Click on "Cart - View"
2. Click "Send"
3. **Expected Response** (200 OK): Cart contents

#### **7.5 Remove from Cart**

**Request**: `7. Shop & Orders > Cart - Remove Item` (DELETE)

1. Update `{{cart_item_id}}` variable
2. Click "Send"
3. **Expected Response** (200 OK): Item removed

#### **7.6 Checkout**

**Request**: `7. Shop & Orders > Orders - Checkout` (POST)

1. Click on "Orders - Checkout"
2. Update the body:
   ```json
   {
     "payment_method": "cash",
     "address": "123 Main St, Dubai"
   }
   ```
3. Click "Send"
4. **Expected Response** (201 Created): Order created
5. ✅ **Copy the order ID**

#### **7.7 List Orders**

**Request**: `7. Shop & Orders > Orders - List` (GET)

1. Click "Send"
2. **Expected Response** (200 OK): List of your orders

#### **7.8 Get Order Details**

**Request**: `7. Shop & Orders > Orders - Get Details` (GET)

1. Update `{{order_id}}` variable
2. Click "Send"
3. **Expected Response** (200 OK): Order details

#### **7.9 Mark Order as Paid**

**Request**: `7. Shop & Orders > Orders - Mark Paid` (POST)

1. Click "Send"
2. **Expected Response** (200 OK): Order marked as paid

---

### **STEP 8: Technician & Supervisor Routes** 🔧

> **Note**: These routes require specific roles. You need to login as a technician or supervisor user.

#### **8.1 Technician Routes**

**Request**: `8. Technician & Supervisor Routes > Technician - Get Assigned Visits` (GET)

1. **Login as a technician user first**
2. Update the token variable
3. Click on technician routes
4. Test each endpoint:
   - Get Assigned Visits
   - Accept Visit
   - Start Visit
   - Complete Visit
   - Upload Photo

#### **8.2 Supervisor Routes**

**Request**: `8. Technician & Supervisor Routes > Supervisor - List Visits` (GET)

1. **Login as a supervisor user**
2. Update the token variable
3. Test supervisor endpoints:
   - List Visits
   - Review Visit
   - Recommend Products
   - Finalize Report
   - Update Visit Status
   - List Areas
   - List Complaints
   - Escalate Complaint

---

### **STEP 9: Admin & HR Routes** 👨‍💼

> **Note**: These routes require admin role. Login as admin user.

#### **9.1 User Management**

**Request**: `9. Admin & HR Routes > Users - List` (GET)

1. **Login as admin user**
2. Update token variable
3. Test admin endpoints:
   - Users: List, Create, Get, Update, Delete
   - Roles: List, Create
   - HR Employees: List, Create, Get, Update, Delete

---

### **STEP 10: Other Modules** 📦

#### **10.1 Complaints**

**Request**: `10. Other Modules > Complaints - List` (GET)

1. Test complaint endpoints:
   - List Complaints
   - Create Complaint
   - Get Complaint
   - Update Complaint
   - Delete Complaint

#### **10.2 Tips & Notifications**

**Request**: `10. Other Modules > Tips - List` (GET)

1. Test:
   - List Tips
   - Get Tip Details
   - List Notifications
   - Mark Notification as Read

#### **10.3 Categories**

**Request**: `10. Other Modules > Categories - List` (GET)

1. Test CRUD operations for categories

#### **10.4 Areas**

**Request**: `10. Other Modules > Areas - List` (GET)

1. **Note**: Requires area_manager role
2. Test area management endpoints

#### **10.5 Payment (PayPal)**

**Request**: `10. Other Modules > Payment (PayPal) - Create Order` (POST)

1. Test PayPal integration (if configured)

---

## 🔍 Testing Tips

### **Common Issues & Solutions**

1. **401 Unauthorized**:
   - Check if token is set correctly
   - Token might have expired - login again
   - Check Authorization header: `Bearer {{token}}`

2. **403 Forbidden**:
   - Your user role doesn't have permission
   - Login with correct role (admin, technician, supervisor, etc.)

3. **422 Validation Error**:
   - Check request body format
   - Ensure all required fields are provided
   - Check field types (string, integer, date, etc.)

4. **404 Not Found**:
   - Check if the resource ID exists
   - Verify the endpoint URL is correct
   - Check if the route is registered in `routes/api.php`

5. **500 Server Error**:
   - Check Laravel logs: `storage/logs/laravel.log`
   - Verify database connection
   - Check if migrations are run: `php artisan migrate`

### **Using Variables**

- `{{base_url}}` - API base URL (auto-set)
- `{{token}}` - Authentication token (set after login)
- `{{subscription_id}}` - Subscription ID (set after creating subscription)
- `{{visit_id}}` - Visit ID (set after creating visit)
- `{{product_id}}` - Product ID (set after listing products)
- `{{order_id}}` - Order ID (set after checkout)
- `{{complaint_id}}` - Complaint ID (set after creating complaint)

### **Quick Test Checklist**

- [ ] Health Check works
- [ ] Can register new user
- [ ] Can login
- [ ] Can get profile
- [ ] Can create subscription
- [ ] Can create visit
- [ ] Can browse products
- [ ] Can add to cart
- [ ] Can checkout
- [ ] Can create complaint
- [ ] Can view reports

---

## 📝 Notes

1. **Token Expiration**: Tokens may expire. If you get 401 errors, login again.
2. **Role-Based Access**: Some endpoints require specific roles. Make sure you're logged in with the correct user.
3. **Test Data**: The collection includes sample data, but you may need to adjust IDs based on your database.
4. **Environment Variables**: Consider creating separate environments for different stages (local, staging, production).

---

## 🎯 Next Steps

After testing all endpoints:

1. **Check Response Formats**: Ensure all responses match the expected JSON structure
2. **Test Error Cases**: Try invalid data to test validation
3. **Test Edge Cases**: Test with missing data, invalid IDs, etc.
4. **Performance Testing**: Test with large datasets if needed
5. **Integration Testing**: Test complete user flows (register → subscribe → visit → report)

---

**Happy Testing! 🚀**

