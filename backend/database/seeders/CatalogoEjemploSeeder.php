<?php

namespace Database\Seeders;

use App\Models\Arancel;
use App\Models\AsignacionDocente;
use App\Models\MallaCurricular;
use App\Models\Modulo;
use App\Models\OfertaAcademica;
use App\Models\Programa;
use App\Models\Usuario;
use App\Services\MallaService;
use App\Services\OfertaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de ejemplo: un programa por tipo de formación, ya publicado
 * (oferta ABIERTA, con costos y docentes) para ver cómo se muestra en la web.
 *
 *   php artisan db:seed --class=CatalogoEjemploSeeder
 *
 * - Carreras (Técnico auxiliar / medio / superior): malla por MÓDULOS de 1 o 2
 *   materias; un módulo = un mes (config icap.academico.meses_por_modulo).
 * - Curso, taller y programa técnico: lista de materias.
 *
 * Idempotente: todo se busca por código (prefijo EJ-), así que puede ejecutarse
 * varias veces sin duplicar. No está en DatabaseSeeder a propósito.
 */
class CatalogoEjemploSeeder extends Seeder
{
    private const VERSION = '2026-1';

    /**
     * codigo => datos del programa.
     *   materias   codigo => [nombre, horas]
     *   modulos    (solo carreras) nombre del módulo => [codigos de sus 1 o 2 materias]
     *   inicio     meses desde hoy en que empieza la oferta (día 1 de ese mes)
     *   duracion   días que dura (solo programas que no son carrera)
     *   planes     [concepto, tipo_cobro, monto, cuotas]
     */
    private const PROGRAMAS = [
        'EJ-TAMD' => [
            'nombre' => 'Técnico Auxiliar en Marketing Digital',
            'tipo' => Programa::TECNICO_AUXILIAR,
            'descripcion' => 'Formación técnica por módulos para gestionar redes sociales, crear contenido y medir campañas digitales para negocios y emprendimientos.',
            'modalidad' => 'SEMIPRESENCIAL',
            'inicio' => 1,
            'materias' => [
                'EJ-MKT-01' => ['Fundamentos de marketing', 40],
                'EJ-MKT-02' => ['Comportamiento del consumidor digital', 40],
                'EJ-MKT-03' => ['Gestión de redes sociales', 60],
                'EJ-MKT-04' => ['Diseño gráfico para redes', 60],
                'EJ-MKT-05' => ['Fotografía y video con el celular', 40],
                'EJ-MKT-06' => ['Publicidad en Facebook e Instagram', 60],
                'EJ-MKT-07' => ['Comercio electrónico', 40],
                'EJ-MKT-08' => ['Analítica y métricas digitales', 40],
            ],
            'modulos' => [
                'Bases del marketing' => ['EJ-MKT-01', 'EJ-MKT-02'],
                'Redes sociales' => ['EJ-MKT-03'],
                'Contenido visual' => ['EJ-MKT-04', 'EJ-MKT-05'],
                'Publicidad digital' => ['EJ-MKT-06'],
                'Ventas en línea' => ['EJ-MKT-07', 'EJ-MKT-08'],
            ],
            'planes' => [
                ['Pago al contado', Arancel::PAGO_UNICO, 2250, 1],
                ['Plan de 5 mensualidades', Arancel::MENSUALIDAD, 2500, 5],
            ],
        ],
        'EJ-TMCO' => [
            'nombre' => 'Técnico Medio en Contabilidad',
            'tipo' => Programa::TECNICO_MEDIO,
            'descripcion' => 'Carrera técnica para llevar la contabilidad de una empresa: registros, libros, costos, impuestos y estados financieros.',
            'modalidad' => 'PRESENCIAL',
            'inicio' => 1,
            'materias' => [
                'EJ-CON-01' => ['Contabilidad básica', 60],
                'EJ-CON-02' => ['Excel contable', 40],
                'EJ-CON-03' => ['Contabilidad intermedia', 80],
                'EJ-CON-04' => ['Contabilidad de costos', 60],
                'EJ-CON-05' => ['Estados financieros', 60],
                'EJ-CON-06' => ['Legislación tributaria', 60],
                'EJ-CON-07' => ['Sistemas contables computarizados', 60],
                'EJ-CON-08' => ['Auditoría básica', 40],
                'EJ-CON-09' => ['Ética profesional', 20],
            ],
            'modulos' => [
                'Fundamentos contables' => ['EJ-CON-01', 'EJ-CON-02'],
                'Contabilidad intermedia' => ['EJ-CON-03'],
                'Costos y estados financieros' => ['EJ-CON-04', 'EJ-CON-05'],
                'Impuestos' => ['EJ-CON-06'],
                'Contabilidad computarizada' => ['EJ-CON-07'],
                'Control y ética' => ['EJ-CON-08', 'EJ-CON-09'],
            ],
            'planes' => [
                ['Pago al contado', Arancel::PAGO_UNICO, 3300, 1],
                ['Plan de 6 mensualidades', Arancel::MENSUALIDAD, 3600, 6],
            ],
        ],
        'EJ-TSSI' => [
            'nombre' => 'Técnico Superior en Sistemas Informáticos',
            'tipo' => Programa::TECNICO_SUPERIOR,
            'descripcion' => 'Carrera técnica superior en programación, bases de datos, redes y desarrollo web, con proyecto final aplicado.',
            'modalidad' => 'VIRTUAL',
            'inicio' => 2,
            'materias' => [
                'EJ-SIS-01' => ['Lógica de programación', 80],
                'EJ-SIS-02' => ['Matemática para informática', 40],
                'EJ-SIS-03' => ['Programación orientada a objetos', 80],
                'EJ-SIS-04' => ['Bases de datos', 80],
                'EJ-SIS-05' => ['Desarrollo web', 80],
                'EJ-SIS-06' => ['Redes de computadoras', 60],
                'EJ-SIS-07' => ['Sistemas operativos', 40],
                'EJ-SIS-08' => ['Ingeniería de software', 60],
                'EJ-SIS-09' => ['Proyecto de grado', 80],
            ],
            'modulos' => [
                'Bases de la programación' => ['EJ-SIS-01', 'EJ-SIS-02'],
                'Programación avanzada' => ['EJ-SIS-03'],
                'Datos' => ['EJ-SIS-04'],
                'Web' => ['EJ-SIS-05'],
                'Infraestructura' => ['EJ-SIS-06', 'EJ-SIS-07'],
                'Desarrollo de software' => ['EJ-SIS-08'],
                'Titulación' => ['EJ-SIS-09'],
            ],
            'planes' => [
                ['Pago al contado', Arancel::PAGO_UNICO, 4900, 1],
                ['Plan de 7 mensualidades', Arancel::MENSUALIDAD, 5250, 7],
            ],
        ],
        'EJ-CEXA' => [
            'nombre' => 'Curso de Excel Avanzado',
            'tipo' => 'CURSO',
            'descripcion' => 'Fórmulas avanzadas, tablas dinámicas, gráficos y automatización con macros para el trabajo diario.',
            'modalidad' => 'VIRTUAL',
            'inicio' => 1,
            'duracion' => 30,
            'materias' => [
                'EJ-EXA-01' => ['Fórmulas y funciones avanzadas', 20],
                'EJ-EXA-02' => ['Tablas dinámicas y gráficos', 20],
                'EJ-EXA-03' => ['Macros y automatización', 20],
            ],
            'planes' => [['Pago único', Arancel::PAGO_UNICO, 450, 1]],
        ],
        'EJ-TBAL' => [
            'nombre' => 'Taller de Elaboración de Balances Contables',
            'tipo' => 'TALLER',
            'descripcion' => 'Taller práctico: se elabora paso a paso el balance general y el estado de resultados de un caso real.',
            'modalidad' => 'PRESENCIAL',
            'inicio' => 1,
            'duracion' => 7,
            'materias' => ['EJ-BAL-01' => ['Práctica de balances contables', 16]],
            'planes' => [['Pago único', Arancel::PAGO_UNICO, 250, 1]],
        ],
        'EJ-PRCO' => [
            'nombre' => 'Programa Técnico en Reparación de Computadoras',
            'tipo' => 'PROGRAMA_TECNICO',
            'descripcion' => 'Ensamblaje, mantenimiento preventivo y correctivo, instalación de sistemas y redes domésticas.',
            'modalidad' => 'PRESENCIAL',
            'inicio' => 1,
            'duracion' => 90,
            'materias' => [
                'EJ-REP-01' => ['Arquitectura y ensamblaje de computadoras', 40],
                'EJ-REP-02' => ['Mantenimiento preventivo y correctivo', 40],
                'EJ-REP-03' => ['Instalación de sistemas operativos', 30],
                'EJ-REP-04' => ['Redes domésticas y de oficina', 30],
            ],
            'planes' => [
                ['Pago al contado', Arancel::PAGO_UNICO, 1500, 1],
                ['Plan de 3 mensualidades', Arancel::MENSUALIDAD, 1650, 3],
            ],
        ],
    ];

