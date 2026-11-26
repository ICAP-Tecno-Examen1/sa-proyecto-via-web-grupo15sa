# ICAP · UAGRM — Sistema web integral (INF513, segundo parcial)

Plataforma académica, administrativa y financiera del **Instituto de Capacitación Popular**: desde que se crea un programa hasta que el estudiante descarga su certificado digital con QR.

Arquitectura de **3 capas**: cliente **React** → **API RESTful Laravel** → **PostgreSQL**.

```
Examen2/
├── frontend/            CAPA 1 · Presentación  (React 19 + Vite + Tailwind, consume la API con Axios)
├── backend/             CAPA 2 · Lógica de negocio (Laravel 12, API RESTful con Sanctum)
│   └── database/sql/    CAPA 3 · Persistencia  (script del grupo + ajustes + triggers)
├── docs/                Arquitectura, matriz de acceso, requisitos y despliegue
├── docker-compose.yml   PostgreSQL local para desarrollo
└── iniciar.bat          Arranca todo con doble clic
```

## Puesta en marcha

Hay dos formas de conectar la API a la base de datos. Elija una:

| Modo | Base de datos | Cuándo usarlo |
|---|---|---|
| **A · Compartida** | `www.tecnoweb.org.bo` · `db_grupo15sa` (la que entregó el ingeniero) | Trabajo del grupo con los datos reales |
| **B · Local** | Docker, `localhost:5433` · `db_icap` | Pruebas sin tocar la base del grupo |

### ⚠️ Antes de tocar la base compartida

- **Nunca** ejecute `migrate:fresh` ni `db:wipe`: borran todas las tablas del grupo.
- La base compartida ya tiene tablas (el grupo indicó 29). Antes de migrar, revise con `php artisan db:show` si alguna se llama igual que una tabla de ICAP (`usuario`, `matricula`, `pago`…). Las migraciones usan `IF NOT EXISTS`: si una tabla existe con otras columnas, el sistema fallará o alterará datos del grupo. Acuerden con el equipo antes de migrar.
- **No ejecute `migrate --seed` en la base compartida** sin acuerdo: el seeder crea cuentas de prueba (contraseña `Icap2026*`) dentro de la base del grupo.
- Si ve `could not translate host name "www.tecnoweb.org.bo"`, el equipo no resuelve el dominio: conéctese desde la red de la universidad o revise su internet o VPN. Pruebe con `nslookup www.tecnoweb.org.bo`.

### Modo A · Base compartida

**Desde la terminal (PowerShell):**

```powershell
# 1. Credenciales: backend\.env ya tiene DB_HOST=www.tecnoweb.org.bo, DB_DATABASE=db_grupo15sa,
#    DB_USERNAME=grupo15sa y la contraseña del grupo. Si no, cópielas desde backend\.env.example.

cd backend
composer install
php artisan key:generate        # solo la primera vez (si APP_KEY está vacío)

# 2. Comprobar la conexión y ver las tablas que ya existen
php artisan db:show

# 3. Solo si el grupo acordó crear las tablas de ICAP:
php artisan migrate

# 4. Servir la API
php artisan serve               # http://127.0.0.1:8000/api
```

**Con Docker:**

```powershell
# En la raíz de Examen2:
copy .env.example .env
notepad .env                    # completar ICAP_DB_DATABASE, ICAP_DB_USERNAME e ICAP_DB_PASSWORD
#   (ICAP_MIGRAR y ICAP_SEMBRAR quedan en false: Docker no modifica la base del grupo al arrancar)

docker compose --profile app up -d --build
docker compose exec backend php artisan db:show       # comprobar la conexión
docker compose exec backend php artisan migrate       # solo si el grupo lo acordó
```

Para detener: `docker compose --profile app down`. Los datos del contenedor local se conservan; la base del grupo no se toca.

### Modo B · Base local

**Desde la terminal:**

```powershell
# 1. Solo la base de datos local (Docker)
docker compose up -d                    # PostgreSQL en localhost:5433 · db_icap · icap / icap_local

# 2. API: en backend\.env deben estar estos valores
#    DB_HOST=127.0.0.1  DB_PORT=5433  DB_DATABASE=db_icap  DB_USERNAME=icap  DB_PASSWORD=icap_local
cd backend
composer install
php artisan key:generate
php artisan migrate --seed              # tablas, triggers, roles, permisos, menú y cuentas
php artisan db:seed --class=DemoSeeder  # opcional: datos de demostración
php artisan db:seed --class=CatalogoEjemploSeeder  # opcional: carreras, curso, taller y programa técnico publicados (catálogo)
php artisan serve

# 3. Cliente (otra terminal)
cd ..\frontend
npm install
copy .env.example .env
npm run dev                             # http://localhost:5173
```

**Con Docker (todo en contenedores):**

```powershell
# Sin archivo .env en la raíz: Docker usa la base local y migra y siembra solo.
docker compose --profile app up -d --build
```

Abra <http://localhost:5173> (cliente) y <http://localhost:8000/api/publico/catalogo> (API).

### Arranque diario con doble clic

Con el modo B ya configurado, **`iniciar.bat`** levanta la base local, la API y el cliente, y abre el navegador. Para el modo A use la terminal o Docker, como arriba.

> Si `npm run dev` falla con *Cannot find module '@rolldown/binding-win32-x64-msvc'*, es un fallo conocido de npm con dependencias opcionales: borre `frontend\node_modules` y `frontend\package-lock.json` y ejecute `npm install` de nuevo.

## Cuentas iniciales

Contraseña de todas: **`Icap2026*`**. Con la base compartida, estas cuentas existen solo si el grupo ya ejecutó el seeder: confírmenlo antes de iniciar sesión.

