#!/bin/bash
set -e

# Create database directory if it doesn't exist
mkdir -p /var/www/html/database

# Create SQLite database if it doesn't exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    touch /var/www/html/database/database.sqlite
    chmod 666 /var/www/html/database/database.sqlite
fi

# Set proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/database

# Initialize database if needed
if [ -f /var/www/html/init_db.php ]; then
    php /var/www/html/init_db.php
fi

# Start Apache
exec apache2-foreground
