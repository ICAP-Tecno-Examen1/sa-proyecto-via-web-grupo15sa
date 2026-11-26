<?php

use App\Http\Controllers\Api\Academico\AsignacionDocenteController;
use App\Http\Controllers\Api\Academico\MallaController;
use App\Http\Controllers\Api\Academico\ModuloController;
use App\Http\Controllers\Api\Academico\OfertaController;
use App\Http\Controllers\Api\Academico\ProgramaController;
use App\Http\Controllers\Api\Acceso\AuthController;
use App\Http\Controllers\Api\Acceso\PerfilController;
use App\Http\Controllers\Api\Acceso\RolController;
use App\Http\Controllers\Api\Acceso\UsuarioController;
use App\Http\Controllers\Api\Certificacion\CertificacionController;
use App\Http\Controllers\Api\Documental\DocumentoController;
use App\Http\Controllers\Api\Evaluacion\CorreccionController;
use App\Http\Controllers\Api\Evaluacion\DocenciaController;
use App\Http\Controllers\Api\Finanzas\ArancelController;
use App\Http\Controllers\Api\Finanzas\BecaController;
use App\Http\Controllers\Api\Finanzas\MetodoPagoController;
use App\Http\Controllers\Api\Finanzas\PagoController;
use App\Http\Controllers\Api\Finanzas\StripeController;
use App\Http\Controllers\Api\Inscripcion\ExpedienteController;
use App\Http\Controllers\Api\Inscripcion\MatriculaController;
use App\Http\Controllers\Api\Inscripcion\PreinscripcionController;
use App\Http\Controllers\Api\Publico\CatalogoController;
use App\Http\Controllers\Api\Reportes\ReporteController;
use App\Http\Controllers\Api\Sistema\BitacoraController;
use App\Http\Controllers\Api\Sistema\BusquedaController;
use App\Http\Controllers\Api\Sistema\PanelController;
use App\Http\Controllers\Api\Sistema\VisitaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API RESTful del ICAP (capa 2 · lógica de negocio)
|--------------------------------------------------------------------------
| Todas las rutas llevan el prefijo /api y responden JSON.
|
| MATRIZ DE ACCESO: cada ruta protegida declara el permiso que exige con el
| middleware "permiso:<accion>". Los permisos de cada rol están en la tabla
| rol_permiso y se administran desde el sistema (Roles y permisos).
*/

// ======================================================================
// RUTAS PÚBLICAS (página web, sin iniciar sesión)
// Cada límite de peticiones lleva su propio nombre (tercer parámetro de
// throttle) para que tenga un contador independiente: sin él, las visitas
// y la búsqueda consumirían el cupo de intentos de inicio de sesión.
// ======================================================================
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1,login');
    Route::post('registro', [AuthController::class, 'registro'])->middleware('throttle:10,1,registro');
});

Route::prefix('publico')->group(function () {
    Route::get('catalogo', [CatalogoController::class, 'index']);
    Route::get('catalogo/{oferta}', [CatalogoController::class, 'show']);
    Route::get('certificados/{codigo}', [CertificacionController::class, 'verificar'])->middleware('throttle:30,1,verificar');
});

Route::get('busqueda', BusquedaController::class)->middleware('throttle:90,1,busqueda');
Route::post('visitas', [VisitaController::class, 'registrar'])->middleware('throttle:180,1,visitas');

// Webhook de Stripe (sin sesión): Stripe avisa aquí cuando se completa un pago con tarjeta.
// Se autentica con la firma Stripe-Signature. Regístrelo en el panel de Stripe (ver docs).
Route::post('pagos/stripe/webhook', [StripeController::class, 'webhook'])->middleware('throttle:120,1,stripe');

