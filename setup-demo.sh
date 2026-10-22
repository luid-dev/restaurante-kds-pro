#!/usr/bin/env bash
# Levanta la demo completa en un comando: genera secretos locales (.env), construye
# las imágenes, migra la base, crea el certificado de prueba SUNAT y carga la pollería demo.
#   ./setup-demo.sh          primera instalación o actualización (idempotente)
#   ./setup-demo.sh --reset  recrea la base de la demo (solo el volumen de Docker)
set -euo pipefail
cd "$(dirname "$0")"

RESET=false
[[ "${1:-}" == "--reset" ]] && RESET=true

command -v docker > /dev/null || { echo "Docker no está instalado." >&2; exit 1; }
docker compose version > /dev/null

secret() { openssl rand -hex "$1"; }

if [[ ! -f .env ]]; then
  cat > .env <<ENV
APP_PORT=8088
APP_KEY=base64:$(openssl rand -base64 32)
DB_DATABASE=kds_demo
DB_USERNAME=kds
DB_PASSWORD=$(secret 16)
REVERB_APP_ID=$(( (RANDOM % 900000) + 100000 ))
REVERB_APP_KEY=$(secret 10)
REVERB_APP_SECRET=$(secret 20)
PIN_PEPPER=$(secret 32)
ENV
  echo "Secretos locales generados en .env"
fi

docker compose build
docker compose up -d --wait postgres redis

artisan() { docker compose run --rm --no-deps backend php artisan "$@"; }

if [[ "$RESET" == true ]]; then
  artisan migrate:fresh --force
else
  artisan migrate --force
fi

artisan sunat:dev-certificate
artisan db:seed --class=RestaurantDemoSeeder --force

docker compose up -d

PORT="$(grep -E '^APP_PORT=' .env | cut -d= -f2)"
cat <<INFO

Demo lista en http://localhost:${PORT:-8088}
Empresa RUC 20609876543 · contraseña común Password123!
  Gerente     admin@restaurante.pe   PIN 111111
  Cajero      caja@restaurante.pe    PIN 222222
  Mozo        mozo@restaurante.pe    PIN 333333
  Cocina KDS  cocina@restaurante.pe  PIN 444444
INFO
