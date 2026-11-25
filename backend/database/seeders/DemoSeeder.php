<?php

namespace Database\Seeders;

use App\Models\Arancel;
use App\Models\Asistencia;
use App\Models\AsignacionDocente;
use App\Models\Beca;
use App\Models\BecaEstudiante;
use App\Models\Matricula;
use App\Models\MetodoPago;
use App\Models\Modulo;
use App\Models\OfertaAcademica;
use App\Models\Pago;
use App\Models\Programa;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\CertificacionService;
use App\Services\DocumentoService;
use App\Services\EvaluacionService;
use App\Services\MallaService;
use App\Services\MatriculaService;
use App\Services\OfertaService;
use App\Services\PagoService;
use App\Services\UsuarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * DATOS DE DEMOSTRACIÓN (opcional):  php artisan db:seed --class=DemoSeeder
 *
 * Usa los mismos servicios que la aplicación, de modo que los datos respetan
 * todas las reglas de negocio. Crea:
 *   - una oferta de la gestión anterior ya FINALIZADA (historial, certificados, reportes)
 *   - una oferta EN_CURSO con estudiantes, pagos por conciliar y deudas
 *   - una oferta ABIERTA con costos, lista para demostrar el flujo completo
 *
 * No se ejecuta dos veces: si el programa DEMO ya existe, termina.
 */
class DemoSeeder extends Seeder
{
    private Usuario $director;

    private Usuario $secretaria;

    private Usuario $coordAcademico;

    private Usuario $coordFinanciero;

    public function __construct(
        private readonly MallaService $mallas,
        private readonly OfertaService $ofertas,
        private readonly MatriculaService $matriculas,
        private readonly PagoService $pagos,
        private readonly EvaluacionService $evaluaciones,
        private readonly CertificacionService $certificacion,
        private readonly DocumentoService $documentos,
        private readonly UsuarioService $usuarios,
    ) {}