// ======================================================================
// RUTAS PROTEGIDAS (token Sanctum + usuario activo)
// ======================================================================
Route::middleware(['auth:sanctum', 'usuario.activo'])->group(function () {

    // ---- Sesión, perfil y panel (cualquier usuario autenticado)
    Route::get('auth/yo', [AuthController::class, 'yo']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('perfil', [PerfilController::class, 'actualizar']);
    Route::put('perfil/password', [PerfilController::class, 'cambiarPassword']);
    Route::get('panel', [PanelController::class, 'index']);

    // ------------------------------------------------------------------
    // CU1 · Usuarios, roles, permisos y bitácora
    // ------------------------------------------------------------------
    Route::get('usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios.ver');
    Route::get('usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware('permiso:usuarios.ver');
    Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('permiso:usuarios.gestionar');
    Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware('permiso:usuarios.gestionar');
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->middleware('permiso:usuarios.gestionar');

    Route::get('estudiantes', [UsuarioController::class, 'estudiantes'])->middleware('permiso:estudiantes.gestionar');
    Route::post('estudiantes', [UsuarioController::class, 'storeEstudiante'])->middleware('permiso:estudiantes.gestionar');
    Route::put('estudiantes/{usuario}', [UsuarioController::class, 'updateEstudiante'])->middleware('permiso:estudiantes.gestionar');

    // Listas para combos (docentes / estudiantes)
    Route::get('personas/{rol}', [UsuarioController::class, 'porRol'])
        ->middleware('permiso:docentes.asignar|ofertas.gestionar|preinscripciones.revisar|documentos.registrar|usuarios.ver');

    Route::get('roles', [RolController::class, 'index'])->middleware('permiso:roles.ver|usuarios.ver');
    Route::get('permisos', [RolController::class, 'permisos'])->middleware('permiso:roles.ver');
    Route::post('roles', [RolController::class, 'store'])->middleware('permiso:roles.gestionar');
    Route::put('roles/{rol}', [RolController::class, 'update'])->middleware('permiso:roles.gestionar');
    Route::delete('roles/{rol}', [RolController::class, 'destroy'])->middleware('permiso:roles.gestionar');

    Route::get('bitacora', [BitacoraController::class, 'index'])->middleware('permiso:bitacora.ver');
    Route::get('estadisticas/accesos', [PanelController::class, 'accesos'])->middleware('permiso:estadisticas.accesos');

    // ------------------------------------------------------------------
    // CU2 · Programas, módulos, mallas, ofertas y docentes
    // ------------------------------------------------------------------
    Route::middleware('permiso:programas.ver')->group(function () {
        Route::get('programas', [ProgramaController::class, 'index']);
        Route::get('modulos', [ModuloController::class, 'index']);
        Route::get('mallas', [MallaController::class, 'index']);
        Route::get('mallas/{malla}', [MallaController::class, 'show']);
    });

    Route::middleware('permiso:programas.gestionar')->group(function () {
        Route::post('programas', [ProgramaController::class, 'store']);
        Route::put('programas/{programa}', [ProgramaController::class, 'update']);
        Route::delete('programas/{programa}', [ProgramaController::class, 'destroy']);
        Route::post('modulos', [ModuloController::class, 'store']);
        Route::put('modulos/{modulo}', [ModuloController::class, 'update']);
        Route::delete('modulos/{modulo}', [ModuloController::class, 'destroy']);
        Route::post('mallas', [MallaController::class, 'store']);
        Route::put('mallas/{malla}', [MallaController::class, 'update']);
        Route::post('mallas/{malla}/versionar', [MallaController::class, 'versionar']);
    });

    Route::get('ofertas', [OfertaController::class, 'index'])->middleware('permiso:ofertas.ver');
    Route::get('ofertas/{oferta}', [OfertaController::class, 'show'])->middleware('permiso:ofertas.ver');
    Route::middleware('permiso:ofertas.gestionar')->group(function () {
        Route::post('ofertas', [OfertaController::class, 'store']);
        Route::put('ofertas/{oferta}', [OfertaController::class, 'update']);
        Route::patch('ofertas/{oferta}/estado', [OfertaController::class, 'cambiarEstado']);
        Route::put('ofertas-modulo/{ofertaModulo}', [OfertaController::class, 'actualizarModulo']);
    });

    Route::middleware('permiso:docentes.asignar')->group(function () {
        Route::post('asignaciones-docente', [AsignacionDocenteController::class, 'store']);
        Route::put('asignaciones-docente/{asignacion}', [AsignacionDocenteController::class, 'update']);
        Route::delete('asignaciones-docente/{asignacion}', [AsignacionDocenteController::class, 'destroy']);
    });

    // ------------------------------------------------------------------
    // CU3 · Preinscripción, expediente único, matrícula y seguimiento
    // ------------------------------------------------------------------
    // Estudiante
    Route::get('mis-preinscripciones', [PreinscripcionController::class, 'mias'])->middleware('permiso:preinscripciones.propias');
    Route::post('preinscripciones', [PreinscripcionController::class, 'store'])->middleware('permiso:preinscripciones.propias|preinscripciones.revisar');
    Route::post('preinscripciones/{preinscripcion}/reenviar', [PreinscripcionController::class, 'reenviar'])->middleware('permiso:preinscripciones.propias');

    Route::middleware('permiso:expediente.propio')->group(function () {
        Route::get('mi-expediente', [ExpedienteController::class, 'propio']);
        Route::post('mi-expediente', [ExpedienteController::class, 'subirPropio']);
        Route::delete('mi-expediente/{documento}', [ExpedienteController::class, 'eliminarPropio']);
    });

    Route::get('mis-estudios', [MatriculaController::class, 'mias'])->middleware('permiso:estudios.propios');
    Route::get('mis-estudios/{matricula}', [MatriculaController::class, 'mia'])->middleware('permiso:estudios.propios');

    // Secretaría y coordinación
    Route::get('preinscripciones', [PreinscripcionController::class, 'index'])->middleware('permiso:preinscripciones.ver');
    Route::get('preinscripciones/{preinscripcion}', [PreinscripcionController::class, 'show'])->middleware('permiso:preinscripciones.ver');
    Route::post('preinscripciones/{preinscripcion}/revisar', [PreinscripcionController::class, 'revisar'])->middleware('permiso:preinscripciones.revisar');

    Route::get('expedientes/{usuario}', [ExpedienteController::class, 'show'])->middleware('permiso:expedientes.gestionar|preinscripciones.ver');
    Route::post('expedientes/{usuario}', [ExpedienteController::class, 'subir'])->middleware('permiso:expedientes.gestionar');

    Route::get('matriculas', [MatriculaController::class, 'index'])->middleware('permiso:matriculas.ver');
    Route::get('matriculas/{matricula}', [MatriculaController::class, 'show'])->middleware('permiso:matriculas.ver');
    Route::post('matriculas/{matricula}/anular', [MatriculaController::class, 'anular'])->middleware('permiso:matriculas.gestionar');
    Route::middleware('permiso:inscripciones.gestionar')->group(function () {
        Route::post('matriculas/{matricula}/inscripciones', [MatriculaController::class, 'inscribir']);
        Route::delete('inscripciones/{inscripcion}', [MatriculaController::class, 'retirarInscripcion']);
        Route::post('matriculas/{matricula}/finalizar', [MatriculaController::class, 'finalizar']);
    });

    // ------------------------------------------------------------------
    // CU4 · Aranceles, becas, métodos de pago, pagos y conciliación
    // ------------------------------------------------------------------
    Route::get('aranceles', [ArancelController::class, 'index'])->middleware('permiso:aranceles.ver');
    Route::middleware('permiso:aranceles.gestionar')->group(function () {
        Route::post('aranceles', [ArancelController::class, 'store']);
        Route::put('aranceles/{arancel}', [ArancelController::class, 'update']);
        Route::delete('aranceles/{arancel}', [ArancelController::class, 'destroy']);
    });

    Route::get('becas', [BecaController::class, 'index'])->middleware('permiso:becas.ver');
    // Solicitudes de beca: el estudiante pide con respaldo; el Coordinador Financiero revisa
    Route::get('becas/disponibles', [BecaController::class, 'disponibles'])->middleware('permiso:pagos.propios|becas.ver');
    Route::post('matriculas/{matricula}/solicitar-beca', [BecaController::class, 'solicitar'])->middleware('permiso:pagos.propios');
    Route::get('becas/solicitudes', [BecaController::class, 'solicitudes'])->middleware('permiso:becas.ver|becas.asignar');
    Route::post('becas/solicitudes/{becaEstudiante}/aprobar', [BecaController::class, 'aprobar'])->middleware('permiso:becas.asignar');
    Route::post('becas/solicitudes/{becaEstudiante}/rechazar', [BecaController::class, 'rechazar'])->middleware('permiso:becas.asignar');
    // Respaldo: el controlador verifica que sea del estudiante o que el rol revise becas
    Route::get('becas/solicitudes/{becaEstudiante}/respaldo', [BecaController::class, 'respaldo'])->whereNumber('becaEstudiante');
    Route::get('becas-asignadas', [BecaController::class, 'asignaciones'])->middleware('permiso:becas.ver');
    Route::middleware('permiso:becas.gestionar')->group(function () {
        Route::post('becas', [BecaController::class, 'store']);
        Route::patch('becas/{beca}/estado', [BecaController::class, 'cambiarEstado']);
        Route::put('becas/{beca}', [BecaController::class, 'update']);
        Route::delete('becas/{beca}', [BecaController::class, 'destroy']);
    });
    Route::post('matriculas/{matricula}/becas', [BecaController::class, 'asignar'])->middleware('permiso:becas.asignar');
    Route::delete('becas-asignadas/{becaEstudiante}', [BecaController::class, 'revocar'])->middleware('permiso:becas.asignar');

    Route::get('metodos-pago/disponibles', [MetodoPagoController::class, 'disponibles'])->middleware('permiso:pagos.propios|pagos.registrar');
    Route::get('metodos-pago', [MetodoPagoController::class, 'index'])->middleware('permiso:metodos_pago.ver');
    Route::middleware('permiso:metodos_pago.gestionar')->group(function () {
        Route::post('metodos-pago', [MetodoPagoController::class, 'store']);
        Route::put('metodos-pago/{metodoPago}', [MetodoPagoController::class, 'update']);
        Route::patch('metodos-pago/{metodoPago}/estado', [MetodoPagoController::class, 'cambiarEstado']);
        Route::delete('metodos-pago/{metodoPago}', [MetodoPagoController::class, 'destroy']);
    });

    Route::get('mis-pagos', [PagoController::class, 'mios'])->middleware('permiso:pagos.propios');
    Route::middleware('permiso:pagos.propios|pagos.registrar')->group(function () {
        Route::post('pagos', [PagoController::class, 'store']);
        Route::post('pagos/en-linea', [PagoController::class, 'iniciarEnLinea']);
    });
    // Estado y comprobante: el controlador verifica que el pago sea propio o que el rol pueda verlo.
    Route::get('pagos/{pago}/estado', [PagoController::class, 'estado'])->whereNumber('pago');
    Route::get('pagos/{pago}/comprobante', [PagoController::class, 'comprobante'])->whereNumber('pago');

    Route::get('pagos', [PagoController::class, 'index'])->middleware('permiso:pagos.ver');
    Route::get('pagos/pendientes', [PagoController::class, 'pendientes'])->middleware('permiso:pagos.conciliar');
    // Cuotas por cobrar: qué estudiante debe qué cuota y cuándo vence (Secretaría y Coordinador Financiero)
    Route::get('cuentas-por-cobrar', [PagoController::class, 'porCobrar'])->middleware('permiso:pagos.ver');
    Route::get('pagos/{pago}', [PagoController::class, 'show'])->middleware('permiso:pagos.ver')->whereNumber('pago');
    Route::get('matriculas/{matricula}/cuotas', [PagoController::class, 'cuotasDeMatricula'])->middleware('permiso:pagos.registrar|pagos.ver');
    Route::post('pagos/{pago}/conciliar', [PagoController::class, 'conciliar'])->middleware('permiso:pagos.conciliar')->whereNumber('pago');

    // ------------------------------------------------------------------
    // CU5 · Asistencia, evaluaciones, notas y correcciones
    // ------------------------------------------------------------------
    Route::middleware('permiso:docencia.ver')->prefix('docencia')->group(function () {
        Route::get('modulos', [DocenciaController::class, 'modulos']);
        Route::get('modulos/{ofertaModulo}', [DocenciaController::class, 'planilla']);
        Route::get('modulos/{ofertaModulo}/asistencia', [DocenciaController::class, 'asistencia']);
    });

    Route::middleware('permiso:docencia.registrar')->prefix('docencia')->group(function () {
        Route::put('modulos/{ofertaModulo}/asistencia', [DocenciaController::class, 'guardarAsistencia']);
        Route::delete('modulos/{ofertaModulo}/asistencia', [DocenciaController::class, 'eliminarSesion']);
        Route::post('modulos/{ofertaModulo}/evaluaciones', [DocenciaController::class, 'crearEvaluacion']);
        Route::put('evaluaciones/{evaluacion}', [DocenciaController::class, 'actualizarEvaluacion']);
        Route::delete('evaluaciones/{evaluacion}', [DocenciaController::class, 'eliminarEvaluacion']);
        Route::put('evaluaciones/{evaluacion}/notas', [DocenciaController::class, 'guardarNotas']);
        Route::post('modulos/{ofertaModulo}/cerrar', [DocenciaController::class, 'cerrar']);
    });

    Route::get('correcciones', [CorreccionController::class, 'index'])->middleware('permiso:correcciones.ver');
    Route::post('correcciones', [CorreccionController::class, 'store'])->middleware('permiso:correcciones.solicitar');
    Route::post('correcciones/{correccion}/resolver', [CorreccionController::class, 'resolver'])->middleware('permiso:correcciones.resolver');

    // ------------------------------------------------------------------
    // CU6 · Requisitos y certificados digitales
    // ------------------------------------------------------------------
    Route::middleware('permiso:certificacion.ver')->group(function () {
        Route::get('certificacion', [CertificacionController::class, 'index']);
        Route::get('certificacion/{matricula}', [CertificacionController::class, 'show']);
        Route::get('certificados', [CertificacionController::class, 'certificados']);
    });
    // El tipo de requisito que cada rol puede validar se comprueba en el controlador.
    Route::post('certificacion/{matricula}/validar', [CertificacionController::class, 'validar'])
        ->middleware('permiso:requisitos.academico|requisitos.financiero|requisitos.documental');
    Route::post('certificacion/{matricula}/emitir', [CertificacionController::class, 'emitir'])->middleware('permiso:certificados.autorizar');
    Route::post('certificados/{certificado}/anular', [CertificacionController::class, 'anular'])->middleware('permiso:certificados.autorizar');

    Route::get('mis-certificados', [CertificacionController::class, 'mios'])->middleware('permiso:certificados.propios');
    Route::get('certificados/{certificado}/pdf', [CertificacionController::class, 'pdf'])->middleware('permiso:certificados.propios|certificacion.ver');

    // ------------------------------------------------------------------
    // CU7 · Repositorio documental
    // ------------------------------------------------------------------
    Route::get('documentos', [DocumentoController::class, 'index'])->middleware('permiso:documentos.ver');
    Route::post('documentos', [DocumentoController::class, 'store'])->middleware('permiso:documentos.registrar');
    Route::post('documentos/{documento}', [DocumentoController::class, 'update'])->middleware('permiso:documentos.registrar|documentos.gestionar');
    Route::patch('documentos/{documento}/archivar', [DocumentoController::class, 'archivar'])->middleware('permiso:documentos.registrar|documentos.gestionar');
    // Descarga: abierta a cualquier sesión; el servicio valida el acceso al documento concreto.
    Route::get('documentos/{documento}/descargar', [DocumentoController::class, 'descargar']);

    // ------------------------------------------------------------------
    // CU8 · Reportes
    // ------------------------------------------------------------------
    Route::middleware('permiso:reportes.administrativos|reportes.academicos|reportes.financieros|reportes.docente')->group(function () {
        Route::get('reportes', [ReporteController::class, 'index']);
        Route::get('reportes/{clave}', [ReporteController::class, 'show']);
    });
});
