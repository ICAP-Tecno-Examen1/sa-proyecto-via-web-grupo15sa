# Manual de usuario · Sistema ICAP – UAGRM

Este manual explica cómo usar el sistema según su rol. Empiece por esta página (lo que es igual para todos) y luego abra el manual de su rol:

| Rol | Manual | En pocas palabras |
|---|---|---|
| Director | [director.md](director.md) | Usuarios y permisos, supervisión, autoriza y emite certificados |
| Secretaría | [secretaria.md](secretaria.md) | Estudiantes, preinscripciones, matrículas, cobro en efectivo, documentos |
| Coordinador Académico | [coordinador-academico.md](coordinador-academico.md) | Programas, materias, mallas, ofertas, docentes, cierre de cursos |
| Coordinador Financiero | [coordinador-financiero.md](coordinador-financiero.md) | Aranceles, becas, métodos de pago, conciliación y deudas |
| Docente | [docente.md](docente.md) | Asistencia, evaluaciones, notas y cierre de sus materias |
| Estudiante | [estudiante.md](estudiante.md) | Preinscripción, expediente, pagos, notas y certificados |

---

## 1. Ingresar al sistema

1. Abra la página del ICAP y pulse **Ingresar**.
2. Escriba su **usuario** (o su correo) y su **contraseña**. El ojito 👁 muestra lo que escribió.
3. Siempre entra al **Panel**, con los indicadores de su rol.

Si se equivoca varias veces o su cuenta está desactivada, el sistema no lo deja pasar: pida ayuda al Director.

**¿Es estudiante nuevo?** Pulse **Crear mi cuenta** en la página de inicio, o **Registrarme y preinscribirme** desde un programa del catálogo.

## 2. La pantalla

- **Menú lateral:** muestra solo lo que su rol puede usar. Si el Director cambia sus permisos, el menú se actualiza solo en unos minutos (o al volver a entrar).
- **Rol activo:** si usted tiene más de un rol (por ejemplo, Docente y Coordinador Académico), elíjalo en el selector de la cabecera. El menú cambia según el rol elegido.
- **Campana 🔔:** avisos que requieren su atención (por ejemplo, pagos en efectivo por cobrar).
- **Apariencia:** desde la cabecera puede cambiar el tema de colores, el modo día/noche y el alto contraste.
- **Mi perfil:** sus datos personales y **Cambiar contraseña**. La contraseña debe tener al menos 8 caracteres, con letras y números.

## 3. Cómo funcionan las listas y formularios

- Las listas tienen un **buscador**: escriba y los resultados se filtran solos.
- En los campos desplegables también puede **escribir para buscar** la opción.
- Si falta un dato o está mal, el campo se marca en rojo con el motivo debajo.
- Las acciones importantes (anular, rechazar, cerrar) piden **confirmación**.
- Todo lo que se registra, cambia o elimina queda anotado en la **bitácora** con su usuario, la fecha y la IP.

## 4. El camino completo de un estudiante

Cada rol hace una parte. Este es el orden:

```
 Coord. Académico       Coord. Financiero        Estudiante              Secretaría / C. Académico
 ─────────────────      ─────────────────        ──────────────          ─────────────────────────
 Programa, materias  →  Arancel (plan de pago) →  Se registra y      →   Revisa la preinscripción
 malla y oferta         de la oferta              se preinscribe         y la aprueba → MATRÍCULA
 (abre preinscripción)                            (sube documentos)

 Estudiante              Secretaría / C. Financiero     Docente                    Coord. Académico
 ──────────────          ──────────────────────────     ─────────────────          ─────────────────
 Paga la 1.ª cuota   →   Confirma el pago           →   Asistencia, notas,    →    «Finalizar curso»
 (tarjeta, comprobante   (la matrícula se ACTIVA y      y cierra cada materia      en la oferta
  o efectivo)             se inscribe en materias)

 C. Académico / C. Financiero / Secretaría        Director                     Estudiante
 ────────────────────────────────────────         ──────────────────           ─────────────────
 Validan los requisitos académico,          →     Autoriza y emite el      →   Descarga su
 financiero y documental                          certificado (PDF + QR)       certificado
```

**Reglas para obtener el certificado:**

- Aprobar **todas** las materias: nota mínima **51** y al menos **70 %** de asistencia en cada una.
- Quien reprueba una materia no recibe el certificado hasta **recuperarla** (cursarla de nuevo en otra oferta y aprobarla).
- No tener cuotas pendientes.
- Tener completo el expediente: **cédula de identidad, título o certificado de estudios y fotografía 4x4**.

**Estados que verá en el sistema:**

| Qué | Estados |
|---|---|
| Oferta | Planificada → Abierta (recibe preinscripciones) → En curso → Finalizada (o Cancelada) |
| Preinscripción | Pendiente → Aprobada / Observada (el estudiante corrige y reenvía) / Rechazada |
| Matrícula | Pendiente de pago → Activa → Finalizada (o Anulada) |
| Materia del estudiante | Inscrito → Aprobado / Reprobado |
| Pago | Pendiente → Confirmado / Observado / Rechazado |
| Certificado | Emitido / Anulado |

## 5. Verificar un certificado (sin cuenta)

Cualquier persona puede comprobar si un certificado es auténtico:

- escaneando el **código QR** impreso en el certificado, o
- entrando a **Verificar un certificado** en la página de inicio y escribiendo el **código único** (por ejemplo `ICAP-2026-KLZSQ8KB`).

El sistema muestra si es **válido** o **anulado**, a quién pertenece y qué programa aprobó.

## 6. Preguntas frecuentes

- **No veo una opción del menú.** Su rol no tiene ese permiso, o tiene otro rol activo. Cambie el rol en la cabecera o consulte al Director.
- **Me dice «sesión expirada».** Vuelva a ingresar; por seguridad la sesión vence.
- **Un archivo no se sube.** Se aceptan PDF, JPG, PNG y WEBP de hasta 4 MB.
- **Abrí un reporte en Excel y salió todo en una columna.** Use el botón **Excel** (archivo .xlsx), no un CSV.
