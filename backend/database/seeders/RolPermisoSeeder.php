<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

/**
 * Roles, permisos y MATRIZ DE ACCESO inicial (requisitos 2 y 4).
 *
 * Es idempotente: puede ejecutarse varias veces. Agrega lo que falte y no
 * quita permisos que el Director haya concedido después desde el sistema.
 */
class RolPermisoSeeder extends Seeder
{
    /** accion => [módulo, descripción] */
    public const PERMISOS = [
        // CU1
        'usuarios.ver' => ['Usuarios', 'Consultar usuarios del sistema'],
        'usuarios.gestionar' => ['Usuarios', 'Registrar y editar usuarios, activar o desactivar y asignar roles'],
        'estudiantes.gestionar' => ['Usuarios', 'Registrar estudiantes y actualizar sus datos'],
        'roles.ver' => ['Usuarios', 'Consultar roles y la matriz de permisos'],
        'roles.gestionar' => ['Usuarios', 'Crear roles y asignarles permisos'],
        'bitacora.ver' => ['Auditoría', 'Consultar la bitácora de auditoría'],
        'estadisticas.accesos' => ['Auditoría', 'Ver estadísticas de acceso al sitio'],
        // CU2
        'programas.ver' => ['Oferta académica', 'Consultar programas, módulos y mallas curriculares'],
        'programas.gestionar' => ['Oferta académica', 'Gestionar programas, módulos y versiones de malla'],
        'ofertas.ver' => ['Oferta académica', 'Consultar ofertas académicas'],
        'ofertas.gestionar' => ['Oferta académica', 'Crear ofertas, definir fechas, modalidad y cupos'],
        'docentes.asignar' => ['Oferta académica', 'Asignar docentes y registrar su contratación y honorarios'],
        // CU3
        'preinscripciones.propias' => ['Inscripciones', 'Preinscribirse y consultar sus solicitudes'],
        'expediente.propio' => ['Inscripciones', 'Gestionar su propio expediente de documentos'],
        'estudios.propios' => ['Inscripciones', 'Consultar sus programas, notas y asistencia'],
        'preinscripciones.ver' => ['Inscripciones', 'Consultar preinscripciones'],
        'preinscripciones.revisar' => ['Inscripciones', 'Aprobar, observar o rechazar preinscripciones y generar matrículas'],
        'expedientes.gestionar' => ['Inscripciones', 'Gestionar el expediente de los estudiantes'],
        'matriculas.ver' => ['Inscripciones', 'Consultar matrículas y el seguimiento del estudiante'],
        'matriculas.gestionar' => ['Inscripciones', 'Anular matrículas'],
        'inscripciones.gestionar' => ['Inscripciones', 'Inscribir en módulos y finalizar el programa del estudiante'],
        // CU4
        'aranceles.ver' => ['Finanzas', 'Consultar aranceles y planes de pago'],
        'aranceles.gestionar' => ['Finanzas', 'Configurar costos y modalidades de pago de las ofertas'],
        'becas.ver' => ['Finanzas', 'Consultar becas y descuentos'],
        'becas.gestionar' => ['Finanzas', 'Gestionar el catálogo de becas'],
        'becas.asignar' => ['Finanzas', 'Asignar o revocar becas a estudiantes'],
        'metodos_pago.ver' => ['Finanzas', 'Consultar métodos de pago'],
        'metodos_pago.gestionar' => ['Finanzas', 'Gestionar métodos de pago y la pasarela PagoFácil'],
        'pagos.propios' => ['Finanzas', 'Consultar sus deudas y pagar sus cuotas'],
        'pagos.registrar' => ['Finanzas', 'Registrar pagos en efectivo o comprobantes recibidos'],
        'pagos.ver' => ['Finanzas', 'Consultar todos los pagos'],
        'pagos.conciliar' => ['Finanzas', 'Confirmar, observar o rechazar pagos'],
        // CU5
        'docencia.ver' => ['Académico', 'Consultar módulos, listas de estudiantes y planillas'],
        'docencia.registrar' => ['Académico', 'Registrar asistencia, evaluaciones y notas de sus módulos'],
        'academico.supervisar' => ['Académico', 'Ver los módulos y planillas de todos los docentes'],
        'correcciones.ver' => ['Académico', 'Consultar solicitudes de corrección de notas'],
        'correcciones.solicitar' => ['Académico', 'Solicitar la corrección de una nota'],
        'correcciones.resolver' => ['Académico', 'Aprobar o rechazar correcciones de notas'],
        // CU6
        'certificacion.ver' => ['Certificación', 'Consultar requisitos y certificados emitidos'],
        'requisitos.academico' => ['Certificación', 'Validar requisitos académicos'],
        'requisitos.financiero' => ['Certificación', 'Validar requisitos financieros'],
        'requisitos.documental' => ['Certificación', 'Validar requisitos documentales'],
        'certificados.autorizar' => ['Certificación', 'Autorizar la emisión y anular certificados'],
        'certificados.propios' => ['Certificación', 'Descargar sus certificados'],
        // CU7
        'documentos.ver' => ['Repositorio', 'Ingresar al repositorio documental'],
        'documentos.registrar' => ['Repositorio', 'Registrar documentos en el repositorio'],
        'documentos.gestionar' => ['Repositorio', 'Editar y archivar documentos del repositorio'],
        'documentos.personal' => ['Repositorio', 'Ver todos los documentos personales de estudiantes'],
        'documentos.academico' => ['Repositorio', 'Ver toda la documentación académica'],
        'documentos.financiero' => ['Repositorio', 'Ver toda la documentación financiera'],
        'documentos.institucional' => ['Repositorio', 'Ver toda la documentación institucional'],
        // CU8
        'reportes.administrativos' => ['Reportes', 'Reportes de estudiantes, preinscripciones, matrículas y certificados'],
        'reportes.academicos' => ['Reportes', 'Reportes de asistencia, rendimiento y docentes'],
        'reportes.financieros' => ['Reportes', 'Reportes de ingresos, deudas, mensualidades y becas'],
        'reportes.docente' => ['Reportes', 'Reportes de asistencia y calificaciones de sus módulos'],
    ];

