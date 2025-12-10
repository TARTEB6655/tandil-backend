# React Native Frontend Connection Guide

This guide explains how to connect your React Native frontend to this Laravel backend.

## 📋 Prerequisites

- Laravel backend is running and accessible
- React Native app is set up
- Both are on the same network (for development)

## 🔧 Backend Configuration

### Step 1: CORS Configuration

This Laravel backend uses Laravel Sanctum for API authentication. CORS is handled automatically by Laravel's built-in CORS middleware. For React Native apps, you may need to configure it in `bootstrap/app.php` or create a CORS config file.

**For Laravel 11**, add this to `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);
})
```

Or install and configure `fruitcake/laravel-cors` package if needed.

### Step 2: Sanctum Configuration

The backend uses Laravel Sanctum for token-based authentication. Make sure your `.env` file has:

```env
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,::1
```

For React Native, you don't need stateful domains since it uses token authentication.

### Step 3: API Base URL

Your React Native app should connect to:

- **Development (Emulator)**: `http://10.0.2.2:8000` (Android) or `http://localhost:8000` (iOS)
- **Development (Physical Device)**: `http://YOUR_COMPUTER_IP:8000`
- **Production**: Your production API URL

## 📱 Frontend Configuration

### Step 1: Update API Configuration

In your React Native app, open `src/config/api.ts` and update:

```typescript
export const API_CONFIG = {
  BASE_URL: __DEV__ 
    ? 'http://10.0.2.2:8000'              // Android Emulator
    // ? 'http://localhost:8000'          // iOS Simulator
    // ? 'http://192.168.1.100:8000'      // Physical Device (replace with your IP)
    : 'https://api.yourdomain.com',      // Production

  API_PREFIX: '/api',
  TIMEOUT: 30000,
};
```

### Step 2: Authentication Flow

The backend expects the following authentication flow:

1. **Login**: `POST /api/auth/login`
   ```json
   {
     "email": "user@example.com",
     "password": "password123"
   }
   ```
   Response:
   ```json
   {
     "status": true,
     "message": "Login successful.",
     "token": "1|xxxxxxxxxxxx",
     "role": "client",
     "user": { ... }
   }
   ```

2. **Register**: `POST /api/auth/register`
   ```json
   {
     "name": "John Doe",
     "email": "john@example.com",
     "password": "password123",
     "password_confirmation": "password123",
     "phone": "+971501234567",
     "role": "client"
   }
   ```

3. **Get User**: `GET /api/auth/user` or `GET /api/auth/profile`
   - Requires: `Authorization: Bearer {token}` header

4. **Logout**: `POST /api/auth/logout`
   - Requires: `Authorization: Bearer {token}` header

## 🔗 Available API Endpoints

### Authentication Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/api/auth/register` | Register new user | No |
| POST | `/api/auth/login` | Login user | No |
| POST | `/api/auth/logout` | Logout user | Yes |
| GET | `/api/auth/user` | Get current user | Yes |
| GET | `/api/auth/profile` | Get current user profile | Yes |

### Product Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/products` | List all products | No |
| GET | `/api/products/{id}` | Get product by ID | No |
| GET | `/api/products/search?q=term` | Search products | No |
| GET | `/api/products/categories` | Get all categories | No |
| GET | `/api/products/category/{id}` | Get products by category | No |

**Note**: Products are also available at `/api/shop/products` (same endpoints)

### Service Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/services` | List all services | No |
| GET | `/api/services/{id}` | Get service by ID | No |
| GET | `/api/services/categories` | Get service categories | No |
| GET | `/api/services/category/{id}` | Get services by category | No |

**Note**: Services currently use Categories model. You may need to create a Service model if you have a separate services table.

### Order Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/orders` | List user orders | Yes |
| GET | `/api/orders/{id}` | Get order by ID | Yes |
| POST | `/api/orders` | Create new order | Yes |
| PUT | `/api/orders/{id}` | Update order | Yes |
| POST | `/api/orders/{id}/cancel` | Cancel order | Yes |
| GET | `/api/orders/{id}/track` | Track order | Yes |
| POST | `/api/orders/{id}/rate` | Rate order | Yes |

**Note**: Orders are also available at `/api/shop/orders` (same endpoints)