| Usuario | Rol | Qué demuestra |
|---|---|---|
| `director` | Director | Usuarios, roles y permisos, bitácora, estadísticas, autoriza certificados |
| `secretaria` | Secretaria | Estudiantes, preinscripciones, matrículas, pagos en efectivo, repositorio |
| `coord.academico` | Coordinador Académico | Programas, mallas, ofertas, docentes, correcciones, finalización |
| `coord.financiero` | Coordinador Financiero | Aranceles, becas, métodos de pago, conciliación |
| `docente`, `docente2` | Docente | Asistencia, evaluaciones, notas, solicitud de corrección |
| `estudiante` | Estudiante | Cuenta vacía, lista para el flujo completo |
| `estudiante1` … `estudiante12` | Estudiante | Solo con datos demo: con certificados, deudas, pagos en revisión, etc. |

## Flujo para la defensa

1. **Coordinador Académico**: Programas → Módulos → Mallas → Ofertas (nueva oferta, asignar docentes).
2. **Coordinador Financiero**: Aranceles (plan al contado y plan en mensualidades).
3. **Coordinador Académico**: en la oferta, *Abrir preinscripciones*.
4. **Estudiante** (registro desde la web): Catálogo → *Preinscribirme*; Mi expediente → subir CI, título y fotografía.
5. **Secretaria**: Preinscripciones → aprobar → se genera la matrícula pendiente de pago con sus cuotas.
6. **Coordinador Financiero**: en la matrícula, pestaña Becas (opcional).
7. **Estudiante**: Mis pagos → *Pagar ahora* → tarjeta con Stripe (o comprobante). Con comprobante, el Coordinador Financiero lo confirma en *Pagos y conciliación*.
8. La matrícula pasa a **Activa** y el estudiante queda inscrito en sus módulos.
9. **Docente**: Mis módulos → evaluaciones, asistencia, calificaciones → *Cerrar módulo* → *Corregir* una nota.
10. **Coordinador Académico**: Correcciones de nota → aprobar; Matrículas → *Finalizar programa*.
11. **Coordinadores y Secretaria**: Certificación → validar su requisito.
12. **Director**: *Autorizar y emitir certificado*; luego Panel, Reportes y Bitácora.
13. **Estudiante**: Mis certificados → descargar PDF; escanear el QR abre la verificación pública.

## Pagos con tarjeta (Stripe)

El estudiante paga en la página segura de **Stripe Checkout** y vuelve al sistema, que confirma el pago solo. Está implementado en [backend/app/Services/StripeService.php](backend/app/Services/StripeService.php).

1. Cree una cuenta en <https://dashboard.stripe.com> (el **modo de prueba** no cobra dinero real).
2. *Developers › API keys*: copie la **Secret key** (`sk_test_…`) y la **Publishable key** (`pk_test_…`). Con la publicable, el formulario de la tarjeta aparece **dentro del sistema** (Stripe Embedded Checkout); sin ella, el estudiante paga en la página de Stripe y vuelve.
3. *Developers › Webhooks › Add endpoint*: URL `https://…/backend/public/api/pagos/stripe/webhook`, evento `checkout.session.completed`. Copie el **Signing secret** (`whsec_…`).
4. En el sistema, como Coordinador Financiero: *Finanzas › Métodos de pago* → editar **Tarjeta de crédito o débito (Stripe)** → pegue las claves y deje el método activo. (Alternativa: `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY` y `STRIPE_WEBHOOK_SECRET` en `backend/.env`.)
5. Pruebe con la tarjeta `4242 4242 4242 4242`, cualquier fecha futura y cualquier CVC.

**Solo tarjeta.** En bolivianos Stripe no ofrece QR ni pago en efectivo (sus QR, como Cash App Pay o WeChat, y sus vales de efectivo, como OXXO, son de otros países y monedas). Por eso el estudiante elige entre **Tarjeta (Stripe)** y **Transferencia o QR bancario** (con comprobante); el **Efectivo** se paga en caja y lo registra la Secretaría.

Si PHP en Windows da `cURL error 60` al conectar con Stripe, falta configurar los certificados: en `php.ini`, `curl.cainfo` y `openssl.cafile` deben apuntar a un `cacert.pem` (por ejemplo, el de Git: `C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt`).

En una computadora local Stripe no puede llamar al webhook; igual funciona porque, al volver de Stripe, el sistema consulta el pago directamente. Para probar también el webhook en local: `stripe listen --forward-to localhost:8000/api/pagos/stripe/webhook` (Stripe CLI).

Las pruebas automáticas simulan la API de Stripe con `Http::fake` y firman el webhook como lo hace Stripe.

## Pruebas

```powershell
docker exec icap_db psql -U icap -d db_icap -c "CREATE DATABASE db_icap_test OWNER icap"   # una vez
cd backend
php artisan test
```

[FlujoCompletoTest](backend/tests/Feature/FlujoCompletoTest.php) recorre por la API los 25 pasos del flujo general y la matriz de acceso.

## Documentación

- [docs/arquitectura.md](docs/arquitectura.md) — las 3 capas, carpetas y cómo viaja una petición
- [docs/requisitos.md](docs/requisitos.md) — dónde se cumple cada requisito del enunciado y cada caso de uso
- [docs/matriz-de-acceso.md](docs/matriz-de-acceso.md) — roles, permisos y menú dinámico
- [docs/base-de-datos.md](docs/base-de-datos.md) — ajustes al script del grupo y triggers
- [docs/despliegue-tecnoweb.md](docs/despliegue-tecnoweb.md) — publicación en el servidor institucional
