#!/bin/sh
set -e
cd /var/www/html

# Instala dependencias si faltan (vendor vive en un volumen de Docker)
if [ ! -f vendor/autoload.php ]; then
    echo "-> composer install..."
    composer install --no-interaction --prefer-dist
fi

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
exec "$@"
