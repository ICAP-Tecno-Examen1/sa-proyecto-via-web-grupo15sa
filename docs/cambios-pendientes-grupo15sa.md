# Cambios pendientes para la base del grupo (db_grupo15sa)

Desde el **25/11/2026** el servidor `www.tecnoweb.org.bo` no acepta conexiones: responde al ping, pero no al puerto de PostgreSQL ni a la página web. Al otro grupo le pasa lo mismo. Mientras tanto se trabaja con una **base local** (`db_icap` en el PostgreSQL de la computadora).

Este archivo registra **todo lo que habrá que aplicar en `db_grupo15sa`** cuando el servidor vuelva. Cada cambio nuevo se agrega aquí.

## Cómo volver a la base del grupo

1. Comprobar que el servidor responde: abrir <https://www.tecnoweb.org.bo> o conectar con pgAdmin.
2. En `backend/.env`: comentar las 5 líneas `DB_*` de la base local y quitar el `# ` de las 5 líneas de la base del grupo (están justo debajo, marcadas).
3. Aplicar los pendientes, **en este orden**:

```powershell
cd backend
php artisan migrate          # P1: cambios de estructura (seguro en la base compartida)
```

Luego, en pgAdmin conectado a `db_grupo15sa`: *Query Tool* → abrir `backend/database/pendientes/grupo15sa.sql` → ejecutar. Ese archivo hace P2 y P3.

4. Hacer los pasos manuales de la tabla (P4 en adelante).
5. Marcar cada fila como hecha ✅ y anotar la fecha.

> ⚠️ **Nunca** `migrate:fresh`, `db:wipe` ni `migrate --seed` en la base del grupo: borran o duplican datos de todos.

## Registro de cambios

| # | Fecha | Cambio | Cómo se aplica en db_grupo15sa | Estado |
|---|---|---|---|---|
| P1 | 25/11/2026 | **Stripe reemplaza a PagoFácil.** Columnas `stripe_clave_secreta` y `stripe_secreto_webhook` en `metodo_pago_electronico`; el método «QR PagoFácil» pasa a llamarse «Tarjeta de crédito o débito (Stripe)». Script `sql/06_stripe.sql`. | `php artisan migrate` (migración `2026_11_25_000001_stripe_como_pasarela`) | ✅ 26/11/2026 |
| P2 | 25/11/2026 | **Menú: «Módulos» → «Materias»** y «Módulos y calificaciones» → «Materias y calificaciones». | `database/pendientes/grupo15sa.sql` | ✅ 26/11/2026 |
| P3 | 25/11/2026 | Descripción del permiso `metodos_pago.gestionar`: «…la pasarela Stripe». | `database/pendientes/grupo15sa.sql` | ✅ 26/11/2026 |
| P4 | 25/11/2026 | **Claves de Stripe** del ambiente del grupo. | Manual: `coord.financiero` → *Finanzas › Métodos de pago* → editar «Tarjeta de crédito o débito (Stripe)» → pegar `sk_test_…`/`sk_live_…` y `whsec_…`. | ✅ 26/11/2026 (usa las claves `sk_test`/`pk_test` del `.env`; cargarlas en *Métodos de pago* es opcional) |
| P5 | 25/11/2026 | El menú cambiado (P2) solo se ve al volver a iniciar sesión. | Cerrar sesión y volver a entrar. | ⏳ Cada usuario: cerrar sesión y volver a entrar |
| P6 | 26/11/2026 | **Pago con tarjeta dentro del sistema** (Stripe Embedded Checkout): columna `stripe_clave_publica`. Script `sql/07_stripe_integrado.sql`. | `php artisan migrate` (migración `2026_11_26_000001_stripe_integrado`) y luego cargar la **clave publicable** `pk_…` en *Métodos de pago* | ✅ 26/11/2026 |
| P7 | 26/11/2026 | **La Secretaría también concilia pagos** (permiso `pagos.conciliar`): cobra el efectivo y revisa comprobantes, igual que el Coordinador Financiero. | `database/pendientes/grupo15sa.sql` | ✅ 26/11/2026 |
| P9 | 26/11/2026 | **Solicitudes de beca con respaldo**: columnas en `beca` (`requisitos`) y `beca_estudiante` (motivo, documento, revisión). Rellena los requisitos de las 4 becas iniciales. Script `sql/08_solicitudes_beca.sql`. | `php artisan migrate` (migración `2026_11_26_000002_solicitudes_de_beca`) | ✅ 26/11/2026 |
| P10 | 26/11/2026 | **Menú «Cuotas por cobrar»** (Finanzas). | `database/pendientes/grupo15sa.sql` | ✅ 26/11/2026 |
| P11 | 26/11/2026 | **Becas con estado y cursos** (diseño original: la beca es el convenio; la institución va en `beca.institucion_financiadora`). Agrega `beca.estado` (ACTIVA/INACTIVA) y la tabla `beca_oferta` (cursos que cubre; sin filas = todos). Script `sql/09_becas_estado_y_cursos.sql`. | `php artisan migrate` (migración `2026_11_26_000003_becas_estado_y_cursos`) | ✅ 26/11/2026 |
| P12 | 26/11/2026 | **Cargar las becas por convenio reales**: institución financiadora, porcentaje, cursos que cubre, documento que deben presentar y estado. | Manual: `coord.financiero` → *Finanzas › Becas › Catálogo de becas* | ⏳ Pendiente |
| P13 | 26/11/2026 | **El Coordinador Académico también acepta preinscripciones** (permiso `preinscripciones.revisar`, además de la Secretaría). | `database/pendientes/grupo15sa.sql` | ✅ 26/11/2026 |
| P14 | 26/11/2026 | **Asistencia mínima del 70 %** para aprobar una materia (antes 80 %). Rige para las materias que se cierren desde ahora. | Configuración: en el `.env` del servidor, `ICAP_ASISTENCIA_MINIMA=70` (o quitar la línea: el valor por defecto ya es 70). | ✅ 26/11/2026 |
| P15 | 26/11/2026 | **Certificado con el formato oficial del ICAP** (A4 vertical, encabezado gótico, QR, foto, firmas digitales; el sello se pone a mano). Tabla `plantilla_certificado` y columna `usuario.lugar_nacimiento`. Script `sql/10_plantilla_certificado.sql`. | `php artisan migrate` (migración `2026_11_26_000004_plantilla_certificado`). Luego, como `director`: *Requisitos y certificados › Plantilla del certificado* → nombres de los firmantes, **subir las dos firmas** y los escudos (UAGRM y Bolivia). Las imágenes subidas en local no viajan: hay que volver a subirlas en el servidor. | ✅ 26/11/2026 |
| P8 | 26/11/2026 | Descripción del método **Efectivo** para el estudiante (ahora puede elegirlo para avisar que pagará en caja). | `database/pendientes/grupo15sa.sql` | ✅ 26/11/2026 |

