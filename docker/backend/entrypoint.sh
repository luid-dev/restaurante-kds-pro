#!/bin/sh
set -e

# Configuración, rutas y eventos en caché con las variables del contenedor (no de la imagen).
php artisan optimize --no-interaction > /dev/null

exec "$@"
