# Production Setup Guide

## Database Configuration

On production servers (like Cloudways), you **MUST** use MySQL, not SQLite.

### Update `.env` file on production:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

### Steps to Fix the Current Error:

1. **SSH into your Cloudways server**
2. **Navigate to your project directory:**
   ```bash
   cd /home/1180784.cloudwaysapps.com/ecmbnbvxsm/public_html
   ```

3. **Edit the `.env` file:**
   ```bash
   nano .env
   ```

4. **Update database configuration:**
   - Change `DB_CONNECTION=sqlite` to `DB_CONNECTION=mysql`
   - Add your MySQL credentials:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=your_database_name
     DB_USERNAME=your_database_user
     DB_PASSWORD=your_database_password
     ```

5. **Get your database credentials from Cloudways:**
   - Log into Cloudways Platform
   - Go to your application
   - Click on "Access Details" or "Database" tab
   - Copy the database credentials

6. **Clear all caches (use this if database connection fails):**
   ```bash
   # Option 1: Use the script (works even if DB connection fails)
   php clear-all-cache.php
   
   # Option 2: Use artisan commands (requires DB connection)
   php artisan optimize:clear
   
   # Option 3: Individual commands
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan event:clear
   ```

7. **Run migrations:**
   ```bash
   php artisan migrate --force
   ```

8. **Seed the database (if needed):**
   ```bash
   php artisan db:seed --force
   ```

## Cache Management

### Clear All Caches (Even Without Database Connection):
```bash
php clear-all-cache.php
```

### Using Composer:
```bash
# Clear all caches
composer clear

# Clear all (including file-based)
composer clear-all

# Optimize for production
composer optimize
```

### Using Artisan (Requires Database):
```bash
# Clear all caches
php artisan optimize:clear

# Individual cache clearing
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
```

## Important Notes:

- **Never use SQLite in production** - it's only for local development
- Always use MySQL/MariaDB on production servers
- Make sure your database exists before running migrations
- Keep your `.env` file secure and never commit it to git
- Use `clear-all-cache.php` if database connection fails during cache clearing

