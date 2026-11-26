# Production Dockerfile for Laravel Backend on Render
FROM php:8.3-fpm-alpine

# Install system utilities
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    dos2unix

# Install official PHP extension installer
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install PHP extensions safely and cleanly
RUN install-php-extensions \
    pdo_sqlite \
    sqlite3 \
    curl \
    mbstring \
    fileinfo \
    zip \
    bcmath \
    opcache \
    pcntl \
    intl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy Nginx, Supervisor, and Entrypoint configurations
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Normalize line endings and permissions for entrypoint
RUN dos2unix /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# Ensure storage, runtime, and bootstrap directories exist with proper permissions
RUN mkdir -p /run/nginx \
    && mkdir -p /var/log/nginx \
    && mkdir -p /var/www/html/storage/framework/cache \
    && mkdir -p /var/www/html/storage/framework/sessions \
    && mkdir -p /var/www/html/storage/framework/views \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/bootstrap/cache \
    && mkdir -p /var/www/html/database \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database /run/nginx /var/log/nginx \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

EXPOSE 80 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
