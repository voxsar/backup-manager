#!/bin/bash
set -e

# Copy .env if it doesn't exist
if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

cd /var/www/html

# Generate app key if not set
if grep -q "^APP_KEY=$" .env 2>/dev/null || ! grep -q "^APP_KEY=" .env 2>/dev/null; then
    php artisan key:generate --force
fi

# Create SQLite database if using SQLite and it doesn't exist
DB_CONNECTION=$(grep "^DB_CONNECTION=" .env | cut -d= -f2 | tr -d '[:space:]')
if [ "${DB_CONNECTION}" = "sqlite" ] || [ -z "${DB_CONNECTION}" ]; then
    DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d= -f2 | tr -d '[:space:]')
    DB_DATABASE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    if [ ! -f "${DB_DATABASE}" ]; then
        mkdir -p "$(dirname ${DB_DATABASE})"
        touch "${DB_DATABASE}"
        chown www-data:www-data "${DB_DATABASE}"
    fi
fi

# Run migrations
php artisan migrate --force

# Seed admin user (first time only)
php artisan db:seed --force 2>/dev/null || true

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Start supervisor (manages nginx + php-fpm + scheduler + queue)
exec /usr/bin/supervisord -c /etc/supervisord.conf
