#!/bin/bash

# Wait for database to be ready
echo "Waiting for database connection..."
until php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1; do
    echo "Database is unavailable - sleeping"
    sleep 2
done

echo "Database is up - executing commands"

# Clear cache
echo "Clearing cache..."
php bin/console cache:clear --env=dev

# Run database migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Create database schema if it doesn't exist
echo "Creating database schema..."
php bin/console doctrine:schema:update --force --complete

# Set proper permissions after operations
chown -R www-data:www-data /var/www/html/var
chmod -R 777 /var/www/html/var
chmod -R 777 /var/www/html/public/storage

echo "Starting Symfony development server..."

# Start Symfony development server
exec php -S 0.0.0.0:8000 -t public/
