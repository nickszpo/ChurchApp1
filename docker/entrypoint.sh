#!/bin/bash
set -e

# Set proper permissions
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Initialize database if needed
if [ -f /var/www/html/init_db.php ]; then
    php /var/www/html/init_db.php
fi

# Start Apache
exec apache2-foreground
