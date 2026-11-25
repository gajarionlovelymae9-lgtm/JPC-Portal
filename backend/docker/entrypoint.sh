#!/bin/sh
set -e

PORT="${PORT:-80}"
echo "Configuring Nginx to listen on port ${PORT}..."
sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/conf.d/default.conf
sed -i "s/listen \[::\]:80;/listen \[::\]:${PORT};/g" /etc/nginx/conf.d/default.conf

# Ensure storage and cache permissions
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/www/html/database

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Check or initialize SQLite database
DB_PATH="/var/www/html/database/database.sqlite"
IS_NEW_DB=false

if [ ! -f "$DB_PATH" ]; then
    echo "Creating SQLite database at $DB_PATH..."
    touch "$DB_PATH"
    chown www-data:www-data "$DB_PATH"
    chmod 664 "$DB_PATH"
    IS_NEW_DB=true
fi

# Clear stale caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Run migrations and seed data
if [ "$IS_NEW_DB" = true ]; then
    echo "Running initial migrations and seeding database..."
    php artisan migrate --force
    php artisan db:seed --force
else
    echo "Applying migrations to existing database..."
    php artisan migrate --force
fi

echo "Starting Supervisor (Nginx + PHP-FPM)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