    /** rol => [descripción, permisos] */
    public const MATRIZ = [
        Rol::DIRECTOR => ['Máxima autoridad del ICAP: gestiona accesos, supervisa y autoriza certificados', [
            'usuarios.ver', 'usuarios.gestionar', 'roles.ver', 'roles.gestionar', 'bitacora.ver', 'estadisticas.accesos',
            'programas.ver', 'ofertas.ver', 'preinscripciones.ver', 'matriculas.ver',
            'aranceles.ver', 'becas.ver', 'metodos_pago.ver', 'pagos.ver',
            'docencia.ver', 'academico.supervisar', 'correcciones.ver',
            'certificacion.ver', 'certificados.autorizar',
            'documentos.ver', 'documentos.personal', 'documentos.academico', 'documentos.financiero', 'documentos.institucional',
            'reportes.administrativos', 'reportes.academicos', 'reportes.financieros',
        ]],
        Rol::SECRETARIA => ['Atiende preinscripciones, matrículas, expedientes y el repositorio documental', [
            'estudiantes.gestionar', 'programas.ver', 'ofertas.ver',
            'preinscripciones.ver', 'preinscripciones.revisar', 'expedientes.gestionar', 'matriculas.ver', 'matriculas.gestionar',
            'aranceles.ver', 'pagos.registrar', 'pagos.ver',
            'certificacion.ver', 'requisitos.documental',
            'documentos.ver', 'documentos.registrar', 'documentos.gestionar',
            'documentos.personal', 'documentos.academico', 'documentos.financiero', 'documentos.institucional',
            'reportes.administrativos',
        ]],
        Rol::COORDINADOR_ACADEMICO => ['Gestiona programas, mallas, ofertas, docentes y el seguimiento académico', [
            'programas.ver', 'programas.gestionar', 'ofertas.ver', 'ofertas.gestionar', 'docentes.asignar',
            'preinscripciones.ver', 'matriculas.ver', 'inscripciones.gestionar',
            'docencia.ver', 'academico.supervisar', 'correcciones.ver', 'correcciones.resolver',
            'certificacion.ver', 'requisitos.academico',
            'documentos.ver', 'documentos.registrar', 'documentos.academico',
            'reportes.academicos',
        ]],
        Rol::COORDINADOR_FINANCIERO => ['Configura aranceles y becas, concilia pagos y controla deudas', [
            'ofertas.ver', 'matriculas.ver',
            'aranceles.ver', 'aranceles.gestionar', 'becas.ver', 'becas.gestionar', 'becas.asignar',
            'metodos_pago.ver', 'metodos_pago.gestionar', 'pagos.ver', 'pagos.registrar', 'pagos.conciliar',
            'certificacion.ver', 'requisitos.financiero',
            'documentos.ver', 'documentos.registrar', 'documentos.financiero',
            'reportes.financieros',
        ]],
        Rol::DOCENTE => ['Registra asistencia, evaluaciones y notas de sus módulos', [
            'docencia.ver', 'docencia.registrar', 'correcciones.ver', 'correcciones.solicitar',
            'documentos.ver', 'documentos.registrar', 'reportes.docente',
        ]],
        Rol::ESTUDIANTE => ['Se preinscribe, paga sus cuotas y consulta sus resultados y certificados', [
            'preinscripciones.propias', 'expediente.propio', 'estudios.propios', 'pagos.propios', 'certificados.propios',
        ]],
    ];

    public function run(): void
    {
        $ids = [];

        foreach (self::PERMISOS as $accion => [$modulo, $descripcion]) {
            $ids[$accion] = Permiso::updateOrCreate(['accion' => $accion], ['modulo' => $modulo, 'descripcion' => $descripcion])->id_permiso;
        }

        foreach (self::MATRIZ as $nombre => [$descripcion, $permisos]) {
            $rol = Rol::firstOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
            $rol->permisos()->syncWithoutDetaching(array_map(fn ($accion) => $ids[$accion], $permisos));
        }
    }
}
