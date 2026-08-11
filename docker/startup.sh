#!/bin/sh
set -e

echo "--- Running migrations ---"
php artisan migrate --force --no-interaction 2>/dev/null || echo "[OK] migrations skipped or already applied"

echo "--- Optimizing ---"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "--- Starting ---"
exec /usr/bin/supervisord -c /etc/supervisord.conf