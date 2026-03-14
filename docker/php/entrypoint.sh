#!/bin/sh
set -e

# Install Composer dependencies if vendor/ is missing (first boot in dev)
if [ ! -f /app/vendor/autoload.php ]; then
    echo "==> vendor/ not found, running composer install..."
    composer install --no-interaction --prefer-dist
fi

exec "$@"
