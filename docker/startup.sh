#!/bin/sh
set -e

echo "=== NowHere API Startup ==="

if [ -z "$APP_KEY" ]; then
  echo "[INFO] Generating APP_KEY..."
  export APP_KEY=$(php artisan key:generate --show)
fi

if [ ! -f /var/www/database/database.sqlite ]; then
  echo "[INFO] Creating database..."
  touch /var/www/database/database.sqlite
  chown www-data:www-data /var/www/database/database.sqlite
fi

echo "--- Migrations ---"
php artisan migrate --force --no-interaction || true

echo "--- Cache ---"
php artisan config:cache 2>/dev/null || true
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

echo "=== Starting ==="
exec /usr/bin/supervisord -c /etc/supervisord.conf
