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

## Puesta en marcha (una sola vez)

Requisitos: PHP 8.2+ con `pdo_pgsql`, Composer, Node.js 20.19+ / 22.12+ y Docker Desktop (o cualquier PostgreSQL).

```powershell
# 1. Base de datos local  ->  localhost:5433 · db_icap · icap / icap_local
docker compose up -d

# 2. API
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed                  # tablas, triggers, roles, permisos, menú y cuentas
php artisan db:seed --class=DemoSeeder      # opcional: datos de demostración

# 3. Cliente
cd ..\frontend
npm install
copy .env.example .env
```

Después, cada día: doble clic a **`iniciar.bat`** y abrir <http://localhost:5173>.

> Si `npm run dev` falla con *Cannot find module '@rolldown/binding-win32-x64-msvc'*, es un fallo conocido de npm con dependencias opcionales: borre `frontend/node_modules` y `frontend/package-lock.json` y ejecute `npm install` de nuevo.

## Cuentas iniciales

Contraseña de todas: **`Icap2026*`**

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
7. **Estudiante**: Mis pagos → *Pagar ahora* → QR PagoFácil (o comprobante). Con comprobante, el Coordinador Financiero lo confirma en *Pagos y conciliación*.
8. La matrícula pasa a **Activa** y el estudiante queda inscrito en sus módulos.
9. **Docente**: Mis módulos → evaluaciones, asistencia, calificaciones → *Cerrar módulo* → *Corregir* una nota.
10. **Coordinador Académico**: Correcciones de nota → aprobar; Matrículas → *Finalizar programa*.
11. **Coordinadores y Secretaria**: Certificación → validar su requisito.
12. **Director**: *Autorizar y emitir certificado*; luego Panel, Reportes y Bitácora.
13. **Estudiante**: Mis certificados → descargar PDF; escanear el QR abre la verificación pública.

## Pagos con PagoFácil

Mismo flujo que el proyecto de referencia del grupo (`login` → `generate-qr` → sondeo cada 5 s → webhook con `Estado = 2`), implementado en [backend/app/Services/PasarelaPagoService.php](backend/app/Services/PasarelaPagoService.php).

- **Modo simulado** (por defecto): el QR abre una pasarela de demostración del propio sistema que dispara el mismo webhook. Permite defender el flujo completo sin credenciales ni dinero real.
- **Modo real**: en *Finanzas › Métodos de pago › QR PagoFácil* cargue `tcTokenService` y `tcTokenSecret` y elija «Real». La URL de callback debe ser pública: `https://…/backend/public/api/pagos/pasarela/callback`.
- El webhook exige el secreto `PAGO_FACIL_WEBHOOK_SECRET` (se anexa solo a la URL de callback) y es idempotente.

El modo real no pudo probarse aquí porque requiere las credenciales del comercio; conviene hacer un pago de prueba antes de la defensa.

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
