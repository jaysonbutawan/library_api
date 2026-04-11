#!/bin/sh

# Exit immediately if a command exits with a non-zero status
set -e

echo "Running RoleSeeder..."
# Target only the RoleSeeder class
php artisan db:seed --class=RoleSeeder --force

echo "Starting PHP-FPM..."
# exec ensures php-fpm becomes the main process (important for Docker)
exec php-fpm
