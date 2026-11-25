# Base de datos

Los tres archivos de `backend/database/sql/` se ejecutan en orden con `php artisan migrate`. Todo usa `IF NOT EXISTS`: puede correrse sobre la base que ya tiene las tablas del primer parcial **sin borrar ni duplicar nada**.

| Archivo | Contenido |
|---|---|
| `01_esquema_base.sql` | El script aprobado por el grupo, sin cambios de estructura |
| `02_ajustes_parcial2.sql` | Columnas y tablas nuevas (solo agrega) |
| `03_triggers.sql` | Triggers y contadores |

> ⚠️ **Nunca ejecuten `php artisan migrate:fresh` contra la base compartida de tecnoweb**: borra todas las tablas del esquema. Usen solo `php artisan migrate`.

## Ajustes al script del grupo

Son los «pequeños ajustes» previstos para conciliación, requisitos y repositorio, más lo que piden los requisitos técnicos. Deben validarse en grupo.

### Tablas nuevas

| Tabla | Para qué |
|---|---|
| `menu_item` | Menú dinámico: cada opción con el permiso que exige |
| `bitacora` | Auditoría: logins exitosos y fallidos, IP, acciones, accesos denegados |
| `visita`, `contador_visita` | Contador de visitas por página |
| `metodo_pago_electronico` | Credenciales de PagoFácil por método de pago (token service/secret, callback, activo). Antes `pasarela_pago` |
| `modulo_carrera` | Módulo de una carrera dentro de la malla: agrupa 1 o 2 materias (`modulo`). Script `sql/04_bloques_carrera.sql` |
| `validacion_requisito` | Validación académica, financiera y documental previa al certificado |
| `personal_access_tokens` | Sesiones de la API (Laravel Sanctum) |

### Columnas agregadas

| Tabla | Columnas | Motivo |
|---|---|---|
| `arancel` | `numero_cuotas` | Distinguir pago único de mensualidades |
| `preinscripcion` | `id_arancel`, `id_usuario_revisor`, `fecha_revision` | Plan elegido y quién revisó |
| `matricula` | `id_preinscripcion`, `fecha_finalizacion` | Trazabilidad y cierre del programa |
| `inscripcion_modulo` | `nota_final`, `porcentaje_asistencia` | Resultado fijado al cerrar el módulo |
| `metodo_pago` | `tipo` (EFECTIVO, COMPROBANTE, PASARELA) | Cómo se procesa cada método |
| `pago` | `id_usuario_registro`, `id_usuario_conciliador`, `fecha_conciliacion`, `observacion_conciliacion`, `numero_transaccion`, `pasarela_transaccion_id` | Conciliación y transacción de la pasarela |
| `correccion_nota` | `observacion_autorizador` | Respuesta del Coordinador Académico |
| `documento` | `categoria`, `estado`, `nombre_archivo`, `tipo_mime`, `tamano_bytes`, `id_usuario_registro`, `fecha_registro` | Clasificación, archivo histórico y control de acceso |

### Decisiones sobre columnas existentes

- La contraseña se guarda cifrada (bcrypt) en `usuario.password_hash`. La columna `usuario.password` no se usa.
- La oferta nace en estado `PLANIFICADA` (nuevo valor de `oferta_academica.estado`): no aparece en el catálogo hasta que tiene costos y se abre.
- Todo pago se asocia a una cuota (`pago.id_mensualidad`); un plan de pago único genera una sola cuota.

## Triggers

| Trigger | Tabla | Qué hace |
|---|---|---|
| `trg_visita_contador` | `visita` | Cada visita incrementa el acumulado de su página en `contador_visita` |
| `trg_pago_estado_mensualidad` | `pago` | Al confirmarse pagos que cubren la cuota, la marca `PAGADA` (y la devuelve a `PENDIENTE` si dejan de cubrirla) |

## Estados

| Entidad | Estados |
|---|---|
| Oferta | PLANIFICADA → ABIERTA → EN_CURSO → FINALIZADA · CANCELADA |
| Preinscripción | PENDIENTE → OBSERVADA ⇄ PENDIENTE → APROBADA · RECHAZADA |
| Matrícula | PENDIENTE_PAGO → ACTIVA → FINALIZADA · ANULADA |
| Cuota (mensualidad) | PENDIENTE → PAGADA · ANULADA |
| Pago | PENDIENTE → CONFIRMADO · OBSERVADO · RECHAZADO |
| Inscripción a módulo | INSCRITO → APROBADO · REPROBADO · RETIRADO |
| Módulo de la oferta | ACTIVO → CERRADO |
| Corrección de nota | PENDIENTE → APROBADA · RECHAZADA |
| Requisito del certificado | PENDIENTE → APROBADO · OBSERVADO |
| Certificado | EMITIDO → ANULADO |

## Niveles de programa

`programa.tipo` admite `CURSO`, `TALLER`, `SEMINARIO`, `DIPLOMADO`, `TECNICO_MEDIO`, `TECNICO_SUPERIOR` y `PROGRAMA_TECNICO` (este último se conserva por compatibilidad).

## Materias y módulos de la carrera

- `modulo` es la **materia**: se califica, tiene asistencia y evaluaciones propias.
- `modulo_carrera` es el **módulo de la carrera** dentro de una malla: agrupa 1 o 2 materias (`malla_modulo.id_modulo_carrera`). La API rechaza grupos con 0 o más de 2 materias.
- Ejemplo cargado con `php artisan db:seed --class=CarreraEjemploSeeder --force`: Auxiliar Contable (técnico medio), con 4 módulos y 5 materias.
