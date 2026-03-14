#!/bin/sh
set -e

# Migrations automatiques au démarrage
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
