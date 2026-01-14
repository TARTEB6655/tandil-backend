# Testing React Native + Laravel Backend Integration

This guide will help you test the complete integration between your React Native frontend and Laravel backend.

## 📋 Current Status

### ✅ Backend (Laravel)
- **Status:** ✅ Ready and configured
- **Location:** `C:\projects\tandil-backend`
- **API Base URL:** `http://localhost:8000` (or your configured URL)
- **All endpoints:** ✅ Verified and matching React Native expectations

### ⚠️ Frontend (React Native)
- **Status:** Needs verification
- **Location:** Your React Native project directory
- **Integration:** Follow the steps below to verify

## 🚀 Step-by-Step Testing Guide

### Step 1: Start the Laravel Backend

Open a terminal in your backend directory and run:

```bash
cd C:\projects\tandil-backend
php artisan serve
```

**Expected Output:**
```
INFO  Server running on [http://127.0.0.1:8000]
```

**✅ Test:** Open `http://localhost:8000/api/health` in your browser
- Should return: `{"status":"API is working"}`

---

### Step 2: Verify Backend API Endpoints

#### Test 1: Health Check (No Auth Required)

```bash
curl http://localhost:8000/api/health
```

**Expected Response:**
```json
{"status":"API is working"}
```

#### Test 2: Register Endpoint (No Auth Required)

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"name\":\"Test User\",\"email\":\"test@example.com\",\"password\":\"password123\",\"password_confirmation\":\"password123\",\"role\":\"client\"}"
```

**Expected Response:**
```json
{
  "status": true,
  "message": "User registered successfully.",
  "token": "1|xxxxxxxxxxxx",
  "role": "client",
  "user": { ... },
  "data": { ... }
}
```

**✅ Save the token** from the response for next tests!

#### Test 3: Login Endpoint (No Auth Required)

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"email\":\"test@example.com\",\"password\":\"password123\"}"
```

**Expected Response:**
```json
{
  "status": true,
  "message": "Login successful.",
  "token": "1|xxxxxxxxxxxx",
  "role": "client",
  "user": { ... },
  "data": { ... }
}
```

#### Test 4: Get User Profile (Auth Required)

Replace `YOUR_TOKEN_HERE` with the token from login:

```bash
curl -X GET http://localhost:8000/api/auth/user \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "status": true,
  "message": "User retrieved successfully.",
  "role": "client",
  "user": { ... },
  "data": { ... }
}
```

#### Test 5: Get Products (No Auth Required)

```bash
curl -X GET http://localhost:8000/api/products \
  -H "Accept: application/json"
```

**Expected Response:**
```json
{
  "status": true,
  "message": "Products retrieved successfully.",
  "data": [ ... ]
}
```

---

### Step 3: Configure React Native Frontend

#### 3.1: Check Your React Native Project Structure

Your React Native project should have:

```
your-react-native-app/
├── src/
│   ├── config/
│   │   └── api.ts                    # API configuration
│   ├── services/
│   │   ├── apiClient.ts              # Axios client
│   │   ├── authService.ts            # Auth service
│   │   ├── productService.ts         # Product service
│   │   ├── orderService.ts           # Order service
│   │   └── userService.ts            # User service
│   └── screens/
│       └── AuthScreen.tsx            # Login/Register screen
```

#### 3.2: Update API Configuration

Open `src/config/api.ts` and verify:

```typescript
export const API_CONFIG = {
  BASE_URL: __DEV__ 
    ? 'http://10.0.2.2:8000'              // Android Emulator (default)
    // ? 'http://localhost:8000'          // iOS Simulator
    // ? 'http://192.168.1.100:8000'      // Physical Device (replace with your IP)
    : 'https://api.yourdomain.com',      // Production

  API_PREFIX: '/api',
  TIMEOUT: 30000,
};
```

**Important:**
- **Android Emulator:** Use `http://10.0.2.2:8000`
- **iOS Simulator:** Use `http://localhost:8000`
- **Physical Device:** Use `http://YOUR_COMPUTER_IP:8000`

**To find your computer's IP (Windows):**
```bash
ipconfig
# Look for "IPv4 Address" under your active network adapter
```

**To find your computer's IP (Mac/Linux):**
```bash
ifconfig
# or
ip addr show
```

#### 3.3: Install Required Dependencies

In your React Native project directory:

```bash
npm install axios @react-native-async-storage/async-storage
# or
yarn add axios @react-native-async-storage/async-storage
```

