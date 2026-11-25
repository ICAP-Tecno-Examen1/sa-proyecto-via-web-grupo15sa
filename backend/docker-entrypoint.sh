#!/bin/sh
# Arranque de la API dentro de Docker: prepara .env, tablas y datos iniciales.
set -e

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

# ICAP_MIGRAR=false  -> no toca el esquema (recomendado con la base COMPARTIDA del grupo)
# ICAP_SEMBRAR=false -> no crea cuentas ni datos iniciales
if [ "${ICAP_MIGRAR:-true}" = "true" ]; then
    echo "Esperando a la base de datos y aplicando migraciones..."
    intentos=0
    until php artisan migrate --force; do
        intentos=$((intentos + 1))
        if [ "$intentos" -ge 15 ]; then
            echo "No se pudo conectar a la base de datos."
            exit 1
        fi
        sleep 3
    done
else
    echo "Migraciones desactivadas (ICAP_MIGRAR=false)."
fi

# Los datos iniciales se cargan solo si la base está vacía (sin usuarios).
if [ "${ICAP_SEMBRAR:-true}" = "true" ]; then
    usuarios=$(php artisan tinker --execute='echo App\Models\Usuario::count();' 2>/dev/null | tr -d '[:space:]')
    if [ "$usuarios" = "0" ]; then
        echo "Base vacía: cargando roles, permisos, menú y cuentas iniciales..."
        php artisan db:seed --force
    fi
else
    echo "Datos iniciales desactivados (ICAP_SEMBRAR=false)."
fi

exec "$@"
