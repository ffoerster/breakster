# syntax=docker/dockerfile:1.7

# Build stage: compile frontend assets with bun
FROM oven/bun AS frontend-builder

WORKDIR /var/www/html

# build-time args (provided by Coolify build args)
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME

# make them visible to bun/vite during build
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

# Copy package files
COPY package.json bun.lock ./

# Install dependencies
RUN bun install --frozen-lockfile

# Copy frontend source
COPY . .

# Build assets
# Ensure local env files don't override build-time args
RUN rm -f .env .env.local .env.development .env.production .env.* || true \
    && bun run build

# Production stage: PHP-FPM + Nginx
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

# Copy pre-built frontend assets from builder stage
COPY --from=frontend-builder /var/www/html/public/build ./public/build

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