## Lo que ya está aplicado en db_grupo15sa (no repetir)

- **26/11/2026: el servidor volvió y se aplicaron P1–P15** (`php artisan migrate` con las 5 migraciones pendientes + `database/pendientes/grupo15sa.sql`). Se comprobó que menú, permisos por rol (igual a `RolPermisoSeeder::MATRIZ`), métodos de pago y plantilla del certificado coinciden con la base local. Antes se respaldaron las tablas tocadas en `backend/database/local/respaldos/db_grupo15sa-20261126/` (CSV; `pg_dump` 17 no sirve con el servidor PostgreSQL 18). El `.env` vuelve a apuntar a `db_grupo15sa`.

- Correos de las 7 cuentas iniciales cambiados a `usuario@icap.edu.bo` (25/11/2026).
- Catálogo de ejemplo (`CatalogoEjemploSeeder`): 6 programas `EJ-…` con malla, oferta abierta, aranceles y docentes.
- Programa `aux01` con su malla 2026-1, creados desde la interfaz.
- Migraciones hasta `2026_11_22_000002` (módulos de carrera y tabla `metodo_pago_electronico`).

## Base local de respaldo: prueba15sa

Base local (PostgreSQL 17, usuario `postgres`) para trabajar mientras el servidor del grupo no responde. El 25/11/2026 se **vació y reconstruyó desde cero** (`php artisan migrate:fresh --seed`), sin los datos del primer parcial:

- 7 migraciones (esquema base, ajustes del segundo parcial, triggers, módulos de carrera, Stripe): 38 tablas.
- Seeders: `DatabaseSeeder` (roles, 55 permisos, menú con «Materias», métodos de pago con Stripe, 7 cuentas `@icap.edu.bo` con contraseña `Icap2026*`), `CarreraEjemploSeeder` (Auxiliar Contable) y `CatalogoEjemploSeeder` (6 programas `EJ-…` con oferta abierta).
- Para volver a dejarla así en cualquier momento: con `.env` apuntando a `prueba15sa`, ejecutar `php artisan migrate:fresh --seed` y luego `php artisan db:seed --class=CarreraEjemploSeeder` y `php artisan db:seed --class=CatalogoEjemploSeeder`. **Solo en esta base local, nunca en db_grupo15sa.**
- El estado anterior (datos del primer parcial) quedó respaldado en `backend/database/local/respaldos/prueba15sa-antes-de-actualizar.sql` (fuera de git).

## Datos creados en la base local

Lo que se registre **desde la interfaz** en la base local (programas, materias, mallas, ofertas, estudiantes, pagos…) **no pasa solo** a la base del grupo, porque son bases distintas y los identificadores no coinciden. Si algo debe quedar también en el grupo:

- **Datos de ejemplo:** se vuelven a cargar con su seeder (por ejemplo `php artisan db:seed --class=CatalogoEjemploSeeder`, que no duplica).
- **Datos reales:** anótelos en la tabla de abajo para volver a registrarlos desde la interfaz.

| Fecha | Qué se creó o cambió en local | ¿Hay que repetirlo en el grupo? |
|---|---|---|
| | | |

## Regla para los cambios que vengan

- **Estructura** (tablas, columnas): nuevo archivo en `backend/database/sql/` y su migración. Llega al grupo con `php artisan migrate`.
- **Corrección de datos** (textos del menú, permisos, catálogos): agregar el `UPDATE`/`INSERT` idempotente a `backend/database/pendientes/grupo15sa.sql` y una fila en la tabla de arriba.
- **Configuración** (claves, parámetros): una fila con el paso manual.
