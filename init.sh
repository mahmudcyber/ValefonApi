#!/bin/sh

# Exit on error
set -e

echo "Running Laravel setup..."

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Run migrations
php artisan migrate --force

echo "Laravel setup complete!"
