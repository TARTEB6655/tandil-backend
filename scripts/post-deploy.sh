#!/usr/bin/env bash
# Run this script on the server after every deployment.
# Fixes 404 "Endpoint not found" for API routes (e.g. GET /api/admin/products/{id})
# by clearing stale route/config cache and rebuilding.

set -e
cd "$(dirname "$0")/.."

echo "Clearing caches..."
php artisan optimize:clear

echo "Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache 2>/dev/null || true

echo "Done. New routes (e.g. GET /api/admin/products/{id}) are now active."
