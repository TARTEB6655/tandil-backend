#!/bin/bash

# Production Installation Script for Tandil Backend
# This script handles all setup steps to prevent errors

set -e  # Exit on any error

echo "=========================================="
echo "  Tandil Backend - Production Installation"
echo "=========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Check PHP version
echo -e "${GREEN}[1/10]${NC} Checking PHP version..."
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
if [ "$(php -r "echo version_compare('$PHP_VERSION', '8.2', '>=');")" != "1" ]; then
    echo -e "${RED}ERROR: PHP 8.2+ required. Found: $PHP_VERSION${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} PHP $PHP_VERSION detected"

# Step 2: Check required PHP extensions
echo -e "${GREEN}[2/10]${NC} Checking PHP extensions..."
REQUIRED_EXTENSIONS=("pdo" "pdo_mysql" "mbstring" "xml" "ctype" "json" "bcmath" "openssl" "fileinfo" "tokenizer")
MISSING_EXTENSIONS=()
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done
if [ ${#MISSING_EXTENSIONS[@]} -ne 0 ]; then
    echo -e "${RED}ERROR: Missing PHP extensions: ${MISSING_EXTENSIONS[*]}${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} All required extensions installed"

# Step 3: Check Composer
echo -e "${GREEN}[3/10]${NC} Checking Composer..."
if ! command -v composer &> /dev/null; then
    echo -e "${RED}ERROR: Composer not found${NC}"
    exit 1
fi
echo -e "${GREEN}✓${NC} Composer installed"

# Step 4: Install dependencies
echo -e "${GREEN}[4/10]${NC} Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
echo -e "${GREEN}✓${NC} Dependencies installed"

# Step 5: Setup .env file
echo -e "${GREEN}[5/10]${NC} Setting up environment file..."
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${GREEN}✓${NC} Created .env from .env.example"
    else
        echo -e "${YELLOW}⚠${NC} .env.example not found, creating basic .env"
        cat > .env << EOF
APP_NAME=Tandil
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database
EOF
    fi
else
    echo -e "${GREEN}✓${NC} .env file already exists"
fi

# Step 6: Generate application key
echo -e "${GREEN}[6/10]${NC} Generating application key..."
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
    php artisan key:generate --force
    echo -e "${GREEN}✓${NC} Application key generated"
else
    echo -e "${GREEN}✓${NC} Application key already exists"
fi

# Step 7: Create necessary directories
echo -e "${GREEN}[7/10]${NC} Creating storage directories..."
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache
echo -e "${GREEN}✓${NC} Storage directories created"

# Step 8: Clear all caches
echo -e "${GREEN}[8/10]${NC} Clearing all caches..."
# Remove config cache first (critical for view config)
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes.php
rm -f bootstrap/cache/services.php
rm -f bootstrap/cache/packages.php
# Clear view cache files
rm -f storage/framework/views/*.php 2>/dev/null || true
echo -e "${GREEN}✓${NC} Caches cleared"

# Step 9: Create storage link
echo -e "${GREEN}[9/10]${NC} Creating storage symlink..."
if [ ! -L public/storage ]; then
    php artisan storage:link
    echo -e "${GREEN}✓${NC} Storage link created"
else
    echo -e "${GREEN}✓${NC} Storage link already exists"
fi

# Step 10: Final checks
echo -e "${GREEN}[10/10]${NC} Running final checks..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
echo -e "${GREEN}✓${NC} Final checks completed"

echo ""
echo "=========================================="
echo -e "${GREEN}Installation Complete!${NC}"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Edit .env file and set your database credentials:"
echo "   - DB_DATABASE=your_database_name"
echo "   - DB_USERNAME=your_database_user"
echo "   - DB_PASSWORD=your_database_password"
echo "   - APP_URL=your_domain_url"
echo ""
echo "2. Run migrations:"
echo "   php artisan migrate --force"
echo ""
echo "3. Seed database (optional):"
echo "   php artisan db:seed --force"
echo ""
echo "4. Optimize for production:"
echo "   php artisan config:cache"
echo "   php artisan route:cache"
echo "   php artisan view:cache"
echo ""

