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
| preinscripciones.revisar / expedientes.gestionar / matriculas.gestionar | | ✔ | | | | |
| matriculas.ver | ✔ | ✔ | ✔ | ✔ | | |
| inscripciones.gestionar | | | ✔ | | | |
| **CU4** aranceles.ver | ✔ | ✔ | | ✔ | | |
| aranceles.gestionar / becas.gestionar / becas.asignar / metodos_pago.gestionar | | | | ✔ | | |
| becas.ver / metodos_pago.ver | ✔ | | | ✔ | | |
| pagos.propios | | | | | | ✔ |
| pagos.registrar | | ✔ | | ✔ | | |
| pagos.ver | ✔ | ✔ | | ✔ | | |
| pagos.conciliar | | | | ✔ | | |
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
- El rol Director no puede perder los permisos de usuarios y roles, y no se puede dejar al sistema sin ningún Director activo.
