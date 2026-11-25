# Dónde se cumple cada requisito

## Requisitos técnicos del enunciado

| # | Requisito | Dónde está |
|---|---|---|
| 1 | Diseño y navegación; barra dinámica según permisos | `frontend/src/layouts/LayoutSitio.jsx` |
| 2 | RBAC con 2+ roles de negocio; menú desde la BD | 6 roles. Tablas `rol`, `permiso`, `rol_permiso`, `menu_item`. `MenuService`, pantalla *Roles y permisos* |
| 3 | Laravel API RESTful + React con cliente HTTP | `backend/routes/api.php` · `frontend/src/api/cliente.js` |
| 4 | Matriz de acceso | Middleware `permiso:` en cada ruta (`VerificarPermiso`) y `Guardia` en `App.jsx` |
| 4 | Bitácora: logins exitosos y fallidos, IP, recursos más accedidos | Tabla `bitacora`. `AuthController`, `RegistrarBitacora`. Pantallas *Bitácora* y *Estadísticas de acceso* |
| 5 | Estilo único, 3 temas, día/noche automático o manual | `styles/index.css`, `PreferenciasContext`, `ControlesVisuales` (botón *Apariencia*) |
| 5 | Accesibilidad: tamaño de letra y contraste | Mismo panel: deslizador de letra y alto contraste |
| 6 | Validación en cliente y servidor, en español | `utils/validacion.js` + `useFormulario` · `$request->validate()` + `lang/es/validation.php` |
| 7 | Contador de visitas por página en el pie | Tablas `visita` y `contador_visita` + trigger. `useVisitas`, pie de `LayoutSitio` |
| 8 | Panel con gráficos del negocio y de accesos | `PanelService` · páginas *Panel* y *Estadísticas de acceso* |
| 9 | Búsqueda global en el encabezado | `Buscador.jsx` (predictiva) · `BusquedaController` |
| 10 | Métodos de pago, pagos únicos y planes de pago | *Métodos de pago*, *Aranceles*, *Mis pagos*, *Pagos y conciliación* · `PagoService`, `PagoFacilService` |

## Casos de uso

| CU | Pantallas principales | Servicio |
|---|---|---|
| CU1 Usuarios | Registro, Ingresar, Perfil, Usuarios, Estudiantes, Roles y permisos | `UsuarioService`, `MenuService` |
| CU2 Programas, mallas y ofertas | Programas, Módulos, Mallas (con *Nueva versión*), Ofertas, Catálogo público | `MallaService`, `OfertaService` |
| CU3 Preinscripción y expediente | Catálogo → Preinscribirme, Mi expediente, Preinscripciones, Matrículas, Mis estudios | `MatriculaService`, `DocumentoService` |
| CU4 Aranceles, becas y pagos | Aranceles, Becas, Métodos de pago, Mis pagos, Pagos y conciliación | `PagoService`, `PagoFacilService` |
| CU5 Asistencia y notas | Mis módulos → Planilla (calificaciones, asistencia, evaluaciones), Correcciones | `EvaluacionService` |
| CU6 Certificados | Certificación (validar requisitos, emitir), Mis certificados, Verificar certificado (pública) | `CertificacionService` |
| CU7 Repositorio | Repositorio documental | `DocumentoService` |
| CU8 Reportes | Panel, Reportes (15 reportes; imprimir, CSV, PDF) | `ReporteService`, `PanelService` |

## Reglas de negocio configurables (`backend/config/icap.php`)

- Nota mínima de aprobación **51** y asistencia mínima **80 %** (`ICAP_NOTA_MINIMA`, `ICAP_ASISTENCIA_MINIMA`).
- Nota final = Σ (calificación × ponderación); las ponderaciones deben sumar 100 % para cerrar un módulo.
- La primera cuota vence a los **7 días** de la matrícula; las siguientes, cada mes.
- Documentos requeridos del expediente: cédula de identidad, título o certificado de estudios y fotografía.

Estos valores son una propuesta: ajústenlos a lo que defina el grupo.