    public function run(): void
    {
        if (Programa::where('codigo', 'DEMO-OFI')->exists()) {
            $this->command?->warn('Los datos de demostración ya fueron cargados.');

            return;
        }

        $this->call(DatabaseSeeder::class);

        $this->director = Usuario::where('username', 'director')->firstOrFail();
        $this->secretaria = Usuario::where('username', 'secretaria')->firstOrFail();
        $this->coordAcademico = Usuario::where('username', 'coord.academico')->firstOrFail();
        $this->coordFinanciero = Usuario::where('username', 'coord.financiero')->firstOrFail();
        $docentes = Usuario::whereIn('username', ['docente', 'docente2'])->orderBy('id_usuario')->get();

        $estudiantes = $this->crearEstudiantes();
        $anterior = now()->year - 1;

        // ---------------- Programas, módulos y mallas ----------------
        $ofimatica = Programa::create(['codigo' => 'DEMO-OFI', 'nombre' => 'Ofimática Profesional', 'tipo' => 'CURSO', 'estado' => 'ACTIVO',
            'descripcion' => 'Dominio de procesadores de texto, hojas de cálculo y presentaciones para el entorno laboral.']);
        $contabilidad = Programa::create(['codigo' => 'DEMO-CON', 'nombre' => 'Curso de Contabilidad Básica', 'tipo' => 'CURSO', 'estado' => 'ACTIVO',
            'descripcion' => 'Fundamentos contables, registro de operaciones y elaboración de estados financieros.']);
        $web = Programa::create(['codigo' => 'DEMO-WEB', 'nombre' => 'Desarrollo Web desde Cero', 'tipo' => 'PROGRAMA_TECNICO', 'estado' => 'ACTIVO',
            'descripcion' => 'HTML, CSS, JavaScript y publicación de sitios web modernos.']);

        $mod = fn (string $codigo, string $nombre, int $horas) => Modulo::create(['codigo' => $codigo, 'nombre' => $nombre, 'horas' => $horas])->id_modulo;
        $malla = fn (Programa $p, string $version, array $modulos) => $this->mallas->crear([
            'id_programa' => $p->id_programa, 'version' => $version, 'fecha_vigencia' => now()->startOfYear()->toDateString(),
            'modulos' => collect($modulos)->values()->map(fn ($id, $i) => ['id_modulo' => $id, 'orden' => $i + 1, 'obligatorio' => true])->all(),
        ]);

        $mallaOfi = $malla($ofimatica, '2025-1', [
            $mod('OFI-01', 'Procesador de textos', 40), $mod('OFI-02', 'Hojas de cálculo', 60), $mod('OFI-03', 'Presentaciones efectivas', 30),
        ]);
        $mallaCon = $malla($contabilidad, '2026-1', [
            $mod('CON-01', 'Fundamentos de contabilidad', 60), $mod('CON-02', 'Registros contables', 60), $mod('CON-03', 'Estados financieros', 80),
        ]);
        $mallaWeb = $malla($web, '2026-1', [
            $mod('WEB-01', 'HTML y CSS', 50), $mod('WEB-02', 'JavaScript', 70), $mod('WEB-03', 'Publicación de sitios web', 30),
        ]);

        // ---------------- 1) Oferta de la gestión anterior, finalizada ----------------
        $ofertaPasada = $this->crearOferta($mallaOfi, "OFI-{$anterior}-A", 'PRESENCIAL', "{$anterior}-03-03", "{$anterior}-06-27", $anterior, $docentes, 1200, 4);
        $notasPorEstudiante = [[90, 85], [78, 70], [65, 72], [88, 95], [40, 35], [70, 60]];

        foreach ($estudiantes->take(6)->values() as $i => $estudiante) {
            $matricula = $this->matricular($estudiante, $ofertaPasada, $i % 2 === 0 ? Arancel::PAGO_UNICO : Arancel::MENSUALIDAD, "{$anterior}-02-".(10 + $i));

            if ($i === 1) {
                $this->asignarBeca($matricula, 'Beca Socioeconómica');
            }

            $this->pagarTodo($matricula, Carbon::parse("{$anterior}-02-20")->addDays($i * 3));
        }

        $this->dictarYCerrar($ofertaPasada, $notasPorEstudiante, Carbon::parse("{$anterior}-03-10"));
        $ofertaPasada->update(['estado' => OfertaAcademica::FINALIZADA]);

        foreach (Matricula::where('id_oferta', $ofertaPasada->id_oferta)->orderBy('id_matricula')->get() as $i => $matricula) {
            $this->matriculas->finalizar($matricula);
            $matricula->update(['fecha_finalizacion' => "{$anterior}-06-30"]);

            // Los cuatro primeros aprobados completan el trámite y reciben su certificado.
            if ($i < 4) {
                $this->certificacion->validar($matricula, 'ACADEMICO', 'APROBADO', null, $this->coordAcademico);
                $this->certificacion->validar($matricula, 'FINANCIERO', 'APROBADO', null, $this->coordFinanciero);
                $this->certificacion->validar($matricula, 'DOCUMENTAL', 'APROBADO', null, $this->secretaria);

                if ($i < 3) {
                    $this->certificacion->emitir($matricula, $this->director)->update(['fecha_emision' => "{$anterior}-07-1".$i]);
                }
            }
        }

        // ---------------- 2) Oferta en curso ----------------
        $ofertaCurso = $this->crearOferta($mallaCon, 'CON-'.now()->year.'-A', 'SEMIPRESENCIAL', now()->subMonths(2)->toDateString(), now()->addMonths(3)->toDateString(), now()->year, $docentes, 2400, 6);

        foreach ($estudiantes->slice(4, 6)->values() as $i => $estudiante) {
            $matricula = $this->matricular($estudiante, $ofertaCurso, Arancel::MENSUALIDAD, now()->subMonths(2)->subDays(10 - $i)->toDateString());

            if ($i === 0) {
                $this->asignarBeca($matricula, 'Beca Excelencia Académica');
            }

            // Pago inicial confirmado para todos; el resto queda en distintos estados.
            $cuotas = $matricula->mensualidades()->get();
            $this->pagarCuota($cuotas[0], now()->subMonths(2)->addDays($i));

            if ($i < 3) {
                $this->pagarCuota($cuotas[1], now()->subMonth()->addDays($i));
            }

            if ($i === 3 || $i === 4) {
                // Comprobante enviado por el estudiante, a la espera del Coordinador Financiero.
                $this->pagos->registrar($cuotas[1], $this->metodo(MetodoPago::COMPROBANTE), $this->pagador($estudiante), $cuotas[1]->saldo(),
                    'TRX-'.(48200 + $i), 'Titular', $this->archivoDemo('Comprobante de pago'), $estudiante);
            }
        }

        $ofertaCurso->update(['estado' => OfertaAcademica::EN_CURSO]);
        $this->dictarPrimerModulo($ofertaCurso);

        // ---------------- 3) Oferta abierta, lista para la demostración ----------------
        $ofertaAbierta = $this->crearOferta($mallaWeb, 'WEB-'.now()->year.'-B', 'VIRTUAL', now()->addWeeks(3)->toDateString(), now()->addMonths(4)->toDateString(), now()->year, $docentes, 1800, 5);

        foreach ($estudiantes->slice(10, 2)->values() as $estudiante) {
            $this->matriculas->preinscribir($estudiante, $ofertaAbierta, null, 'Solicito un cupo para el turno de la noche.');
        }

        // ---------------- Documentación institucional de gestiones anteriores ----------------
        foreach ([
            ['Resolución de aprobación del programa de Ofimática', 'RESOLUCION', 'INSTITUCIONAL', $anterior - 1],
            ["Informe de gestión académica {$anterior}", 'INFORME', 'INSTITUCIONAL', $anterior],
            ["Acta de calificaciones · {$ofertaPasada->codigo}", 'ACTA_NOTAS', 'ACADEMICO', $anterior],
            ["Informe de ejecución presupuestaria {$anterior}", 'INFORME', 'FINANCIERO', $anterior],
            ['Contrato de servicios docentes', 'CONTRATO', 'ACADEMICO', $anterior],
        ] as [$nombre, $tipo, $categoria, $gestion]) {
            $this->documentos->registrar([
                'nombre' => $nombre, 'tipo' => $tipo, 'categoria' => $categoria, 'gestion' => $gestion,
                'fecha_documento' => "{$gestion}-12-15", 'descripcion' => 'Documento de demostración del repositorio histórico.',
            ], $this->archivoDemo($nombre), $this->secretaria);
        }

        Storage::deleteDirectory('tmp');

        $this->command?->info('Datos de demostración cargados. Contraseña de todas las cuentas: '.UsuarioSeeder::PASSWORD);
    }

