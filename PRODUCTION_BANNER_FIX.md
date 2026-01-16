# Production Server Fix for /admin/banners Route

## Issue
The `/admin/banners` page works on localhost but shows login page on production server.

## Root Cause
Likely causes:
1. **Route/Config cache not cleared** on production
2. **User authentication/authorization** issue
3. **Middleware not properly registered**

## Solution

### Step 1: Clear All Caches on Production Server

SSH into your production server and run:

```bash
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear
```

Or run the fix script:
```bash
php fix_production_banners.php
```

### Step 2: Verify Route Registration

```bash
php artisan route:list --path=admin/banners
```

You should see:
- `GET|HEAD admin/banners` → `admin.banners.index`

### Step 3: Check Authentication

1. **Verify you are logged in** as an admin user
2. **Check user has 'admin' role**:
   ```sql
   SELECT * FROM users WHERE email = 'your-admin-email@example.com';
   SELECT * FROM model_has_roles WHERE model_id = [your_user_id];
   ```

3. **Verify role exists**:
   ```sql
   SELECT * FROM roles WHERE name = 'admin';
   ```

### Step 4: Check Middleware

The `role:admin` middleware should be registered in `bootstrap/app.php`:
```php
'role' => \App\Http\Middleware\CheckRole::class,
```

### Step 5: Verify Environment

Check `.env` file on production:
- `APP_URL` should match your production domain
- `APP_ENV` should be set correctly
- Database connection is correct

## Quick Test

After clearing caches, try:
1. Logout and login again
2. Access: `https://phpstack-1180784-6050385.cloudwaysapps.com/admin/banners`
3. Check browser console for errors
4. Check server logs: `storage/logs/laravel.log`

## Expected Behavior

- If logged in as admin: Should show banner management page
- If not logged in: Should redirect to login page
- If logged in but not admin: Should show 403 Forbidden

## Route Configuration

The route is correctly configured in `routes/web.php`:
```php
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('banners', BannerController::class);
    });
```

Controller has additional middleware:
```php
public function __construct()
{
    $this->middleware('role:admin');
}
```

## If Still Not Working

1. Check `storage/logs/laravel.log` for errors
2. Verify `CheckRole` middleware exists: `app/Http/Middleware/CheckRole.php`
3. Check if Spatie Permission package is installed
4. Verify database migrations are run: `php artisan migrate:status`

