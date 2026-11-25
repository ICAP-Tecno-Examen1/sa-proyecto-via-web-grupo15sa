<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Permiso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menú de navegación (tabla menu_item). El cliente lo recibe ya filtrado por
 * los permisos del rol activo. Se reconstruye completo en cada ejecución.
 *
 * Formato: sección => [icono, [ [etiqueta, ruta, icono, permiso], ... ]]
 */
class MenuSeeder extends Seeder
{
    private const MENU = [
        'Inicio' => ['inicio', [
            ['Panel', '/panel', 'panel', null],
            ['Catálogo de programas', '/catalogo', 'catalogo', null],
        ]],
        'Mi espacio' => ['estudiante', [
            ['Mis preinscripciones', '/mis-preinscripciones', 'solicitud', 'preinscripciones.propias'],
            ['Mi expediente', '/mi-expediente', 'carpeta', 'expediente.propio'],
            ['Mis estudios y notas', '/mis-estudios', 'libro', 'estudios.propios'],
            ['Mis pagos', '/mis-pagos', 'pago', 'pagos.propios'],
            ['Mis certificados', '/mis-certificados', 'certificado', 'certificados.propios'],
        ]],
        'Administración' => ['ajustes', [
            ['Usuarios', '/usuarios', 'usuarios', 'usuarios.ver'],
            ['Estudiantes', '/estudiantes', 'estudiante', 'estudiantes.gestionar'],
            ['Roles y permisos', '/roles', 'escudo', 'roles.ver'],
            ['Bitácora', '/bitacora', 'bitacora', 'bitacora.ver'],
            ['Estadísticas de acceso', '/estadisticas-accesos', 'tendencia', 'estadisticas.accesos'],
        ]],
        'Oferta académica' => ['libro', [
            ['Programas', '/programas', 'libro', 'programas.ver'],
            ['Materias', '/modulos', 'modulo', 'programas.ver'],
            ['Mallas curriculares', '/mallas', 'malla', 'programas.ver'],
            ['Ofertas académicas', '/ofertas', 'calendario', 'ofertas.ver'],
        ]],
        'Inscripciones' => ['solicitud', [
            ['Preinscripciones', '/preinscripciones', 'solicitud', 'preinscripciones.ver'],
            ['Matrículas', '/matriculas', 'matricula', 'matriculas.ver'],
        ]],
        'Finanzas' => ['pago', [
            ['Aranceles', '/aranceles', 'arancel', 'aranceles.ver'],
            ['Becas', '/becas', 'beca', 'becas.ver'],
            ['Métodos de pago', '/metodos-pago', 'tarjeta', 'metodos_pago.ver'],
            ['Pagos y conciliación', '/pagos', 'pago', 'pagos.ver'],
        ]],
        'Académico' => ['docente', [
            ['Materias y calificaciones', '/docencia', 'docente', 'docencia.ver'],
            ['Correcciones de nota', '/correcciones', 'correccion', 'correcciones.ver'],
        ]],
        'Certificación' => ['certificado', [
            ['Requisitos y certificados', '/certificacion', 'certificado', 'certificacion.ver'],
        ]],
        'Repositorio' => ['carpeta', [
            ['Repositorio documental', '/documentos', 'carpeta', 'documentos.ver'],
        ]],
        'Reportes' => ['reporte', [
            ['Reportes', '/reportes', 'reporte', 'reportes.administrativos|reportes.academicos|reportes.financieros|reportes.docente'],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            MenuItem::query()->delete();
            $permisos = Permiso::pluck('id_permiso', 'accion');
            $ordenSeccion = 0;

            foreach (self::MENU as $seccion => [$icono, $opciones]) {
                $padre = MenuItem::create(['etiqueta' => $seccion, 'icono' => $icono, 'orden' => ++$ordenSeccion]);
                $orden = 0;

                foreach ($opciones as [$etiqueta, $ruta, $iconoOpcion, $permiso]) {
                    // Una opción visible para varios permisos se registra una vez por permiso
                    // (MenuService descarta las rutas repetidas).
                    foreach ($permiso ? explode('|', $permiso) : [null] as $accion) {
                        MenuItem::create([
                            'id_padre' => $padre->id_menu_item,
                            'id_permiso' => $accion ? $permisos[$accion] : null,
                            'etiqueta' => $etiqueta,
                            'ruta' => $ruta,
                            'icono' => $iconoOpcion,
                            'orden' => ++$orden,
                        ]);
                    }
                }
            }
        });
    }
}
