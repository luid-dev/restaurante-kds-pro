#!/bin/sh
set -e

# Configuración, rutas y eventos en caché con las variables del contenedor (no de la imagen).
# Sin `optimize`: la API no tiene vistas Blade y view:cache exige resources/views.
php artisan config:cache --no-interaction > /dev/null
php artisan route:cache --no-interaction > /dev/null
php artisan event:cache --no-interaction > /dev/null

exec "$@"
