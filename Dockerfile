# syntax=docker/dockerfile:1.7

# Coolify-ready production image: HTTP (nginx) + PHP-FPM in one container
# Base image includes nginx + s6 overlay + sane defaults.

FROM serversideup/php:8.4-fpm-nginx

USER root

WORKDIR /var/www/html

# Install required PHP extensions for this app (keep it minimal)
RUN install-php-extensions \
    intl \
    bcmath

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install prod dependencies without running scripts (avoid dev-only providers)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

# Copy the rest of the app
COPY . .

# Generate autoloader and clear stale cache files
RUN composer dump-autoload --classmap-authoritative --no-dev && \
    rm -f /var/www/html/bootstrap/cache/*.php

# Ensure correct permissions & avoid stale cached providers from the repo
RUN rm -f /var/www/html/bootstrap/cache/*.php \
    && chmod 755 /var/www/html/artisan \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Performance (enable OPcache in production)
ENV PHP_OPCACHE_ENABLE=1

# Drop back to the default unprivileged user
USER www-data

# Nginx listens on 8080 in this image
EXPOSE 8080

