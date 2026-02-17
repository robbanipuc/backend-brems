#!/bin/bash
set -e

echo "==========================================="
echo "Starting Docker Entrypoint for Laravel App"
echo "==========================================="

# ============================================
# 0. Write TiDB SSL cert from ENV
# ============================================
if [ ! -z "$DB_SSL_CA_CONTENT" ]; then
    mkdir -p storage/app
    echo "$DB_SSL_CA_CONTENT" > storage/app/tidb-ca.pem
    echo "TiDB SSL certificate written to storage/app/tidb-ca.pem"
else
    echo "WARNING: DB_SSL_CA_CONTENT is not set! TiDB SSL connection may fail."
fi

# ============================================
# 1. Wait for TiDB Cloud to be reachable
# ============================================
echo "Waiting for database connection..."

MAX_RETRIES=30
RETRY_COUNT=0

until php artisan db:monitor --databases=mysql 2>/dev/null || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database not ready yet... retry $RETRY_COUNT/$MAX_RETRIES"
    sleep 5
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "WARNING: Could not confirm database connection after $MAX_RETRIES retries"
fi

# ============================================
# 2. Verify actual DB connectivity
# ============================================
echo "Testing database connection..."
php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'DB Connected Successfully\n'; } catch(\Exception \$e) { echo 'DB Connection Failed: ' . \$e->getMessage() . '\n'; exit(1); }" || {
    echo "ERROR: Cannot connect to database. Check these:"
    echo "  - DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD env vars"
    echo "  - SSL configuration (DB_SSL_CA_CONTENT / MYSQL_ATTR_SSL_CA)"
    echo "  - TiDB Cloud IP whitelist / allowed traffic"
    exit 1
}

# ============================================
# 3. Clear Laravel config cache
# ============================================
echo "Clearing cached config..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# ============================================
# 4. Run migrations safely
# ============================================
echo "Running migrations..."
php artisan migrate --force --verbose 2>&1 | tee /tmp/migration_output.log
MIGRATION_EXIT_CODE=${PIPESTATUS[0]}

if [ $MIGRATION_EXIT_CODE -ne 0 ]; then
    echo "============================================"
    echo "MIGRATION FAILED (exit code: $MIGRATION_EXIT_CODE)"
    echo "============================================"
    cat /tmp/migration_output.log
    echo ""
    echo "Continuing with server startup anyway..."
else
    echo "Migrations completed successfully!"
fi

# ============================================
# 5. Start Apache in foreground
# ============================================
echo "Starting Apache..."
exec apache2-foreground
