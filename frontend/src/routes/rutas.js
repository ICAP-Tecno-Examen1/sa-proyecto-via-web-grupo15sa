import { lazy } from 'react'

/**
 * Mapa de páginas del cliente.
 *
 *   ruta     patrón de React Router (también es la clave del contador de visitas)
 *   titulo   título de la página (pestaña del navegador y pie de página)
 *   permiso  permiso exigido; "a|b" significa que basta uno. Sin permiso = cualquier sesión
 *   publica  no requiere iniciar sesión
 *   invitado solo para quien NO tiene sesión (ingresar, registro)
 *   pantallaCompleta  la página ocupa todo el ancho y maneja sus propios márgenes (portada)
 *
 * El menú NO sale de aquí: lo entrega la API desde la tabla menu_item. Este
 * mapa solo impide abrir a mano una página para la que el rol no tiene permiso
 * (la API vuelve a comprobarlo en cada petición).
 */
const p = (carga) => lazy(carga)

export const RUTAS = [
  // --- Públicas
  { ruta: '/', titulo: 'Inicio', publica: true, pantallaCompleta: true, pagina: p(() => import('../pages/publico/Inicio')) },
  { ruta: '/catalogo', titulo: 'Catálogo de programas', publica: true, pagina: p(() => import('../pages/publico/Catalogo')) },
  { ruta: '/catalogo/:id', titulo: 'Detalle del programa', publica: true, pagina: p(() => import('../pages/publico/DetalleOferta')) },
  { ruta: '/verificar', titulo: 'Verificar certificado', publica: true, pagina: p(() => import('../pages/publico/VerificarCertificado')) },
  { ruta: '/verificar/:codigo', titulo: 'Verificar certificado', publica: true, pagina: p(() => import('../pages/publico/VerificarCertificado')) },
  { ruta: '/ingresar', titulo: 'Iniciar sesión', publica: true, invitado: true, pagina: p(() => import('../pages/publico/Ingresar')) },
  { ruta: '/registro', titulo: 'Registro de estudiantes', publica: true, invitado: true, pagina: p(() => import('../pages/publico/Registro')) },

  // --- Cualquier usuario con sesión
  { ruta: '/panel', titulo: 'Panel', pagina: p(() => import('../pages/panel/Panel')) },
  { ruta: '/perfil', titulo: 'Mi perfil', pagina: p(() => import('../pages/panel/Perfil')) },

  // --- CU1 · Usuarios, roles y auditoría
  { ruta: '/usuarios', titulo: 'Usuarios', permiso: 'usuarios.ver', pagina: p(() => import('../pages/acceso/Usuarios')) },
  { ruta: '/estudiantes', titulo: 'Estudiantes', permiso: 'estudiantes.gestionar', pagina: p(() => import('../pages/acceso/Estudiantes')) },
  { ruta: '/roles', titulo: 'Roles y permisos', permiso: 'roles.ver', pagina: p(() => import('../pages/acceso/Roles')) },
  { ruta: '/bitacora', titulo: 'Bitácora', permiso: 'bitacora.ver', pagina: p(() => import('../pages/acceso/Bitacora')) },
  { ruta: '/estadisticas-accesos', titulo: 'Estadísticas de acceso', permiso: 'estadisticas.accesos', pagina: p(() => import('../pages/acceso/EstadisticasAccesos')) },

  // --- CU2 · Oferta académica
  { ruta: '/programas', titulo: 'Programas', permiso: 'programas.ver', pagina: p(() => import('../pages/academico/Programas')) },
  { ruta: '/modulos', titulo: 'Materias', permiso: 'programas.ver', pagina: p(() => import('../pages/academico/Modulos')) },
  { ruta: '/mallas', titulo: 'Mallas curriculares', permiso: 'programas.ver', pagina: p(() => import('../pages/academico/Mallas')) },
  { ruta: '/ofertas', titulo: 'Ofertas académicas', permiso: 'ofertas.ver', pagina: p(() => import('../pages/academico/Ofertas')) },
  { ruta: '/ofertas/:id', titulo: 'Detalle de la oferta', permiso: 'ofertas.ver', pagina: p(() => import('../pages/academico/OfertaDetalle')) },

  // --- CU3 · Inscripciones
  { ruta: '/mis-preinscripciones', titulo: 'Mis preinscripciones', permiso: 'preinscripciones.propias', pagina: p(() => import('../pages/inscripcion/MisPreinscripciones')) },
  { ruta: '/mi-expediente', titulo: 'Mi expediente', permiso: 'expediente.propio', pagina: p(() => import('../pages/inscripcion/MiExpediente')) },
  { ruta: '/mis-estudios', titulo: 'Mis estudios y notas', permiso: 'estudios.propios', pagina: p(() => import('../pages/inscripcion/MisEstudios')) },
  { ruta: '/preinscripciones', titulo: 'Preinscripciones', permiso: 'preinscripciones.ver', pagina: p(() => import('../pages/inscripcion/Preinscripciones')) },
  { ruta: '/matriculas', titulo: 'Matrículas', permiso: 'matriculas.ver', pagina: p(() => import('../pages/inscripcion/Matriculas')) },
  { ruta: '/matriculas/:id', titulo: 'Detalle de la matrícula', permiso: 'matriculas.ver', pagina: p(() => import('../pages/inscripcion/MatriculaDetalle')) },

  // --- CU4 · Finanzas
  { ruta: '/aranceles', titulo: 'Aranceles', permiso: 'aranceles.ver', pagina: p(() => import('../pages/finanzas/Aranceles')) },
  { ruta: '/becas', titulo: 'Becas', permiso: 'becas.ver', pagina: p(() => import('../pages/finanzas/Becas')) },
  { ruta: '/metodos-pago', titulo: 'Métodos de pago', permiso: 'metodos_pago.ver', pagina: p(() => import('../pages/finanzas/MetodosPago')) },
  { ruta: '/pagos', titulo: 'Pagos y conciliación', permiso: 'pagos.ver', pagina: p(() => import('../pages/finanzas/Pagos')) },
  { ruta: '/mis-pagos', titulo: 'Mis pagos', permiso: 'pagos.propios', pagina: p(() => import('../pages/finanzas/MisPagos')) },

  // --- CU5 · Académico
  { ruta: '/docencia', titulo: 'Materias y calificaciones', permiso: 'docencia.ver', pagina: p(() => import('../pages/evaluacion/Docencia')) },
  { ruta: '/docencia/:id', titulo: 'Planilla de la materia', permiso: 'docencia.ver', pagina: p(() => import('../pages/evaluacion/Planilla')) },
  { ruta: '/correcciones', titulo: 'Correcciones de nota', permiso: 'correcciones.ver', pagina: p(() => import('../pages/evaluacion/Correcciones')) },

  // --- CU6 · Certificación
  { ruta: '/certificacion', titulo: 'Requisitos y certificados', permiso: 'certificacion.ver', pagina: p(() => import('../pages/certificacion/Certificacion')) },
  { ruta: '/certificacion/:id', titulo: 'Validación de requisitos', permiso: 'certificacion.ver', pagina: p(() => import('../pages/certificacion/CertificacionDetalle')) },
  { ruta: '/mis-certificados', titulo: 'Mis certificados', permiso: 'certificados.propios', pagina: p(() => import('../pages/certificacion/MisCertificados')) },

  // --- CU7 · Repositorio
  { ruta: '/documentos', titulo: 'Repositorio documental', permiso: 'documentos.ver', pagina: p(() => import('../pages/documental/Documentos')) },

  // --- CU8 · Reportes
  { ruta: '/reportes', titulo: 'Reportes', permiso: 'reportes.administrativos|reportes.academicos|reportes.financieros|reportes.docente', pagina: p(() => import('../pages/reportes/Reportes')) },
]
