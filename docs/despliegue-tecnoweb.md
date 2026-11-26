# Despliegue en tecnoweb

Destino: `https://www.tecnoweb.org.bo/inf513/grupoXXsa/proyecto2` (reemplace `grupoXXsa` por su usuario).

> Estos pasos **no se probaron en el servidor institucional** (no hay acceso desde el entorno de desarrollo). Se basan en lo que ya funcionó con el proyecto de referencia del grupo; verifique cada punto al subir.

## Estructura en el servidor

```
proyecto2/                  ← contenido de frontend/dist  (index.html, assets/, .htaccess)
└── backend/                ← proyecto Laravel completo
    └── public/             ← la API responde en  …/proyecto2/backend/public/api
```

## 1. Base de datos

El esquema del grupo ya existe del primer parcial. Las migraciones solo agregan lo que falta.

```bash
cd proyecto2/backend
php artisan migrate --force        # NUNCA migrate:fresh en la base compartida
php artisan db:seed --force        # roles, permisos, menú, métodos de pago y cuentas iniciales
```

Si prefieren no usar artisan, ejecuten en orden los tres archivos de `backend/database/sql/` y luego los seeders.

## 2. Backend

1. Subir la carpeta `backend/` (sin `vendor/`) y ejecutar `composer install --no-dev --optimize-autoloader`.
2. Crear `backend/.env` a partir de `.env.example`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.tecnoweb.org.bo/inf513/grupoXXsa/proyecto2/backend/public

DB_HOST=localhost              # desde el propio servidor; desde fuera: db.tecnoweb.org.bo
DB_PORT=5432
DB_DATABASE=db_grupoXXsa
DB_USERNAME=grupoXXsa
DB_PASSWORD=********
DB_SEARCH_PATH=public          # el esquema asignado al grupo

FRONTEND_URL=https://www.tecnoweb.org.bo/inf513/grupoXXsa/proyecto2
FRONTEND_ROUTER=hash           # igual que VITE_ROUTER del frontend

# Stripe: lo recomendado es cargar las claves desde Métodos de pago (estas son el respaldo)
STRIPE_SECRET_KEY=             # sk_live_… en producción
STRIPE_WEBHOOK_SECRET=         # whsec_… del endpoint registrado en Stripe
STRIPE_CURRENCY=bob
```

3. `php artisan key:generate`
4. Permisos de escritura para el usuario del servidor web (en tecnoweb, `apache`):

```bash
setfacl -R -m u:apache:rwX storage bootstrap/cache
setfacl -R -d -m u:apache:rwX storage bootstrap/cache
# si no hay setfacl:  chmod -R o+rwX storage bootstrap/cache
```

5. Probar: `…/proyecto2/backend/public/api/publico/catalogo` debe responder JSON.

`backend/.htaccess` bloquea el acceso web a todo lo que no sea `public/` (incluido el `.env`). Compruebe que `…/proyecto2/backend/.env` responde **403**.

## 3. Frontend

En su máquina:

```powershell
cd frontend
copy .env.production.example .env.production      # y editar grupoXXsa
npm run build
```

Subir el **contenido** de `frontend/dist/` a `proyecto2/`.

- `VITE_ROUTER=hash` (recomendado): las direcciones quedan como `…/proyecto2/#/catalogo` y funcionan sin configuración de Apache.
- `VITE_ROUTER=browser`: direcciones limpias, pero requiere que el `.htaccess` incluido esté activo; de lo contrario, al recargar una página interna el servidor responde 404.

## Problemas conocidos (vividos en el proyecto de referencia)

| Síntoma | Causa | Solución |
|---|---|---|
| Error 500 sin mensaje | `apache` no puede escribir en `storage/` | Paso 4 del backend |
| Error 500 al conectar a la BD | `DB_HOST` con el dominio público desde el propio servidor | `DB_HOST=localhost` |
| La página carga en blanco | `VITE_BASE` no coincide con la carpeta publicada | Corregir `.env.production` y recompilar |
| Las llamadas a la API dan 404 | `VITE_API_URL` incorrecta | Debe terminar en `/backend/public/api` |
| Los cambios del `.env` no se aplican | Configuración en caché | `php artisan config:clear` |
| El QR del certificado abre una dirección equivocada | `FRONTEND_URL` / `FRONTEND_ROUTER` | Deben coincidir con la URL pública y con `VITE_ROUTER` |

## Stripe en producción

1. En el panel de Stripe, active la cuenta para cobros reales y copie la **Secret key** `sk_live_…`.
2. *Developers › Webhooks › Add endpoint*: `https://www.tecnoweb.org.bo/inf513/grupoXXsa/proyecto2/backend/public/api/pagos/stripe/webhook`, evento `checkout.session.completed`; copie su **Signing secret** `whsec_…`.
3. Ingresar como Coordinador Financiero → *Métodos de pago* → editar **Tarjeta de crédito o débito (Stripe)** → pegar ambas claves (la tabla mostrará «Modo real»).
4. Hacer un pago de monto mínimo y confirmar que la cuota pasa a *Pagada* sin intervención.
5. `FRONTEND_URL` debe ser la dirección pública: Stripe devuelve al estudiante a `…/pago/resultado`.
