# Use the official ServerSideUp PHP 8.4 FPM Nginx image as the base
FROM serversideup/php:8.4-fpm-nginx

# Switch to root user for installation
USER root

# Install required PHP extensions
RUN install-php-extensions intl

# Set the working directory
WORKDIR /var/www/html

# Copy composer files first for better caching
COPY --chown=www-data:www-data composer.json composer.lock ./

# Install PHP dependencies (production mode, no dev dependencies)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-cache

# Copy the rest of the application code
COPY --chown=www-data:www-data . .

# Set proper permissions for Laravel
RUN chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Switch back to www-data user
USER www-data

# Expose port 80 for web traffic
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD php artisan --version || exit 1

# Set environment variables
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Default command - runs the base image's entrypoint (S6 supervisor)
# To run different roles, override with environment variable ROLE=queue or ROLE=reverb
CMD []
