#!/bin/sh
set -e

PORT="${PORT:-80}"
echo "Configuring Nginx to listen on port ${PORT}..."
sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/nginx.conf
sed -i "s/listen \[::\]:80;/listen \[::\]:${PORT};/g" /etc/nginx/nginx.conf

# Ensure runtime directories exist
mkdir -p /run/nginx /var/log/nginx
chown -R www-data:www-data /run/nginx /var/log/nginx

# Ensure storage and cache permissions
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/www/html/database

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Ensure .env file exists for Laravel key storage
if [ ! -f /var/www/html/.env ]; then
    touch /var/www/html/.env
    chown www-data:www-data /var/www/html/.env
    chmod 664 /var/www/html/.env
fi

# Ensure APP_KEY exists
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY not provided in environment. Generating application key..."
    php artisan key:generate --force
fi

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

# Clear file-based configuration and route cache
php artisan config:clear
php artisan route:clear

# Run migrations and seed data FIRST so database tables exist
if [ "$IS_NEW_DB" = true ]; then
    echo "Running initial migrations and seeding database..."
    php artisan migrate --force
    php artisan db:seed --force
else
    echo "Applying migrations to existing database..."
    php artisan migrate --force
fi

# Ensure storage symlink exists for public uploads
php artisan storage:link || true

# Clear application cache now that tables exist
php artisan cache:clear || true

# Validate Nginx configuration syntax
echo "Testing Nginx configuration..."
nginx -t

echo "Starting Supervisor (Nginx + PHP-FPM)..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
