#!/usr/bin/env bash
# ----------------------------------------------------------------------
# Despliegue en tecnoweb, ejecutado EN EL SERVIDOR (terminal de Cockpit).
#
#   Primera vez:   bash ~/.repo-proyecto2/despliegue/desplegar-servidor.sh
#   Actualizar:    el mismo comando (hace git pull y vuelve a publicar)
#
# Qué hace:
#   1. Trae la última versión del repositorio (~/.repo-proyecto2, NO público).
#   2. Compila el frontend (npm run build) con las rutas del grupo.
#   3. Instala las dependencias PHP de producción (composer install --no-dev).
#   4. Publica en ~/proyecto2 : frontend compilado + backend/ (sin tocar el .env
#      ni los archivos subidos por los usuarios).
#   5. Migraciones (solo agregan) y cachés de Laravel.
#
# El backend/.env del servidor se crea UNA vez a mano (ver docs/despliegue-tecnoweb.md)
# y este script nunca lo reemplaza.
# ----------------------------------------------------------------------
set -euo pipefail

GRUPO="${GRUPO:-$(whoami)}"                   # grupo15sa
REPO="$HOME/.repo-proyecto2"                  # copia del repositorio (oculta y privada)
DESTINO="$HOME/proyecto2"                     # carpeta publicada
BASE="/inf513/$GRUPO/proyecto2/"

paso() { printf '\n\033[1;36m== %s\033[0m\n' "$*"; }

# El repositorio no debe verse desde la web: solo el dueño puede leerlo.
chmod 700 "$REPO"

paso "1/5 Actualizando el código (git pull)"
git -C "$REPO" pull --ff-only

paso "2/5 Compilando el frontend para $BASE"
cd "$REPO/frontend"
cat > .env.production <<EOF
VITE_API_URL=${BASE}backend/public/api
VITE_BASE=${BASE}
VITE_ROUTER=hash
EOF
npm ci --no-audit --no-fund || npm install --no-audit --no-fund
npm run build

paso "3/5 Dependencias PHP de producción"
cd "$REPO/backend"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

paso "4/5 Publicando en $DESTINO"
mkdir -p "$DESTINO/backend"
# Frontend: se reemplaza index.html y assets/ (se borran los assets viejos)
rm -rf "$DESTINO/assets"
cp -r "$REPO/frontend/dist/." "$DESTINO/"
# Backend: se copia el código; se conservan .env, storage/ (archivos subidos, logs) y bootstrap/cache
for item in "$REPO/backend/"* "$REPO/backend/.htaccess"; do
  nombre=$(basename "$item")
  case "$nombre" in
    storage|bootstrap|tests|node_modules|phpunit.xml|Dockerfile|docker-entrypoint.sh) continue ;;
  esac
  rm -rf "$DESTINO/backend/$nombre"
  cp -r "$item" "$DESTINO/backend/$nombre"
done
mkdir -p "$DESTINO/backend/bootstrap/cache"
cp "$REPO/backend/bootstrap/"*.php "$DESTINO/backend/bootstrap/"
for c in app/private app/public logs fonts framework/cache/data framework/sessions framework/views; do
  mkdir -p "$DESTINO/backend/storage/$c"
done
rm -f "$DESTINO/backend/bootstrap/cache/"*.php
chmod -R 777 "$DESTINO/backend/storage" "$DESTINO/backend/bootstrap/cache"

if [ ! -f "$DESTINO/backend/.env" ]; then
  printf '\n\033[1;33mFalta %s/backend/.env\033[0m: créelo con los datos que dio el docente\n' "$DESTINO"
  echo "(ver docs/despliegue-tecnoweb.md, paso «.env del servidor») y vuelva a ejecutar este script."
  exit 1
fi

paso "5/5 Base de datos y cachés"
cd "$DESTINO/backend"
php artisan package:discover
php artisan migrate --force          # solo agrega lo que falta; NUNCA migrate:fresh aquí
php artisan config:cache
php artisan route:cache

printf '\n\033[1;32mListo:\033[0m https://www.tecnoweb.org.bo%s\n' "$BASE"
echo "Prueba de la API: https://www.tecnoweb.org.bo${BASE}backend/public/api/publico/catalogo"
