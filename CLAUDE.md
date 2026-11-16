# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ICAP · UAGRM academic/administrative/financial platform (INF513 second exam). Strict 3-tier split:

- `frontend/` — React 19 SPA (Vite 8, Tailwind 4, React Router 7, Axios). No business rules; only calls the API.
- `backend/` — Laravel 12 REST API (Sanctum tokens). Returns only JSON (plus PDF/CSV files), never HTML.
- PostgreSQL — schema lives as raw SQL in `backend/database/sql/`.

All identifiers, routes, DB tables, UI text, validation and error messages are in **Spanish**. Keep new code consistent with that (e.g. `MatriculaService`, `permiso:pagos.conciliar`, `'El apellido es obligatorio.'`).

`docs/` holds the authoritative design docs (`arquitectura.md`, `matriz-de-acceso.md`, `base-de-datos.md`, `requisitos.md`, `despliegue-tecnoweb.md`). Update them when changing permissions, schema or states.

## Commands

The shell is Windows PowerShell. Run backend commands from `backend/`, frontend commands from `frontend/`.

```powershell
# Local DB (localhost:5433 · db_icap · icap / icap_local)
docker compose up -d

# Backend
php artisan serve --host=127.0.0.1 --port=8000
php artisan migrate --seed                 # schema + triggers + roles, permissions, menu, accounts
php artisan db:seed --class=DemoSeeder     # optional demo data
vendor\bin\pint                            # PHP formatter

# Frontend (http://localhost:5173, proxies /api -> 127.0.0.1:8000)
npm run dev
npm run build
npm run lint                               # oxlint

# Everything at once (DB + API + client in separate windows)
..\iniciar.bat
```

### Tests

Tests run against a real PostgreSQL database `db_icap_test` (triggers are part of the system, so SQLite is not an option). `phpunit.xml` overrides only `DB_DATABASE`; host and credentials come from `backend/.env`.

```powershell
docker exec icap_db psql -U icap -d db_icap -c "CREATE DATABASE db_icap_test OWNER icap"   # once
php artisan test
php artisan test --filter=test_flujo_general_del_icap    # single test
```

`tests/Feature/FlujoCompletoTest.php` is one long end-to-end test that walks the whole business flow through the API (program → offer → pre-enrolment → enrolment → payment → grades → certificate) plus the access matrix. It uses `RefreshDatabase` with `$seed = true` and logs in as seeded users via the `como('username')` helper. New behaviour is normally verified by extending this flow.

Seeded accounts all use password `Icap2026*` (`director`, `secretaria`, `coord.academico`, `coord.financiero`, `docente`, `estudiante`, …).

### Never run `migrate:fresh` against a shared database

Production uses the institutional shared PostgreSQL (tecnoweb), which already contains the group's tables from the first exam. `migrate:fresh` / `db:wipe` would drop them. Only `php artisan migrate` is safe there. Check `DB_HOST` in `backend/.env` before running any destructive DB or test command.

## Backend architecture

Request path: `routes/api.php` → middleware (`auth:sanctum`, `usuario.activo`, `permiso:…`, `RegistrarBitacora`) → controller (validates input) → service (business rules, transactions) → Eloquent model.

- **Controllers** (`app/Http/Controllers/Api/<Módulo>/`) are grouped by use case (CU1 `Acceso`, CU2 `Academico`/`Publico`, CU3 `Inscripcion`, CU4 `Finanzas`, CU5 `Evaluacion`, CU6 `Certificacion`, CU7 `Documental`, CU8 `Reportes`/`Sistema`). They validate inline with `$request->validate()` and delegate; simple CRUD may touch models directly.
- **Services** (`app/Services/`) hold the business rules. A broken rule throws `App\Exceptions\ReglaNegocioException`, which renders as `422 {message}` in Spanish.
- **Models** all extend `App\Models\Modelo` (no timestamps, `$guarded = []`). Tables are singular Spanish names with `id_<tabla>` primary keys, and state values are class constants (`Arancel::ACTIVO`, `Bitacora::ACCION`). `Usuario` is the exception: it extends `Authenticatable` and authenticates with `password_hash` (the `password` column is unused).
- **Errors**: `bootstrap/app.php` forces every exception to render as JSON with a Spanish `message`. Validation messages come from `lang/es/validation.php`.
- **Business parameters** (minimum grade, minimum attendance, required documents, upload limits, PagoFácil) live in `config/icap.php`.

### RBAC — the access matrix

