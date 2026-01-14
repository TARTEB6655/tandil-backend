# Production Installation Guide

This guide ensures **ZERO ERRORS** when installing Tandil Backend on a cloud server.

## Quick Installation (Recommended)

### Option 1: Automated Installation Script (PHP)
```bash
php install-production.php
```

### Option 2: Automated Installation Script (Bash)
```bash
chmod +x install-production.sh
./install-production.sh
```

### Option 3: Manual Installation

Follow these steps in order:

## Step-by-Step Manual Installation

### 1. Prerequisites Check

**PHP Requirements:**
- PHP 8.2 or higher
- Required extensions: `pdo`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `json`, `bcmath`, `openssl`, `fileinfo`, `tokenizer`

**Check PHP version:**
```bash
php -v
```

**Check extensions:**
```bash
php -m | grep -E "pdo|mysql|mbstring|xml|json|bcmath|openssl"
```

### 2. Install Dependencies

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Install NPM dependencies (if needed)
npm install
npm run build
```

### 3. Environment Setup

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure .env File

**CRITICAL:** Update these values in `.env`:

```env
# Application
APP_NAME=Tandil
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database (MUST use MySQL, NOT SQLite)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Cache (MUST be file, NOT database)
CACHE_STORE=file

# Session
SESSION_DRIVER=file

# Queue
QUEUE_CONNECTION=database
```

### 5. Create Storage Directories

```bash
# Create necessary directories
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set permissions
chmod -R 775 storage bootstrap/cache
```

### 6. Clear All Caches (IMPORTANT - Do this FIRST)

```bash
# Remove config cache (critical for view config to load)
rm -f bootstrap/cache/config.php

# Clear all caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

**OR use the provided script:**
```bash
php clear-all-cache.php
```

### 7. Create Storage Link

```bash
php artisan storage:link
```

### 8. Run Migrations

```bash
# Create database tables
php artisan migrate --force

# Seed database (optional)
php artisan db:seed --force
```

### 9. Optimize for Production

```bash
# Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Common Issues & Solutions

### Issue 1: "View path not found"
**Solution:**
```bash
# Remove config cache first
rm -f bootstrap/cache/config.php
php artisan config:clear
php artisan view:clear
```

### Issue 2: "Database connection failed"
**Solution:**
- Ensure `DB_CONNECTION=mysql` (NOT sqlite)
- Verify database credentials in `.env`
- Ensure database exists and user has permissions

### Issue 3: "Cache clearing fails"
**Solution:**
- Set `CACHE_STORE=file` in `.env`
- Use `php clear-all-cache.php` script

### Issue 4: "Access denied for user 'root'@'localhost'"
**Solution:**
- Update database credentials in `.env`
- Use correct database username/password from your hosting provider

## Post-Installation Checklist

- [ ] `.env` file configured with correct database credentials
- [ ] `APP_DEBUG=false` in production
- [ ] `CACHE_STORE=file` set in `.env`
- [ ] `DB_CONNECTION=mysql` (not sqlite)
- [ ] Storage directories created with correct permissions
- [ ] Storage symlink created (`public/storage`)
- [ ] All caches cleared
- [ ] Migrations run successfully
- [ ] Production optimization completed (config/route/view cache)

## Verification

Test that everything works:

```bash
# Check routes
php artisan route:list

# Test cache clearing
php artisan optimize:clear

# Check application
php artisan about
```

## Production Optimization Commands

After installation, run these for optimal performance:

```bash
# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear when needed
php artisan optimize:clear
```

## Troubleshooting Scripts

If you encounter errors, use these scripts:

```bash
# Fix view cache issues
php fix-view-cache.php

# Clear all caches (works without database)
php clear-all-cache.php

# Full re-installation
php install-production.php
```

## Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] `.env` file has correct permissions (not world-readable)
- [ ] Database credentials are secure
- [ ] Storage directories have correct permissions
- [ ] Application key is set (`APP_KEY`)

## Support

If you encounter any issues:
1. Check the error message
2. Review this guide
3. Run `php install-production.php` to reinstall
4. Check logs: `storage/logs/laravel.log`

---

**Remember:** Always use MySQL in production, never SQLite!

