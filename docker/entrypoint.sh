#!/bin/bash

# Docker entrypoint script for NullFake Laravel application
# Handles permissions, Laravel setup, and initialization

set -e

echo "🐳 NullFake Docker Entrypoint - Starting initialization..."

# Ensure we're in the correct directory
cd /var/www/html

# Create required Laravel directories if they don't exist
echo "📁 Creating required Laravel directories..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set proper ownership for Laravel directories
echo "🔐 Setting proper ownership and permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Ensure the vendor directory has proper permissions if it exists
if [ -d "vendor" ]; then
    chown -R www-data:www-data vendor
    chmod -R 755 vendor
fi

# Ensure node_modules has proper permissions if it exists
if [ -d "node_modules" ]; then
    chown -R www-data:www-data node_modules
    chmod -R 755 node_modules
fi

# Generate application key if it doesn't exist
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "🔑 Generating Laravel application key..."
    php artisan key:generate --force
fi

# Clear Laravel caches to ensure fresh start
echo "🧹 Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
until php artisan migrate:status > /dev/null 2>&1; do
    echo "Database not ready, waiting 2 seconds..."
    sleep 2
done

echo "✅ Database connection established"

# Run database migrations if needed
echo "📊 Running database migrations..."
php artisan migrate --force

echo "🎉 NullFake initialization complete!"

# Execute the main command (usually php-fpm)
exec "$@"