- A permission is an action string (`pagos.conciliar`). Roles get permissions via `rol_permiso`; users get roles via `usuario_rol`.
- A user works with **one active role per request**, sent in the `X-Rol-Activo` header (falls back to their first role). `Usuario::rolActivo()` / `permisos()` resolve it.
- Every protected route declares its permission: `->middleware('permiso:a|b')` (any one suffices). `routes/api.php` *is* the access matrix. Some routes do finer ownership checks inside the controller/service (payment status, document download, which certificate requirement a role may validate).
- The initial matrix is `database/seeders/RolPermisoSeeder.php`; the menu is the `menu_item` table (`MenuSeeder`), each entry tagged with the permission it needs. Both are editable at runtime by the Director.

Adding a protected feature therefore touches: the route (with `permiso:`), `RolPermisoSeeder`, `MenuSeeder` if it needs a menu entry, `frontend/src/routes/rutas.js`, and `docs/matriz-de-acceso.md`.

### Audit log

`RegistrarBitacora` runs on every API route and, after the response, logs all successful mutating requests (POST/PUT/PATCH/DELETE) and every 401/403. Controllers supply a human-readable description by calling `Auditoria::describir("…")`; without it a generic "Registró en api/…" is stored. Login/logout write their own entries. GETs are not logged (page views use the `visita` counter instead).

### Database

Migrations only execute the three SQL files in order, so **the SQL is the source of truth**:

- `sql/01_esquema_base.sql` — the group's approved script; do not change its structure.
- `sql/02_ajustes_parcial2.sql` — additive columns/tables. Put schema changes here.
- `sql/03_triggers.sql` — `trg_pago_estado_mensualidad` (confirmed payments covering an instalment mark it `PAGADA`) and `trg_visita_contador`.

Everything uses `IF NOT EXISTS` so it can run on top of an existing database; migrations have empty `down()` on purpose. State machines for each entity are listed in `docs/base-de-datos.md`.

### Payments (PagoFácil)

`app/Services/PasarelaPagoService.php` implements login → generate QR → client polling every 5 s → webhook (`POST /api/pagos/pasarela/callback`, authenticated by `PAGO_FACIL_WEBHOOK_SECRET`, idempotent). Default mode is **simulated**: the QR points to the SPA page `/pasarela-simulada/:numero`, which fires the same webhook. Real credentials are stored per payment method in the `pasarela_pago` table (set from the UI), not in `.env`. Real mode has not been tested.

Confirming a payment cascades: trigger marks the instalment paid → `MatriculaService` activates the enrolment if it was the initial payment and enrols the student in modules → the receipt is archived in the document repository.

## Frontend architecture

- `src/api/cliente.js` is the single Axios instance. It attaches `Authorization: Bearer` and `X-Rol-Activo` from `localStorage`, handles session expiry on 401, and exports helpers `mensajeError`, `erroresDeValidacion` (maps 422 `errors` to fields), `aFormData`, `abrirArchivo` (authenticated blob download).
- `src/routes/rutas.js` maps every page to its required permission (lazy-loaded). It only guards manual navigation; the **menu comes from the API**, not from this file.
- `src/context/` — `AuthContext` (session, active role, `auth.puede(permiso)`), `PreferenciasContext` (3 themes, day/night, high contrast), `AvisosContext` (toasts).
- `src/hooks/` — `useConsulta` (GET with params), `useFormulario` (client validation + server 422 errors), `useVisitas`.
- `src/components/ui/CrudSimple.jsx` is a generic list/search/create/edit/delete screen driven by props; simple catalogue pages (programs, modules, scholarships, fees) are just a `CrudSimple` configuration. Prefer it over hand-rolling a new CRUD page.
- `src/pages/<módulo>/` — one file per screen, grouped by the same use-case modules as the backend.
- Client-side validation rules with Spanish messages are in `src/utils/validacion.js`; theming variables in `src/styles/index.css`.

## Deployment coupling

The site is published under a subfolder (`…/inf513/grupoXXsa/proyecto2/`) with the API at `…/proyecto2/backend/public/api`. These settings must agree or links/QRs break:

- `VITE_BASE` and `VITE_API_URL` (frontend) with the published folder.
- `VITE_ROUTER` (`browser` | `hash`) with backend `FRONTEND_ROUTER`; `FRONTEND_URL` is used to build certificate-verification QR links and the simulated gateway URL (`app/Support/Frontend.php`).

See `docs/despliegue-tecnoweb.md` for the full procedure and known server issues.

## Known issue

If `npm run dev` fails with *Cannot find module '@rolldown/binding-win32-x64-msvc'*, delete `frontend/node_modules` and `frontend/package-lock.json` and run `npm install` again.
