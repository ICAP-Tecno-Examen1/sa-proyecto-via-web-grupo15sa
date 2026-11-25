# Usuarios y contraseñas · ICAP UAGRM

Cuentas de la base compartida **`db_grupo15sa`** (creadas con `php artisan db:seed`).

> ⚠️ **Son cuentas de desarrollo.** La contraseña es la misma para todas y está escrita aquí.
> Cámbienla antes de publicar el sitio (**Perfil → Cambiar contraseña**) y no usen estas contraseñas en otro lugar.

## Cuentas del sistema

**Contraseña de todas: `Icap2026*`**

| Usuario | Nombre | Rol | Qué puede hacer |
|---|---|---|---|
| `director` | Roberto Suárez Antelo | Director | Usuarios, roles y permisos, bitácora, estadísticas de acceso, autoriza certificados, reportes |
| `secretaria` | María Elena Vaca Justiniano | Secretaria | Estudiantes, preinscripciones, matrículas, pagos en efectivo, validación documental, repositorio |
| `coord.academico` | Jorge Luis Méndez Rojas | Coordinador Académico | Programas, mallas, ofertas, docentes, inscripciones, correcciones de nota, validación académica |
| `coord.financiero` | Patricia Gutiérrez Salvatierra | Coordinador Financiero | Aranceles, becas, métodos de pago, conciliación de pagos, validación financiera |
| `docente` | Carlos Alberto Rivero Paz | Docente | Sus módulos: asistencia, evaluaciones, notas y solicitud de corrección |
| `docente2` | Ana Lucía Fernández Cuéllar | Docente | Igual que `docente`, con sus propios módulos |
| `estudiante` | Luis Fernando Montaño Égüez | Estudiante | Catálogo, preinscripción, expediente, pagos, notas y certificados |

Para ingresar, usen el **usuario** (o el correo `usuario@icap.edu.bo`) y la contraseña.

## Cómo probar el flujo con cada cuenta

1. **`coord.academico`**: crear programa → módulos → malla → oferta (asignar docentes).
2. **`coord.financiero`**: configurar aranceles de la oferta.
3. **Registrar un estudiante nuevo** desde la página de registro (no hace falta usar `estudiante`).
4. **`secretaria`**: aprobar la preinscripción (genera matrícula).
5. **Estudiante**: pagar la primera cuota (QR de PagoFácil o comprobante).
6. **`coord.financiero`**: confirmar el pago con comprobante.
7. **`docente`**: registrar asistencia, evaluaciones y notas; cerrar el módulo.
8. **`director`**: autorizar el certificado y revisar el panel, reportes y bitácora.

## Notas

- Un usuario puede tener varios roles. Con el selector de la cabecera cambia el **rol activo** y el menú se actualiza.
- Si una cuenta aparece **desactivada**, la reactiva el Director en *Usuarios*.
- La base de datos de tu PostgreSQL local (`prueba15sa`) tiene su propio conjunto de usuarios; estas cuentas no existen ahí si no ejecutaron los seeders allí.