    // ------------------------------------------------------------------

    private function crearEstudiantes()
    {
        $personas = [
            ['Camila', 'Rojas Vaca'], ['Diego', 'Arteaga Soliz'], ['Valeria', 'Justiniano Peña'], ['Mateo', 'Salvatierra Roca'],
            ['Daniela', 'Cuéllar Méndez'], ['Sebastián', 'Hurtado Vargas'], ['Gabriela', 'Añez Limpias'], ['Andrés', 'Parada Chávez'],
            ['Fernanda', 'Saucedo Ribera'], ['Joaquín', 'Melgar Antelo'], ['Lucía', 'Barbery Suárez'], ['Nicolás', 'Egüez Pinto'],
        ];

        $idRol = $this->usuarios->idRol(Rol::ESTUDIANTE);
        $hash = Hash::make(UsuarioSeeder::PASSWORD);

        return collect($personas)->map(function (array $p, int $i) use ($idRol, $hash) {
            $username = 'estudiante'.($i + 1);

            $usuario = Usuario::create([
                'nombres' => $p[0], 'apellidos' => $p[1], 'tipo_documento' => 'CI', 'ci' => (string) (8100100 + $i * 37),
                'correo' => $username.'@icap.edu.bo', 'telefono' => '7'.(6000000 + $i * 1111), 'direccion' => 'Santa Cruz de la Sierra',
                'fecha_nacimiento' => (1995 + $i % 8).'-0'.(1 + $i % 9).'-15', 'estado' => 'ACTIVO',
                'fecha_registro' => now()->subMonths(14 - $i), 'username' => $username, 'password_hash' => $hash,
            ]);
            $this->usuarios->sincronizarRoles($usuario, [$idRol]);

            // Expediente único completo
            foreach (config('icap.documentos_requeridos') as $tipo => $etiqueta) {
                $this->documentos->registrar([
                    'nombre' => $etiqueta.' - '.$usuario->nombre_completo, 'tipo' => $tipo, 'categoria' => 'PERSONAL', 'gestion' => now()->year - 1,
                ], $this->archivoDemo($etiqueta), $usuario, [$usuario->id_usuario]);
            }

            return $usuario;
        });
    }

