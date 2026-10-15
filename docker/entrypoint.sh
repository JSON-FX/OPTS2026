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

# Bootstrap business roles only. Suppliers and other reference data are operator-managed.
ROLE_COUNT=$(php artisan tinker --execute="echo \Spatie\Permission\Models\Role::count();" 2>/dev/null || echo "0")
if [ "$ROLE_COUNT" = "0" ]; then
    echo "Seeding database..."
    php artisan db:seed --class=RoleSeeder --force
fi

# Cache configuration and routes
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link --force 2>/dev/null || true

# CLI startup may create log/cache files before PHP-FPM and workers start.
chown -R www-data:www-data storage bootstrap/cache

echo "Starting OPTS2026 services..."
exec "$@"
