# Despliegue en tecnoweb (paso a paso)

Destino: **https://www.tecnoweb.org.bo/inf513/grupo15sa/proyecto2**
API: **https://www.tecnoweb.org.bo/inf513/grupo15sa/proyecto2/backend/public/api**
Base de datos: PostgreSQL del servidor, base `db_grupo15sa` (usuario `grupo15sa`).

## 0. Idea general (para explicarlo en la defensa)

El sistema tiene 3 capas y cada una va a un lugar distinto:

| Capa | Qué es | Dónde queda en el servidor |
|---|---|---|
| Cliente (React) | Archivos estáticos ya compilados (`index.html` + `assets/`) | `proyecto2/` |
| API (Laravel) | Código PHP que responde JSON | `proyecto2/backend/` (solo `public/` es accesible por web) |
| Base de datos | PostgreSQL | `db_grupo15sa` en el servidor de BD |

```
proyecto2/
├── index.html, assets/, favicon.svg, .htaccess   ← frontend compilado (npm run build)
└── backend/                                       ← Laravel completo, con vendor/ y .env
    ├── .htaccess                                  ← bloquea todo menos public/ (protege el .env)
    └── public/index.php                           ← la API entra por aquí: …/backend/public/api/…
```

Tres datos deben coincidir o el sitio no funciona:

- `VITE_BASE` = `/inf513/grupo15sa/proyecto2/`: la carpeta donde se publica.
- `VITE_API_URL` = `/inf513/grupo15sa/proyecto2/backend/public/api`: dónde está la API.
- `VITE_ROUTER=hash` en el frontend y `FRONTEND_ROUTER=hash` en el backend: así el QR del certificado y las vueltas de Stripe llevan a la dirección correcta.

## ★ Forma recomendada: desde el servidor con git (Cockpit)

En tecnoweb el puerto SSH/SFTP (22) está cerrado desde fuera. Se entra por **Cockpit**:

- Dirección: `https://www.tecnoweb.org.bo:9090`
- Usuario: `grupo15sa`, con la contraseña del grupo
- Menú: **Terminal**

La carpeta personal (`~`) **es** la carpeta pública: allí están `practica2`…`practica5`, y ahí va `proyecto2`.

El servidor ya tiene PHP 8.4, Composer, git, Node 22, curl, unzip y las extensiones `pdo_pgsql`, `gd`, `zip`, `mbstring`, `xml` y `fileinfo`.

### Primera vez

**1. Clonar el repositorio** en una carpeta oculta y privada. Así el código fuente no queda visible en la web:

```bash
git clone https://github.com/ICAP-Tecno-Examen1/sa-proyecto-via-web-grupo15sa.git ~/.repo-proyecto2
chmod 700 ~/.repo-proyecto2
```

**2. Crear el `.env` del servidor**, una sola vez y **con los datos que dio el docente**:

```bash
mkdir -p ~/proyecto2/backend
nano ~/proyecto2/backend/.env
```

Pegue esto, completando los `…` con los datos del docente, y guarde con `Ctrl+O`, `Enter`, `Ctrl+X`:

```env
APP_NAME="ICAP UAGRM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://www.tecnoweb.org.bo/inf513/grupo15sa/proyecto2/backend/public
APP_TIMEZONE=America/La_Paz
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=…            # el que indicó el docente (p. ej. db.tecnoweb.org.bo o localhost)
DB_PORT=5432
DB_DATABASE=db_grupo15sa
DB_USERNAME=grupo15sa
DB_PASSWORD=…
DB_SEARCH_PATH=public
DB_TIMEZONE=America/La_Paz

SESSION_DRIVER=array
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log

FRONTEND_URL=https://www.tecnoweb.org.bo/inf513/grupo15sa/proyecto2
FRONTEND_ROUTER=hash
CORS_ORIGENES=*

ICAP_NOTA_MINIMA=51
ICAP_ASISTENCIA_MINIMA=70
ICAP_MESES_POR_MODULO=1
ICAP_DIAS_PAGO_INICIAL=7
ICAP_ARCHIVO_MAX_KB=4096

STRIPE_SECRET_KEY=…        # sk_test_…
STRIPE_PUBLISHABLE_KEY=…   # pk_test_…
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=bob
```

**3. Ejecutar el despliegue y luego generar la clave de la aplicación:**