    private function crearOferta($malla, string $codigo, string $modalidad, string $inicio, string $fin, int $gestion, $docentes, float $costo, int $cuotas): OfertaAcademica
    {
        $oferta = $this->ofertas->crear([
            'id_malla' => $malla->id_malla, 'codigo' => $codigo, 'modalidad' => $modalidad,
            'fecha_inicio' => $inicio, 'fecha_fin' => $fin, 'cupo_maximo' => 25, 'cupo_minimo' => 5, 'gestion' => $gestion,
        ]);

        foreach ($oferta->ofertaModulos()->orderBy('id_oferta_modulo')->get() as $i => $modulo) {
            AsignacionDocente::create([
                'id_oferta_modulo' => $modulo->id_oferta_modulo, 'id_usuario_docente' => $docentes[$i % $docentes->count()]->id_usuario,
                'estado' => 'ACTIVO', 'tipo_contrato' => 'POR_MODULO', 'fecha_contrato' => $inicio, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
                'monto_honorario' => 1500 + $i * 250,
            ]);
        }

        Arancel::create(['id_oferta' => $oferta->id_oferta, 'concepto' => 'Pago único al contado', 'monto' => round($costo * 0.9), 'tipo_cobro' => Arancel::PAGO_UNICO, 'numero_cuotas' => 1]);
        Arancel::create(['id_oferta' => $oferta->id_oferta, 'concepto' => "Plan de {$cuotas} mensualidades", 'monto' => $costo, 'tipo_cobro' => Arancel::MENSUALIDAD, 'numero_cuotas' => $cuotas]);

        return $this->ofertas->cambiarEstado($oferta, OfertaAcademica::ABIERTA);
    }

    private function matricular(Usuario $estudiante, OfertaAcademica $oferta, string $tipoCobro, string $fecha): Matricula
    {
        $arancel = $oferta->aranceles()->where('tipo_cobro', $tipoCobro)->firstOrFail();

        Carbon::setTestNow(Carbon::parse($fecha));
        $pre = $this->matriculas->preinscribir($estudiante, $oferta, $arancel->id_arancel);
        $matricula = $this->matriculas->aprobar($pre, $arancel->id_arancel, $this->secretaria);
        Carbon::setTestNow();

        return $matricula;
    }

    private function asignarBeca(Matricula $matricula, string $nombre): void
    {
        $beca = Beca::where('nombre', $nombre)->firstOrFail();

        BecaEstudiante::create([
            'id_beca' => $beca->id_beca, 'id_matricula' => $matricula->id_matricula, 'porcentaje_aplicado' => $beca->porcentaje,
            'estado' => BecaEstudiante::ACTIVA, 'fecha_asignacion' => $matricula->fecha,
        ]);
        $this->matriculas->recalcularMontos($matricula);
    }

    private function pagarTodo(Matricula $matricula, Carbon $desde): void
    {
        foreach ($matricula->mensualidades()->get() as $i => $cuota) {
            $this->pagarCuota($cuota, $desde->copy()->addMonths($i));
        }
    }

    /** Registra en efectivo y confirma, dejando la fecha histórica indicada. */
    private function pagarCuota($cuota, Carbon $fecha): void
    {
        if ($cuota->refresh()->saldo() <= 0) {
            return;
        }

        $estudiante = $cuota->matricula->usuario;
        $tipo = $cuota->numero_cuota % 2 === 0 ? MetodoPago::COMPROBANTE : MetodoPago::EFECTIVO;

        $pago = $this->pagos->registrar($cuota, $this->metodo($tipo), $this->pagador($estudiante), $cuota->saldo(), 'REC-'.$cuota->id_mensualidad,
            'Titular', $tipo === MetodoPago::COMPROBANTE ? $this->archivoDemo('Comprobante de pago') : null, $this->secretaria);
        $this->pagos->confirmar($pago, $this->coordFinanciero);

        Pago::whereKey($pago->id_pago)->update(['fecha' => $fecha, 'fecha_conciliacion' => $fecha->copy()->addDay()]);
    }