---

### Step 4: Test React Native Integration

#### Test 1: Test API Connection

Create a test file `src/utils/testConnection.ts`:

```typescript
import { apiClient } from '../services/apiClient';

export const testConnection = async () => {
  try {
    console.log('Testing API connection...');
    const response = await apiClient.get('/health');
    console.log('✅ API Connection Success:', response);
    return true;
  } catch (error: any) {
    console.error('❌ API Connection Failed:', error.message);
    return false;
  }
};
```

Call this in your app's entry point or a test screen.

#### Test 2: Test Login from React Native

In your `AuthScreen.tsx` or test component:

```typescript
import { authService } from '../services/authService';

const testLogin = async () => {
  try {
    console.log('Testing login...');
    const response = await authService.login({
      email: 'test@example.com',
      password: 'password123',
    });
    
    if (response.status && response.token) {
      console.log('✅ Login Success!');
      console.log('Token:', response.token);
      console.log('User:', response.user);
      console.log('Role:', response.role);
      return true;
    } else {
      console.error('❌ Login Failed:', response.message);
      return false;
    }
  } catch (error: any) {
    console.error('❌ Login Error:', error.message);
    return false;
  }
};
```

#### Test 3: Test Get Products from React Native

```typescript
import { productService } from '../services/productService';

const testProducts = async () => {
  try {
    console.log('Testing products...');
    const response = await productService.getProducts();
    
    if (response.status && response.data) {
      console.log('✅ Products Retrieved!');
      console.log('Count:', response.data.length);
      return true;
    } else {
      console.error('❌ Products Failed:', response.message);
      return false;
    }
  } catch (error: any) {
    console.error('❌ Products Error:', error.message);
    return false;
  }
};
```

---

### Step 5: Complete Integration Test Checklist

Use this checklist to verify everything works:

#### Backend Tests (Using curl or Postman)

- [ ] ✅ Health check endpoint works
- [ ] ✅ Register endpoint creates user and returns token
- [ ] ✅ Login endpoint returns token and user data
- [ ] ✅ Get user profile with token works
- [ ] ✅ Get products endpoint works
- [ ] ✅ Get services endpoint works
- [ ] ✅ Error responses have `status: false`

#### React Native Tests (In App)

- [ ] ✅ API client connects to backend
- [ ] ✅ Login screen can authenticate
- [ ] ✅ Token is stored in AsyncStorage
- [ ] ✅ Token is automatically added to requests
- [ ] ✅ Products can be fetched
- [ ] ✅ User profile can be fetched
- [ ] ✅ Error messages display correctly
- [ ] ✅ Loading states work
- [ ] ✅ Network errors are handled

---

### Step 6: Common Issues & Solutions

#### Issue 1: "Network Error" or "Connection Refused"

**Solutions:**
1. ✅ Verify Laravel is running: `php artisan serve`
2. ✅ Check BASE_URL matches your setup:
   - Android Emulator: `http://10.0.2.2:8000`
   - iOS Simulator: `http://localhost:8000`
   - Physical Device: `http://YOUR_IP:8000`
3. ✅ Ensure phone/emulator and computer are on same WiFi (for physical device)
4. ✅ Check firewall isn't blocking port 8000

#### Issue 2: "401 Unauthorized"

**Solutions:**
1. ✅ Verify token is being sent: Check Network tab in React Native debugger
2. ✅ Check token format: Should be `Bearer {token}`
3. ✅ Verify token is stored: `AsyncStorage.getItem('auth_token')`
4. ✅ Check if token expired (try logging in again)

#### Issue 3: "404 Not Found"

**Solutions:**
1. ✅ Verify route exists in `routes/api.php`
2. ✅ Check API_PREFIX matches: Should be `/api`
3. ✅ Verify route is registered: `php artisan route:list | grep api`

#### Issue 4: "CORS Error"

**Solutions:**
1. ✅ React Native doesn't have CORS restrictions (it's not a browser)
2. ✅ If you see CORS errors, check your API configuration
3. ✅ Verify CORS middleware is enabled in `bootstrap/app.php`

#### Issue 5: Response Format Mismatch

**Solutions:**
1. ✅ Verify backend returns `{ status, message, data }` format
2. ✅ Check `ApiResponse` helper is used in controllers
3. ✅ Verify error responses have `status: false`

---

### Step 7: Automated Testing Script