### User Profile Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| GET | `/api/user/profile` | Get user profile | Yes |
| PUT | `/api/user/profile` | Update user profile | Yes |
| GET | `/api/user/addresses` | Get user addresses | Yes |
| POST | `/api/user/addresses` | Create address | Yes |
| PUT | `/api/user/addresses/{id}` | Update address | Yes |
| DELETE | `/api/user/addresses/{id}` | Delete address | Yes |
| GET | `/api/user/loyalty` | Get loyalty points | Yes |
| GET | `/api/user/notifications` | Get notifications | Yes |
| POST | `/api/user/notifications/{id}/read` | Mark notification as read | Yes |
| POST | `/api/user/notifications/read-all` | Mark all as read | Yes |

## 🔐 Authentication Token Management

The backend uses Laravel Sanctum tokens. Your React Native app should:

1. **Store the token** after login:
   ```typescript
   // After successful login
   await AsyncStorage.setItem('auth_token', response.token);
   ```

2. **Include token in requests**:
   ```typescript
   headers: {
     'Authorization': `Bearer ${token}`,
     'Content-Type': 'application/json',
     'Accept': 'application/json'
   }
   ```

3. **Handle token expiration**:
   - On 401 Unauthorized, redirect to login
   - Clear stored token and user data

## 📝 Response Format

All API responses follow this format:

**Success Response:**
```json
{
  "status": true,
  "message": "Success message",
  "data": { ... }
}
```

**Error Response:**
```json
{
  "status": false,
  "message": "Error message",
  "errors": {
    "field": ["Error message"]
  }
}
```

## 🛠️ Testing the Connection

### 1. Test Health Endpoint

```bash
curl http://localhost:8000/api/health
```

Expected response:
```json
{
  "status": "API is working"
}
```

### 2. Test Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

### 3. Test Authenticated Endpoint

```bash
curl http://localhost:8000/api/auth/user \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

## 🐛 Troubleshooting

### Network Error / Connection Refused

1. **Check if Laravel is running**:
   ```bash
   php artisan serve
   ```

2. **Verify the BASE_URL**:
   - Android Emulator: `http://10.0.2.2:8000`
   - iOS Simulator: `http://localhost:8000`
   - Physical Device: Use your computer's IP address

3. **Check firewall settings** - Make sure port 8000 is not blocked

4. **Verify network** - Ensure phone and computer are on the same WiFi network

### 401 Unauthorized

- Token may be expired or invalid
- Check if token is being sent correctly in headers
- Verify token format: `Bearer {token}` (with space after Bearer)
- Check if user account is active

### 422 Validation Error

- The API returns validation errors in the `errors` object
- Each field has an array of error messages
- Display field-specific errors to users

### 404 Not Found

- Verify the API endpoint exists in `routes/api.php`
- Check the API_PREFIX matches (`/api`)
- Ensure the route is registered correctly

### CORS Issues

- For React Native, CORS is not typically an issue (it's a mobile app, not a browser)
- If you see CORS errors, check `bootstrap/app.php` middleware configuration
- Ensure `HandleCors` middleware is enabled for API routes

## 📚 Additional Resources

- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Laravel API Documentation](https://laravel.com/docs/api)
- [React Native Networking](https://reactnative.dev/docs/network)

## 🔄 Next Steps

1. Update `src/config/api.ts` in your React Native app with the correct BASE_URL
2. Test authentication flow (login/register)
3. Test product listing
4. Test order creation
5. Implement error handling
6. Add loading states
7. Test on physical device

## ⚠️ Important Notes

1. **Password Reset**: The endpoints `/api/auth/forgot-password`, `/api/auth/verify-otp`, and `/api/auth/reset-password` are placeholders and return 501 (Not Implemented). Implement these if needed.

2. **Addresses**: The address endpoints (`/api/user/addresses/*`) are placeholders. Implement if you have an addresses table.

3. **Loyalty Points**: The loyalty endpoint returns mock data. Implement if you have a loyalty system.

4. **Order Tracking/Rating**: Some order endpoints (`/cancel`, `/track`, `/rate`) are placeholders. Implement as needed.

5. **Services**: Currently uses Categories model. Create a Service model if you have a separate services table.

## ✅ Checklist

- [ ] Backend is running (`php artisan serve`)
- [ ] API health check works (`/api/health`)
- [ ] CORS is configured (if needed)
- [ ] React Native app BASE_URL is updated
- [ ] Authentication flow works (login/register)
- [ ] Token is stored and sent with requests
- [ ] Products can be fetched
- [ ] Orders can be created
- [ ] Error handling is implemented
- [ ] Tested on physical device

---

**Need Help?** Check the Laravel logs at `storage/logs/laravel.log` for detailed error messages.