    public function run(): void
    {
        $docentes = Usuario::whereIn('username', ['docente', 'docente2'])->orderBy('username')->pluck('id_usuario')->all();

        foreach (self::PROGRAMAS as $codigo => $datos) {
            DB::transaction(fn () => $this->sembrar($codigo, $datos, $docentes));
            $this->command?->info("✓ {$datos['nombre']}");
        }
    }

    private function sembrar(string $codigo, array $datos, array $docentes): void
    {
        $programa = Programa::firstOrCreate(['codigo' => $codigo], [
            'nombre' => $datos['nombre'],
            'tipo' => $datos['tipo'],
            'descripcion' => $datos['descripcion'],
            'estado' => Programa::ACTIVO,
        ]);

        // 1. Materias
        $ids = [];
        foreach ($datos['materias'] as $codigoMateria => [$nombre, $horas]) {
            $ids[$codigoMateria] = Modulo::firstOrCreate(['codigo' => $codigoMateria], ['nombre' => $nombre, 'horas' => $horas])->id_modulo;
        }

        // 2. Malla: por módulos (carreras) o como lista de materias
        $malla = MallaCurricular::where('id_programa', $programa->id_programa)->where('version', self::VERSION)->first()
            ?? app(MallaService::class)->crear([
                'id_programa' => $programa->id_programa,
                'version' => self::VERSION,
                'estado' => MallaCurricular::ACTIVO,
                'fecha_vigencia' => now()->startOfYear()->toDateString(),
                ...(isset($datos['modulos'])
                    ? ['bloques' => collect($datos['modulos'])->keys()->map(fn ($nombre, $i) => [
                        'nombre' => $nombre,
                        'orden' => $i + 1,
                        'modulos' => array_map(fn ($c) => ['id_modulo' => $ids[$c]], $datos['modulos'][$nombre]),
                    ])->all()]
                    : ['modulos' => collect(array_values($ids))->map(fn ($id, $i) => ['id_modulo' => $id, 'orden' => $i + 1, 'obligatorio' => true])->all()]),
            ]);

        // 3. Oferta (en una carrera, la API calcula el fin: un mes por módulo)
        $codigoOferta = "{$codigo}-".now()->year;
        $oferta = OfertaAcademica::where('codigo', $codigoOferta)->first();

        if (! $oferta) {
            $inicio = Carbon::now()->addMonthsNoOverflow($datos['inicio'])->startOfMonth();
            $oferta = app(OfertaService::class)->crear([
                'id_malla' => $malla->id_malla,
                'codigo' => $codigoOferta,
                'modalidad' => $datos['modalidad'],
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $inicio->copy()->addDays(($datos['duracion'] ?? 30) - 1)->toDateString(),
                'cupo_maximo' => isset($datos['modulos']) ? 30 : 25,
                'cupo_minimo' => 5,
                'gestion' => $inicio->year,
            ]);
        }

        // 4. Planes de pago (aranceles)
        foreach ($datos['planes'] as [$concepto, $tipoCobro, $monto, $cuotas]) {
            Arancel::firstOrCreate(['id_oferta' => $oferta->id_oferta, 'concepto' => $concepto], [
                'monto' => $monto,
                'tipo_cobro' => $tipoCobro,
                'numero_cuotas' => $cuotas,
                'estado' => Arancel::ACTIVO,
            ]);
        }

        // 5. Docentes: se reparten las cuentas de ejemplo entre las materias
        if ($docentes) {
            foreach ($oferta->ofertaModulos()->orderBy('id_oferta_modulo')->get() as $i => $ofertaModulo) {
                AsignacionDocente::firstOrCreate(['id_oferta_modulo' => $ofertaModulo->id_oferta_modulo], [
                    'id_usuario_docente' => $docentes[$i % count($docentes)],
                    'estado' => AsignacionDocente::ACTIVO,
                    'fecha_contrato' => now()->toDateString(),
                    'fecha_inicio' => $ofertaModulo->fecha_inicio,
                    'fecha_fin' => $ofertaModulo->fecha_fin,
                    'monto_honorario' => 1500,
                    'tipo_contrato' => 'POR_MODULO',
                ]);
            }
        }

        // 6. Publicar en el catálogo (requiere al menos un arancel activo)
        if ($oferta->estado === OfertaAcademica::PLANIFICADA) {
            app(OfertaService::class)->cambiarEstado($oferta, OfertaAcademica::ABIERTA);
        }
    }
}