```bash
bash ~/.repo-proyecto2/despliegue/desplegar-servidor.sh
cd ~/proyecto2/backend && php artisan key:generate --force && php artisan config:cache
```

### Cada vez que haya cambios

1. En su PC: `git push` de los cambios al repositorio.
2. En la terminal de Cockpit:

```bash
bash ~/.repo-proyecto2/despliegue/desplegar-servidor.sh
```

El script ([despliegue/desplegar-servidor.sh](../despliegue/desplegar-servidor.sh)) hace:

1. `git pull`
2. compila el frontend con las rutas del grupo
3. `composer install --no-dev`
4. copia todo a `~/proyecto2` **sin tocar** el `.env` ni los archivos subidos
5. `migrate --force`, `config:cache` y `route:cache`

Las secciones siguientes son la alternativa sin git: armar el paquete en la PC y subirlo.

## 1. Preparar el paquete (en su computadora)

Requisitos: Node, PHP y Composer instalados.

```powershell
cd D:\respaldo\TecnoWeb\Proyectos\Web\Examen2
powershell -ExecutionPolicy Bypass -File .\despliegue\preparar-paquete.ps1
```

El script hace, en orden:

1. Escribe `frontend\.env.production` con las 3 variables de arriba y ejecuta `npm run build`. El resultado queda en `frontend\dist`.
2. Copia `backend\` sin lo local: sin `.env`, `tests`, archivos subidos, logs ni cachés.
3. Ejecuta `composer install --no-dev`. Esto crea `vendor/` con solo lo necesario en producción.
4. Genera el `.env` de producción:
   - `APP_ENV=production` y `APP_DEBUG=false`;
   - una `APP_KEY` nueva;
   - `DB_HOST=localhost` y los datos de `db_grupo15sa`;
   - las URL públicas;
   - las claves de Stripe que ya están en su `backend\.env`.

Resultado: `despliegue\salida\proyecto2\` y `despliegue\salida\proyecto2.zip`. Esa carpeta **no va a git** porque contiene contraseñas.

> Las dependencias están fijadas para **PHP 8.2 o superior** (`config.platform.php` en `composer.json`), así funcionan aunque el servidor no tenga la última versión de PHP.

## 2. Subir los archivos

### Opción A: WinSCP o FileZilla (SFTP), la más simple

1. Conéctese a `www.tecnoweb.org.bo` con el usuario y contraseña del grupo, protocolo **SFTP**, puerto 22.
2. Vaya a la carpeta pública del grupo: la que se ve como `/inf513/grupo15sa/`. Ahí están `practica2`, `practica3`…
3. Cree la carpeta `proyecto2` y suba **el contenido** de `despliegue\salida\proyecto2\`.
   - Más rápido: suba `proyecto2.zip` y descomprímalo en el servidor (paso 3).
4. Asegúrese de que se suban también los **archivos ocultos**: `.htaccess` y `backend/.env`. En WinSCP: *Opciones › Preferencias › Panel › Mostrar archivos ocultos*.

### Opción B: por SSH

```bash
# desde su PC
scp despliegue/salida/proyecto2.zip grupo15sa@www.tecnoweb.org.bo:~/

# en el servidor
ssh grupo15sa@www.tecnoweb.org.bo
cd <carpeta pública del grupo>          # la que corresponde a /inf513/grupo15sa/
unzip ~/proyecto2.zip                   # crea la carpeta proyecto2/
```

## 3. Comandos en el servidor (por SSH)

```bash
cd <carpeta pública>/proyecto2/backend

php -v                                  # debe ser 8.2 o superior

# 1) Laravel necesita escribir en storage/ y bootstrap/cache (el servidor web corre como "apache")
setfacl -R -m u:apache:rwX storage bootstrap/cache
setfacl -R -d -m u:apache:rwX storage bootstrap/cache
#    si no existe setfacl:
chmod -R 777 storage bootstrap/cache

# 2) Base de datos: solo agrega lo que falta (es seguro en la base compartida)
php artisan migrate --force

