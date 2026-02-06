#!/bin/bash
set -e

echo "Starting Apache..."

# Try to migrate, but don't crash if it fails
echo "Attempting migration..."
php artisan migrate --force || echo "Migration failed, but continuing..."

# Start the server no matter what
exec apache2-foreground