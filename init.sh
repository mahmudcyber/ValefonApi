#!/bin/sh
set -e

# Run migrations AFTER the app has env + Postgres
php artisan config:clear
php artisan route:clear
php artisan cache:clear

# Run scripts that were skipped during composer install
composer run-script post-autoload-dump --no-dev --optimize-autoloader

php artisan migrate --force