# 3) Cachés de producción (más rápido)
php artisan config:cache
php artisan route:cache
```

> ⛔ **Nunca** ejecute `migrate:fresh`, `migrate:refresh`, `db:wipe` ni `migrate --seed` en `db_grupo15sa`: borran o duplican datos de toda la base del grupo.
> La base ya tiene roles, permisos, menú y cuentas. Si alguna vez falta algo de eso, `php artisan db:seed --class=RolPermisoSeeder --force` solo **agrega** lo que falte.

Si **no tiene SSH**: los permisos se pueden dar desde WinSCP (clic derecho › *Propiedades* › 777 en `storage` y `bootstrap/cache`, recursivo). La migración se puede ejecutar desde su PC apuntando `backend\.env` a `db_grupo15sa`, que es lo que ya se hizo el 26/11/2026.

## 4. Probar

| Prueba | Resultado esperado |
|---|---|
| `…/proyecto2/backend/public/api/publico/catalogo` | JSON con las ofertas |
| `…/proyecto2/backend/.env` | **403 Forbidden** (el `.env` está protegido) |
| `…/proyecto2/` | Página de inicio del ICAP |
| Ingresar como `director` | Panel con su menú |
| `…/proyecto2/#/verificar` | Página pública de verificación |

## 5. Actualizar después de un cambio

1. Volver a ejecutar `preparar-paquete.ps1`.
2. Subir solo lo que cambió:
   - **Solo frontend:** `index.html` y `assets/`. Conviene borrar antes la carpeta `assets/` vieja.
   - **Backend:** `app/`, `routes/`, `resources/`, `database/`…
   - **Si cambió `composer.json`:** también `vendor/`.
3. Por SSH:

   ```bash
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   ```

**No sobrescriba `backend/.env` del servidor** si lo editó allí.

## 6. Problemas frecuentes

| Síntoma | Causa | Solución |
|---|---|---|
| Error 500 sin mensaje | `apache` no puede escribir en `storage/` | Paso 3.1 (permisos) |
| Error 500 «connection to server … failed» | `DB_HOST` | Desde el servidor use `localhost`; si no funciona, `db.tecnoweb.org.bo` |
| Error 500 «Class … not found» | `vendor/` incompleto o caché vieja | Subir `vendor/` completo; `php artisan config:clear` |
| «Your Composer dependencies require a PHP version…» | PHP del servidor < 8.2 | Pedir PHP 8.2+ o bajar `config.platform.php` en `composer.json` y regenerar |
| Página en blanco | `VITE_BASE` no coincide con la carpeta | Regenerar el paquete con el grupo correcto |
| Las llamadas a la API dan 404 | `VITE_API_URL` incorrecta o falta `backend/public/.htaccess` | Revisar que se subieron los archivos ocultos |
| Los cambios del `.env` no se aplican | Configuración en caché | `php artisan config:clear` y luego `config:cache` |
| El QR del certificado abre otra dirección | `FRONTEND_URL` / `FRONTEND_ROUTER` | Deben ser la URL pública y `hash` |
| No se ven firmas o archivos subidos | Los archivos subidos en local no viajan | Volver a subirlos desde la interfaz del servidor |

Para ver el error real en el servidor:

```bash
tail -n 50 storage/logs/laravel.log
```

## 7. GitHub (requisito del proyecto)

El repositorio debe estar en `https://github.com/<su_cuenta>/inf513-proyecto2`.

1. En GitHub cree el repositorio **vacío** `inf513-proyecto2` (sin README).
2. En su PC:

```powershell
cd D:\respaldo\TecnoWeb\Proyectos\Web\Examen2
git remote add entrega https://github.com/<su_cuenta>/inf513-proyecto2.git
git push entrega main
```

`git remote -v` muestra los repositorios conectados: `origin` es el del grupo y `entrega` el de la materia. Los `.env`, `vendor/`, `node_modules/`, `dist/` y `despliegue/salida/` **no se suben**: están en `.gitignore`.

## Stripe en producción

1. Webhook en el panel de Stripe (*Developers › Webhooks › Add endpoint*):
   - URL: `https://www.tecnoweb.org.bo/inf513/grupo15sa/proyecto2/backend/public/api/pagos/stripe/webhook`
   - Evento: `checkout.session.completed`
   - Copie el **Signing secret** `whsec_…`.
2. Ingrese como `coord.financiero` → *Métodos de pago* → «Tarjeta de crédito o débito (Stripe)» → pegue las claves.

Sin webhook también funciona: la página de resultado consulta el estado del pago a Stripe.
