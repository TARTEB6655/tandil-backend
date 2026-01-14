#!/bin/bash

# Fix View Cache Issue
# Run this on production server

echo "=== Fixing View Cache Configuration ==="
echo ""

# 1. Clear config cache first
echo "1. Removing config cache..."
rm -f bootstrap/cache/config.php
echo "   ✅ Config cache removed"
echo ""

# 2. Ensure view cache directory exists
echo "2. Creating view cache directory..."
mkdir -p storage/framework/views
chmod -R 775 storage/framework/views
echo "   ✅ View cache directory ready"
echo ""

# 3. Clear view cache files
echo "3. Clearing view cache files..."
rm -f storage/framework/views/*.php
echo "   ✅ View cache files cleared"
echo ""

# 4. Now run optimize:clear
echo "4. Running optimize:clear..."
php artisan optimize:clear
echo ""

echo "=== Done ==="

