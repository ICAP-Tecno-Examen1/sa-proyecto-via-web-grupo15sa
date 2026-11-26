# Manual del Coordinador Académico

← [Volver al índice](README.md)

Usted arma la oferta académica (programas, materias, mallas y ofertas), asigna docentes, acompaña el avance, cierra los cursos y valida el requisito **académico** del certificado.

**Su menú:**

- Oferta académica › **Programas** · **Materias** · **Mallas curriculares** · **Ofertas académicas**
- Inscripciones › **Preinscripciones** · **Matrículas**
- Académico › **Materias y calificaciones** (supervisión) · **Correcciones de nota**
- Certificación › **Requisitos y certificados**
- Repositorio documental · **Reportes** académicos

---

## 1. Armar el catálogo (una sola vez por programa)

El orden es: **materias → programa → malla → oferta**.

### 1.1 Materias

En **Materias**, registre cada materia con su **código**, **nombre**, **carga horaria** y una descripción. Una misma materia puede usarse en varios programas.

### 1.2 Programa

En **Programas**, registre el **código**, el **nombre**, la descripción y el **tipo de programa**:

| Tipo | Qué otorga |
|---|---|
| Curso / Taller | Certificado de Aprobación |
| Técnico Auxiliar / Técnico Medio / Técnico Superior | Título |

### 1.3 Malla curricular

En **Mallas curriculares** → **Nueva malla**:

1. Elija el programa y escriba la **versión** (ejemplo: `2026-1`).
2. Agregue las materias en orden y marque cuáles son **obligatorias**.
3. **En carreras técnicas**, agrupe las materias en **módulos** de 1 o 2 materias. Cada módulo dura **un mes**, y sus materias se dictan al mismo tiempo.
4. Pulse **Guardar malla** y déjela **Activa**.

Si el plan de estudios cambia, use **Nueva versión de la malla**: las ofertas anteriores conservan su malla.

## 2. Crear y abrir una oferta

1. **Ofertas académicas** → **Nueva oferta**: elija la malla activa, el código, la modalidad (presencial o virtual), las fechas, la gestión y los cupos mínimo y máximo. Pulse **Crear oferta**.
   - En carreras, las fechas de cada módulo se calculan solas (módulo 1 = mes 1, módulo 2 = mes 2…).
2. Abra la oferta. En **Materias y docentes**, pulse **Asignar docente** en cada materia, con los datos de su **contrato**.
3. Pida al **Coordinador Financiero** que cargue el **arancel** (plan de pago). Sin arancel, la oferta no se puede abrir.
4. Pulse **Abrir preinscripciones**: la oferta aparece en el catálogo público.

**Estados de la oferta:**

```
Planificada ──Abrir preinscripciones──► Abierta ──Iniciar clases──► En curso ──Finalizar curso──► Finalizada
     └────────────── Cancelar oferta (solo si no tiene matriculados) ──────────────┘
```

## 3. Preinscripciones

Igual que Secretaría, puede revisar las solicitudes en **Preinscripciones**:

- **Aprobar y generar matrícula**
- **Observar**
- **Rechazar**

Ver el detalle en [secretaria.md](secretaria.md#2-revisar-preinscripciones).

## 4. Durante el curso

- **Materias y calificaciones:** vea el avance de cada materia (asistencia y notas que registran los docentes).
- **Correcciones de nota:** los docentes piden corregir notas de materias ya cerradas. Abra cada solicitud y elija **Aprobar: actualizar la nota** o **Rechazar: mantener la nota anterior**, con su respuesta. Pulse **Registrar decisión**.
- **Matrículas → Inscribir en materias / Retirar de la materia:** ajustes de inscripción de un estudiante.

## 5. Finalizar el curso

Cuando **todos los docentes cerraron sus materias**:

1. Abra la oferta y pulse **Finalizar curso**. Lea la advertencia y confirme.
2. El sistema finaliza la matrícula de cada estudiante y le muestra un resumen:
   - **Aprobaron todo:** pueden pasar a certificación.
   - **Sin certificado hasta recuperar:** estudiantes con alguna materia reprobada, y cuáles son.

Si falta cerrar alguna materia, el sistema le dice cuál.

### Recuperar una materia reprobada

Matrículas → abra la matrícula → **Seguimiento académico** → **Recuperar** en la materia reprobada → elija una oferta vigente que dicte esa materia → **Inscribir para recuperar**. Cuando la apruebe, el estudiante cumple el requisito académico.

## 6. Validar el requisito académico

1. **Requisitos y certificados** → abra al estudiante.
2. El sistema muestra cada materia con ✔ o ✘ (las recuperadas se marcan **(recuperada)**).
3. Elija **Aprobado** u **Observado**, escriba una observación si hace falta y pulse **Registrar validación**.

Solo puede aprobar si **todas** las materias están aprobadas (nota ≥ 51 y asistencia ≥ 70 %).

## 7. Reportes académicos

- Rendimiento académico por módulo
- Asistencia por módulo
- Estudiantes aprobados y reprobados
- Docentes y módulos asignados

Se pueden filtrar, imprimir y descargar en **PDF** o **Excel**.