    /** Dicta todos los módulos de la oferta, registra notas y los cierra. */
    private function dictarYCerrar(OfertaAcademica $oferta, array $notas, Carbon $inicio): void
    {
        foreach ($oferta->ofertaModulos()->orderBy('id_oferta_modulo')->get() as $n => $modulo) {
            $this->registrarActividad($modulo, $notas, $inicio->copy()->addWeeks($n * 4), 6);
            $this->evaluaciones->cerrar($modulo);
        }
    }

    /** En la oferta en curso solo el primer módulo tiene actividad (queda abierto). */
    private function dictarPrimerModulo(OfertaAcademica $oferta): void
    {
        $modulo = $oferta->ofertaModulos()->orderBy('id_oferta_modulo')->first();
        $this->registrarActividad($modulo, [[82, null], [74, null], [91, null], [55, null], [68, null], [77, null]], now()->subWeeks(6), 5, false);
    }

    private function registrarActividad($modulo, array $notas, Carbon $inicio, int $sesiones, bool $conFinal = true): void
    {
        $inscripciones = $modulo->inscripciones()->orderBy('id_inscripcion_modulo')->pluck('id_inscripcion_modulo')->values();

        for ($s = 0; $s < $sesiones; $s++) {
            $fecha = $inicio->copy()->addWeeks($s)->toDateString();
            $this->evaluaciones->guardarAsistencia($modulo, $fecha, $inscripciones->map(fn ($id, $i) => [
                'id_inscripcion_modulo' => $id,
                // El quinto estudiante falta con frecuencia.
                'estado' => $i === 4 && $s % 2 === 0 ? Asistencia::AUSENTE : (($i + $s) % 7 === 0 ? Asistencia::TARDANZA : Asistencia::PRESENTE),
            ])->all());
        }

        $parcial = $this->evaluaciones->guardarEvaluacion($modulo, ['nombre' => 'Trabajo práctico', 'tipo' => 'PRACTICO', 'ponderacion' => 40, 'fecha' => $inicio->copy()->addWeeks(2)->toDateString()]);
        $this->evaluaciones->guardarNotas($parcial, $inscripciones->map(fn ($id, $i) => ['id_inscripcion_modulo' => $id, 'calificacion' => $notas[$i % count($notas)][0]])->all());

        $final = $this->evaluaciones->guardarEvaluacion($modulo, ['nombre' => 'Examen final', 'tipo' => 'EXAMEN_FINAL', 'ponderacion' => 60, 'fecha' => $inicio->copy()->addWeeks($sesiones)->toDateString()]);

        if ($conFinal) {
            $this->evaluaciones->guardarNotas($final, $inscripciones->map(fn ($id, $i) => ['id_inscripcion_modulo' => $id, 'calificacion' => $notas[$i % count($notas)][1]])->all());
        }
    }

    private function metodo(string $tipo): MetodoPago
    {
        return MetodoPago::where('tipo', $tipo)->firstOrFail();
    }

    private function pagador(Usuario $u): array
    {
        return ['nombres' => $u->nombres, 'apellidos' => $u->apellidos, 'tipo_documento' => 'CI', 'numero_documento' => $u->ci, 'telefono' => $u->telefono, 'correo' => $u->correo];
    }

    /** PDF de una página que hace de archivo escaneado. */
    private function archivoDemo(string $titulo): \Illuminate\Http\UploadedFile
    {
        static $contenido = [];
        $contenido[$titulo] ??= Pdf::loadHTML('<h2 style="font-family: DejaVu Sans">'.e($titulo).'</h2><p style="font-family: DejaVu Sans">Documento de demostración del sistema ICAP.</p>')->output();

        $ruta = 'tmp/demo-'.uniqid().'.pdf';
        Storage::put($ruta, $contenido[$titulo]);

        return new \Illuminate\Http\UploadedFile(Storage::path($ruta), 'documento.pdf', 'application/pdf', null, true);
    }
}
