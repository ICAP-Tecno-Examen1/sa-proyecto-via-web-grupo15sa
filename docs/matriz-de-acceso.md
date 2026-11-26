# Matriz de acceso (RBAC)

## Cómo funciona

1. Cada **permiso** es una acción (`pagos.conciliar`). Tabla `permiso`.
2. Cada **rol** tiene un conjunto de permisos. Tabla `rol_permiso`.
3. Un **usuario** puede tener varios roles (`usuario_rol`) y trabaja con uno a la vez: el **rol activo** (lo cambia desde el menú de su nombre).
4. Cada ruta de la API declara el permiso que exige: `->middleware('permiso:pagos.conciliar')`. Si el rol activo no lo tiene, la API responde **403** y lo anota en la bitácora.
5. El **menú** se lee de `menu_item`; cada opción indica el permiso que requiere. El cliente solo dibuja lo que la API le entrega.

El Director puede cambiar esta matriz en *Administración › Roles y permisos* sin tocar código. La matriz inicial se define en [RolPermisoSeeder](../backend/database/seeders/RolPermisoSeeder.php).

## Matriz inicial

✔ = concedido. Es la distribución propuesta; el grupo debe validarla.

| Permiso | Director | Secretaria | Coord. Académico | Coord. Financiero | Docente | Estudiante |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| **CU1** usuarios.ver / usuarios.gestionar | ✔ | | | | | |
| estudiantes.gestionar | | ✔ | | | | |
| roles.ver / roles.gestionar | ✔ | | | | | |
| bitacora.ver / estadisticas.accesos | ✔ | | | | | |
| **CU2** programas.ver | ✔ | ✔ | ✔ | | | |
| programas.gestionar | | | ✔ | | | |
| ofertas.ver | ✔ | ✔ | ✔ | ✔ | | |
| ofertas.gestionar / docentes.asignar | | | ✔ | | | |
| **CU3** preinscripciones.propias / expediente.propio / estudios.propios | | | | | | ✔ |
| preinscripciones.ver | ✔ | ✔ | ✔ | | | |
| preinscripciones.revisar | | ✔ | ✔ | | | |
| expedientes.gestionar / matriculas.gestionar | | ✔ | | | | |
| matriculas.ver | ✔ | ✔ | ✔ | ✔ | | |
| inscripciones.gestionar | | | ✔ | | | |
| **CU4** aranceles.ver | ✔ | ✔ | | ✔ | | |
| aranceles.gestionar / becas.gestionar / becas.asignar / metodos_pago.gestionar | | | | ✔ | | |
| becas.ver / metodos_pago.ver | ✔ | | | ✔ | | |
| pagos.propios | | | | | | ✔ |
| pagos.registrar | | ✔ | | ✔ | | |
| pagos.ver | ✔ | ✔ | | ✔ | | |
| pagos.conciliar | | ✔ | | ✔ | | |
| **CU5** docencia.ver | ✔ | | ✔ | | ✔ | |
| docencia.registrar / correcciones.solicitar | | | | | ✔ | |
| academico.supervisar | ✔ | | ✔ | | | |
| correcciones.ver | ✔ | | ✔ | | ✔ | |
| correcciones.resolver | | | ✔ | | | |
| **CU6** certificacion.ver | ✔ | ✔ | ✔ | ✔ | | |
| requisitos.academico | | | ✔ | | | |
| requisitos.financiero | | | | ✔ | | |
| requisitos.documental | | ✔ | | | | |
| certificados.autorizar | ✔ | | | | | |
| certificados.propios | | | | | | ✔ |
| **CU7** documentos.ver | ✔ | ✔ | ✔ | ✔ | ✔ | |
| documentos.registrar | | ✔ | ✔ | ✔ | ✔ | |
| documentos.gestionar | | ✔ | | | | |
| documentos.personal / .institucional | ✔ | ✔ | | | | |
| documentos.academico | ✔ | ✔ | ✔ | | | |
| documentos.financiero | ✔ | ✔ | | ✔ | | |
| **CU8** reportes.administrativos | ✔ | ✔ | | | | |
| reportes.academicos | ✔ | | ✔ | | | |
| reportes.financieros | ✔ | | | ✔ | | |
| reportes.docente | | | | | ✔ | |

## Decisiones que conviene validar en grupo

- **El Director supervisa y autoriza, pero no opera.** No concilia pagos ni revisa preinscripciones: eso mantiene separadas las funciones. Si el grupo prefiere que pueda hacerlo todo, basta marcarle los permisos.
- **El Docente y el Estudiante no tienen permisos de categoría en el repositorio**: ven solo los documentos que registraron o los de su propio expediente.
- **Validar un requisito del certificado exige el permiso de ese requisito**, y el sistema no deja aprobarlo si los datos no lo respaldan (por ejemplo, con deuda pendiente).
- **Quien solicita una corrección de nota no puede autorizarla.**
- **Cobro y revisión de pagos:** la Secretaría y el Coordinador Financiero (`pagos.conciliar`) reciben el aviso (campana de la cabecera, `GET /api/pagos/pendientes`) cuando un estudiante elige **Efectivo** (avisa que pagará en caja) o adjunta un **comprobante**; cualquiera de los dos lo cobra o confirma y la cuota queda pagada. Si el propio personal registra un cobro en efectivo, queda confirmado en el acto.
- **Becas por convenio:** la beca es el convenio (`beca.institucion_financiadora` = institución o empresa). La registra el Coordinador Financiero (`becas.gestionar`) en *Becas › Catálogo*; solo aplica si está ACTIVA y cubre el curso de la matrícula. La asigna directamente (`becas.asignar`) desde *Becas › Becas asignadas* o la ficha de la matrícula.
- **Becas:** el estudiante (`pagos.propios`) la solicita para su matrícula adjuntando el documento que pide la beca; solo el Coordinador Financiero (`becas.asignar`) ve el respaldo, la aprueba (puede ajustar el porcentaje) o la rechaza con motivo. Una beca solicitada no descuenta hasta ser aprobada.
- **Cuotas por cobrar** (`/cuentas-por-cobrar`, `pagos.ver`): Secretaría, Coordinador Financiero y Director ven quién debe qué cuota y cuándo vence; quien tiene `pagos.registrar` puede cobrar desde ahí.
- **Pago con tarjeta (Stripe):** iniciarlo (`POST /api/pagos/en-linea`) y la página de regreso `/pago/resultado` exigen `pagos.propios` o `pagos.registrar`; el estado del pago solo lo ve su dueño o quien tenga `pagos.ver`/`pagos.conciliar`. El webhook `POST /api/pagos/stripe/webhook` es público pero solo acepta avisos firmados por Stripe (`Stripe-Signature`).
- **Restablecer un rol base:** en *Roles y permisos*, el botón «Restablecer originales» (`POST /api/roles/{id}/restablecer`, `roles.gestionar`) devuelve el rol a esta matriz y quita lo marcado después. El seeder solo agrega permisos: no deshace cambios hechos desde la pantalla.
- El rol Director no puede perder los permisos de usuarios y roles, y no se puede dejar al sistema sin ningún Director activo.
