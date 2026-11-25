# Arquitectura de 3 capas

```
┌──────────────────────────────────────────────────────────────┐
│ CAPA 1 · PRESENTACIÓN            frontend/  (React SPA)       │
│ Páginas, temas, accesibilidad, validación en el cliente       │
└───────────────────────────┬──────────────────────────────────┘
                            │  JSON sobre HTTP (Axios)
                            │  Authorization: Bearer <token>   X-Rol-Activo: <id_rol>
                            ▼
┌──────────────────────────────────────────────────────────────┐
│ CAPA 2 · LÓGICA DE NEGOCIO       backend/  (Laravel API)      │
│ Rutas → middleware (sesión, permiso, bitácora) →              │
│ controlador (valida) → servicio (reglas de negocio) → modelo  │
└───────────────────────────┬──────────────────────────────────┘
                            │  SQL transaccional (Eloquent)
                            ▼
┌──────────────────────────────────────────────────────────────┐
│ CAPA 3 · PERSISTENCIA            PostgreSQL                   │
│ Tablas del script del grupo + ajustes, triggers y contadores  │
└──────────────────────────────────────────────────────────────┘
```

El cliente **no** tiene reglas de negocio ni accede a la base de datos: solo llama a la API. La API no genera HTML: solo responde JSON (y archivos PDF/CSV).

## Capa 1 · `frontend/src`

| Carpeta | Contenido |
|---|---|
| `api/cliente.js` | Único punto de comunicación con la API (token, rol activo, errores, archivos) |
| `routes/rutas.js` | Mapa de páginas y el permiso que exige cada una |
| `context/` | Sesión (`AuthContext`), temas y accesibilidad (`PreferenciasContext`), avisos |
| `hooks/` | `useFormulario` (validación cliente + servidor), `useConsulta`, `useVisitas` |
| `utils/` | `validacion.js` (reglas con mensajes en español) y `formato.js` |
| `layouts/LayoutSitio.jsx` | Encabezado con búsqueda, menú dinámico y pie con el contador de visitas |
| `components/ui/` | Piezas comunes: botón, campo, tabla, modal, `CrudSimple` |
| `components/<módulo>/` | Piezas compartidas de un módulo (pago por QR, estado de cuenta, expediente…) |
| `pages/<módulo>/` | Una página por pantalla, agrupadas por caso de uso |
| `styles/index.css` | Identidad visual: variables de los 3 temas, día/noche y alto contraste |

## Capa 2 · `backend/app`

| Carpeta | Contenido |
|---|---|
| `Http/Controllers/Api/<módulo>/` | Reciben la petición, **validan** y delegan. Un grupo por caso de uso |
| `Http/Middleware/` | `VerificarPermiso` (matriz RBAC), `VerificarUsuarioActivo`, `RegistrarBitacora` |
| `Services/` | **Reglas de negocio**: matrícula, pagos, pasarela, evaluación, certificación, documentos, reportes |
| `Models/` | Un modelo Eloquent por tabla, con los estados como constantes |
| `Exceptions/ReglaNegocioException` | Regla incumplida → respuesta 422 con mensaje en español |
| `Support/` | Generación de QR y enlaces hacia el cliente |
| `routes/api.php` | Todas las rutas con el permiso que exige cada una (la matriz de acceso) |
| `config/icap.php` | Parámetros del negocio: nota mínima, asistencia mínima, documentos requeridos, PagoFácil |
| `lang/es/validation.php` | Mensajes de validación en español |

| Módulo de controladores | Caso de uso |
|---|---|
| `Acceso` | CU1 usuarios, roles, perfil |
| `Academico`, `Publico` | CU2 programas, mallas, ofertas, catálogo |
| `Inscripcion` | CU3 preinscripción, expediente, matrícula |
| `Finanzas` | CU4 aranceles, becas, pagos, PagoFácil |
| `Evaluacion` | CU5 asistencia, notas, correcciones |
| `Certificacion` | CU6 requisitos y certificados |
| `Documental` | CU7 repositorio |
| `Reportes`, `Sistema` | CU8 reportes, panel, bitácora, visitas, búsqueda |

## Capa 3 · `backend/database`

`sql/01_esquema_base.sql` (script del grupo), `sql/02_ajustes_parcial2.sql` y `sql/03_triggers.sql`. Las migraciones solo ejecutan esos archivos, así el SQL sigue siendo la fuente de verdad. Detalle en [base-de-datos.md](base-de-datos.md).

## Recorrido de una petición

*El Coordinador Financiero confirma un pago:*

1. `pages/finanzas/Pagos.jsx` valida el formulario y llama `POST /api/pagos/15/conciliar`.
2. `auth:sanctum` identifica al usuario; `permiso:pagos.conciliar` consulta `rol_permiso` para su rol activo.
3. `PagoController@conciliar` valida la entrada (mensajes en español) y llama a `PagoService::conciliar`.
4. `PagoService` confirma el pago dentro de una transacción. El trigger `trg_pago_estado_mensualidad` marca la cuota como pagada; `MatriculaService` activa la matrícula si era el pago inicial e inscribe al estudiante en sus módulos; el comprobante se archiva en el repositorio.
5. `RegistrarBitacora` deja el rastro de la acción (usuario, IP, recurso).
6. La respuesta JSON vuelve al cliente, que actualiza la pantalla.
