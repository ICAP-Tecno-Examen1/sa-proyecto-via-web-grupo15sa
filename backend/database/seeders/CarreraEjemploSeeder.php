<?php

namespace Database\Seeders;

use App\Models\MallaCurricular;
use App\Models\Modulo;
use App\Models\OfertaAcademica;
use App\Models\Programa;
use App\Services\MallaService;
use App\Services\OfertaService;
use Illuminate\Database\Seeder;

/**
 * Ejemplo de carrera técnica media con módulos de 1 o 2 materias:
 * Auxiliar Contable -> malla 2026-1 -> oferta planificada.
 * No está en DatabaseSeeder: se ejecuta a propósito con
 *   php artisan db:seed --class=CarreraEjemploSeeder --force
 */
class CarreraEjemploSeeder extends Seeder
{
    public function run(): void
    {
        $programa = Programa::firstOrCreate(['codigo' => 'AUX-CONT'], [
            'nombre' => 'Auxiliar Contable',
            'tipo' => Programa::TECNICO_MEDIO,
            'estado' => Programa::ACTIVO,
            'descripcion' => 'Carrera técnica media: registro contable, libros, estados financieros y herramientas de oficina contable.',
        ]);

        // Materias (cada una se califica por separado)
        $materias = [
            'CONT-01' => ['Contabilidad básica', 60],
            'CONT-02' => ['Registro de operaciones contables', 60],
            'CONT-03' => ['Libros contables', 40],
            'CONT-04' => ['Estados financieros', 80],
            'CONT-05' => ['Excel aplicado a la contabilidad', 40],
        ];

        $ids = [];
        foreach ($materias as $codigo => [$nombre, $horas]) {
            $ids[$codigo] = Modulo::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'horas' => $horas])->id_modulo;
        }

        // Malla con módulos de la carrera: 1 o 2 materias cada uno
        $malla = MallaCurricular::where('id_programa', $programa->id_programa)->where('version', '2026-1')->first();

        if (! $malla) {
            $malla = app(MallaService::class)->crear([
                'id_programa' => $programa->id_programa,
                'version' => '2026-1',
                'estado' => MallaCurricular::ACTIVO,
                'fecha_vigencia' => now()->startOfYear()->toDateString(),
                'bloques' => [
                    ['nombre' => 'Fundamentos contables', 'orden' => 1, 'modulos' => [
                        ['id_modulo' => $ids['CONT-01']],
                    ]],
                    ['nombre' => 'Registro contable', 'orden' => 2, 'modulos' => [
                        ['id_modulo' => $ids['CONT-02']],
                        ['id_modulo' => $ids['CONT-03']],
                    ]],
                    ['nombre' => 'Estados financieros', 'orden' => 3, 'modulos' => [
                        ['id_modulo' => $ids['CONT-04']],
                    ]],
                    ['nombre' => 'Herramientas', 'orden' => 4, 'modulos' => [
                        ['id_modulo' => $ids['CONT-05']],
                    ]],
                ],
            ]);
        }

        // Oferta planificada (sin costos ni docentes: se completan desde la interfaz)
        $codigoOferta = 'AUX-CONT-'.now()->year.'-A';

        if (! OfertaAcademica::where('codigo', $codigoOferta)->exists()) {
            app(OfertaService::class)->crear([
                'id_malla' => $malla->id_malla,
                'codigo' => $codigoOferta,
                'modalidad' => 'PRESENCIAL',
                'fecha_inicio' => now()->addMonths(2)->startOfMonth()->toDateString(),
                'fecha_fin' => now()->addMonths(8)->endOfMonth()->toDateString(),
                'cupo_maximo' => 25,
                'cupo_minimo' => 5,
                'gestion' => now()->year,
            ]);
        }
    }
}
