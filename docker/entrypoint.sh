#!/bin/sh
set -e

echo "Starting OPTS2026..."

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    echo "MySQL not ready, retrying in 2s..."
    sleep 2
done
echo "MySQL is ready."

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Seed if users table is empty
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "Seeding database..."
    php artisan db:seed --force
fi

# Cache configuration and routes
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link --force 2>/dev/null || true

echo "Starting OPTS2026 services..."
exec "$@"
