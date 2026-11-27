#!/usr/bin/env bash
# ----------------------------------------------------------------------
# Despliegue en tecnoweb, ejecutado EN EL SERVIDOR (terminal de Cockpit).
#
#   Primera vez:   bash ~/.repo-proyecto2/despliegue/desplegar-servidor.sh
#   Actualizar:    el mismo comando (trae lo último de GitHub y vuelve a publicar)
#
# Qué hace:
#   1. Trae la última versión de GitHub (~/.repo-proyecto2, NO público).
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

# Todo va dentro de main(): bash lee la función completa ANTES de ejecutarla. Así no se
# rompe cuando el paso 1 actualiza este mismo archivo desde GitHub mientras corre.
main() {
GRUPO="${GRUPO:-$(whoami)}"                   # grupo15sa
REPO="$HOME/.repo-proyecto2"                  # copia del repositorio (oculta y privada)
DESTINO="$HOME/proyecto2"                     # carpeta publicada
BASE="/inf513/$GRUPO/proyecto2/"

paso() { printf '\n\033[1;36m== %s\033[0m\n' "$*"; }

# El repositorio no debe verse desde la web: solo el dueño puede leerlo.
chmod 700 "$REPO"

paso "1/5 Actualizando el código (git)"
# Es una copia solo para desplegar: se deja idéntica a GitHub aunque npm haya tocado
# el package-lock.json (con «git pull» eso haría fallar la siguiente actualización).
git -C "$REPO" fetch --quiet origin
git -C "$REPO" reset --hard --quiet origin/main
git -C "$REPO" log --oneline -1

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

# Permisos: el servidor web (usuario apache) debe poder escribir en storage/ y bootstrap/cache.
# Con ACL se da acceso solo a apache; si no hay setfacl, se abre a todos (777).
if command -v setfacl >/dev/null 2>&1 && setfacl -R -m u:apache:rwX,d:u:apache:rwX "$DESTINO/backend/storage" "$DESTINO/backend/bootstrap/cache" 2>/dev/null; then
  echo "Permisos de escritura para apache (ACL)."
else
  chmod -R 777 "$DESTINO/backend/storage" "$DESTINO/backend/bootstrap/cache"
fi

if [ ! -f "$DESTINO/backend/.env" ]; then
  printf '\n\033[1;33mFalta %s/backend/.env\033[0m: créelo con los datos que dio el docente\n' "$DESTINO"
  echo "(ver docs/despliegue-tecnoweb.md, paso «.env del servidor») y vuelva a ejecutar este script."
  exit 1
fi

# El .env tiene la contraseña de la base: que no lo lean otros usuarios del servidor
# (la web ya lo bloquea con .htaccess). Solo el dueño y apache.
if command -v setfacl >/dev/null 2>&1 && chmod 600 "$DESTINO/backend/.env" && setfacl -m u:apache:r "$DESTINO/backend/.env" 2>/dev/null; then
  echo "backend/.env protegido (solo $GRUPO y apache)."
else
  chmod 644 "$DESTINO/backend/.env"
fi

paso "5/5 Base de datos y cachés"
cd "$DESTINO/backend"
# Primera vez: si el .env no tiene APP_KEY, se genera
if ! grep -qE '^APP_KEY=base64:.+' .env; then
  php artisan key:generate --force
fi
php artisan package:discover
php artisan migrate --force          # solo agrega lo que falta; NUNCA migrate:fresh aquí
php artisan config:cache
php artisan route:cache

printf '\n\033[1;32mListo:\033[0m https://www.tecnoweb.org.bo%s\n' "$BASE"
echo "Prueba de la API: https://www.tecnoweb.org.bo${BASE}backend/public/api/publico/catalogo"
}

main "$@"
exit $?