Create a test file `test-integration.js` in your React Native project root:

```javascript
// test-integration.js
const axios = require('axios');

const BASE_URL = 'http://localhost:8000/api';

async function testIntegration() {
  console.log('🧪 Starting Integration Tests...\n');

  // Test 1: Health Check
  try {
    const health = await axios.get(`${BASE_URL}/health`);
    console.log('✅ Health Check:', health.data);
  } catch (error) {
    console.error('❌ Health Check Failed:', error.message);
    return;
  }

  // Test 2: Register
  let token = null;
  try {
    const register = await axios.post(`${BASE_URL}/auth/register`, {
      name: 'Test User',
      email: `test${Date.now()}@example.com`,
      password: 'password123',
      password_confirmation: 'password123',
      role: 'client',
    });
    token = register.data.token;
    console.log('✅ Register:', register.data.message);
  } catch (error) {
    console.error('❌ Register Failed:', error.response?.data || error.message);
    return;
  }

  // Test 3: Login
  try {
    const login = await axios.post(`${BASE_URL}/auth/login`, {
      email: 'test@example.com',
      password: 'password123',
    });
    token = login.data.token;
    console.log('✅ Login:', login.data.message);
  } catch (error) {
    console.error('❌ Login Failed:', error.response?.data || error.message);
  }

  // Test 4: Get User (with token)
  if (token) {
    try {
      const user = await axios.get(`${BASE_URL}/auth/user`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      console.log('✅ Get User:', user.data.message);
    } catch (error) {
      console.error('❌ Get User Failed:', error.response?.data || error.message);
    }
  }

  // Test 5: Get Products
  try {
    const products = await axios.get(`${BASE_URL}/products`);
    console.log('✅ Get Products:', products.data.message);
    console.log('   Products count:', products.data.data?.length || 0);
  } catch (error) {
    console.error('❌ Get Products Failed:', error.response?.data || error.message);
  }

  console.log('\n🎉 Integration Tests Complete!');
}

testIntegration();
```

Run it:
```bash
node test-integration.js
```

---

### Step 8: Using React Native Debugger

1. **Enable Remote Debugging:**
   - Shake device or press `Cmd+D` (iOS) / `Cmd+M` (Android)
   - Select "Debug"

2. **Check Network Requests:**
   - Open Chrome DevTools
   - Go to Network tab
   - Filter by "Fetch/XHR"
   - See all API requests and responses

3. **Check Console Logs:**
   - All `console.log()` statements will appear in Chrome DevTools console

---

### Step 9: Production Testing

Before deploying to production:

1. ✅ Update `BASE_URL` in `src/config/api.ts` to production URL
2. ✅ Test all endpoints with production URL
3. ✅ Verify SSL/HTTPS is working
4. ✅ Test error handling
5. ✅ Test offline scenarios
6. ✅ Test token refresh (if implemented)

---

## 📊 Quick Test Summary

### Backend Only (No Frontend Needed)

```bash
# 1. Start backend
php artisan serve

# 2. Test health
curl http://localhost:8000/api/health

# 3. Test register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","email":"test@example.com","password":"password123","password_confirmation":"password123","role":"client"}'

# 4. Test login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123"}'
```

### Full Integration (React Native + Backend)

1. ✅ Start Laravel: `php artisan serve`
2. ✅ Update `BASE_URL` in React Native config
3. ✅ Run React Native app
4. ✅ Test login from app
5. ✅ Check console logs for API calls
6. ✅ Verify data appears in app

---

## ✅ Success Criteria

Your integration is successful when:

- ✅ Backend API responds to all requests
- ✅ React Native app can connect to backend
- ✅ Login/Register works from React Native
- ✅ Token is stored and sent automatically
- ✅ Products/Services can be fetched
- ✅ User profile can be retrieved
- ✅ Error messages display correctly
- ✅ All API responses match expected format

---

## 🎯 Next Steps After Testing

1. ✅ Fix any issues found during testing
2. ✅ Add error handling for edge cases
3. ✅ Implement loading states
4. ✅ Add offline support (if needed)
5. ✅ Set up production environment
6. ✅ Deploy backend to production server
7. ✅ Update React Native app with production URL
8. ✅ Test production deployment

---

**Need Help?** Check the logs:
- **Laravel logs:** `storage/logs/laravel.log`
- **React Native logs:** Chrome DevTools console
- **Network requests:** Chrome DevTools Network tab

