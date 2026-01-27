#!/bin/bash
set -e
echo "Running migrations..."
# Remember to remove :fresh --seed after the first successful deploy!
php artisan migrate --force
echo "Starting Apache..."
exec apache2-foreground