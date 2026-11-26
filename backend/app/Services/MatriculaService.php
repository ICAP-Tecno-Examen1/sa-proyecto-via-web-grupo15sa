<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Arancel;
use App\Models\BecaEstudiante;
use App\Models\Certificado;
use App\Models\InscripcionModulo;
use App\Models\MallaModulo;
use App\Models\Matricula;
use App\Models\Mensualidad;
use App\Models\OfertaAcademica;
use App\Models\OfertaModulo;
use App\Models\Preinscripcion;
use App\Models\Usuario;
use App\Models\ValidacionRequisito;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CU3 · Ciclo de vida del estudiante: preinscripción -> matrícula -> inscripción
 * a módulos -> finalización. También arma el plan de cuotas (CU4).
 *
 * Estados de la matrícula:
 *   PENDIENTE_PAGO --(pago inicial confirmado)--> ACTIVA --(módulos cerrados)--> FINALIZADA
 *   cualquiera de las dos primeras --> ANULADA
 */
class MatriculaService
{
    public function __construct(private readonly DocumentoService $documentos) {}

    // ------------------------------------------------------------------
    // Preinscripción
    // ------------------------------------------------------------------

    /** El estudiante (o la Secretaria en su nombre) solicita un cupo en una oferta. */
    public function preinscribir(Usuario $estudiante, OfertaAcademica $oferta, ?int $idArancel, ?string $observacion = null): Preinscripcion
    {
        if ($oferta->estado !== OfertaAcademica::ABIERTA) {
            throw new ReglaNegocioException('Esta oferta académica no está recibiendo preinscripciones.');
        }

        if ($oferta->cuposOcupados() >= $oferta->cupo_maximo) {
            throw new ReglaNegocioException('La oferta académica ya no tiene cupos disponibles.');
        }

        if (Matricula::where('id_usuario', $estudiante->id_usuario)->where('id_oferta', $oferta->id_oferta)
            ->where('estado', '<>', Matricula::ANULADA)->exists()) {
            throw new ReglaNegocioException('El estudiante ya está matriculado en esta oferta académica.');
        }

        $enCurso = Preinscripcion::where('id_usuario', $estudiante->id_usuario)
            ->where('id_oferta', $oferta->id_oferta)
            ->whereIn('estado', [Preinscripcion::PENDIENTE, Preinscripcion::OBSERVADA])
            ->exists();

        if ($enCurso) {
            throw new ReglaNegocioException('Ya existe una preinscripción en revisión para esta oferta académica.');
        }

        if ($idArancel) {
            $this->arancelDeLaOferta($oferta, $idArancel);
        }

        return Preinscripcion::create([
            'id_usuario' => $estudiante->id_usuario,
            'id_oferta' => $oferta->id_oferta,
            'id_arancel' => $idArancel,
            'fecha' => now()->toDateString(),
            'estado' => Preinscripcion::PENDIENTE,
            'observacion' => $observacion,
        ]);
    }

    /** La Secretaria devuelve la solicitud al estudiante (OBSERVADA) o la descarta (RECHAZADA). */
    public function observarORechazar(Preinscripcion $pre, string $estado, string $observacion, Usuario $revisor): Preinscripcion
    {
        $this->exigirRevisable($pre);

        $pre->update([
            'estado' => $estado,
            'observacion' => $observacion,
            'id_usuario_revisor' => $revisor->id_usuario,
            'fecha_revision' => now(),
        ]);

        return $pre;
    }

    /** El estudiante corrige una preinscripción observada y la reenvía a revisión. */
    public function reenviar(Preinscripcion $pre): Preinscripcion
    {
        if ($pre->estado !== Preinscripcion::OBSERVADA) {
            throw new ReglaNegocioException('Solo se puede reenviar una preinscripción observada.');
        }

        $pre->update(['estado' => Preinscripcion::PENDIENTE]);

        return $pre;
    }

    /**
     * La Secretaria aprueba la preinscripción: se genera la matrícula en estado
     * PENDIENTE_PAGO junto con su plan de cuotas.
     */
    public function aprobar(Preinscripcion $pre, int $idArancel, Usuario $revisor, ?string $observacion = null): Matricula
    {
        $this->exigirRevisable($pre);

        return DB::transaction(function () use ($pre, $idArancel, $revisor, $observacion) {
            $oferta = OfertaAcademica::lockForUpdate()->findOrFail($pre->id_oferta);
            $estudiante = $pre->usuario;

            if (! in_array($oferta->estado, [OfertaAcademica::ABIERTA, OfertaAcademica::EN_CURSO], true)) {
                throw new ReglaNegocioException('La oferta académica ya no admite nuevas matrículas.');
            }

            if ($faltantes = $this->documentos->requisitosFaltantes($estudiante)) {
                throw new ReglaNegocioException(
                    'El expediente está incompleto. Falta: '.implode(', ', $faltantes).'. Observe la preinscripción para que el estudiante lo complete.'
                );
            }

            if ($oferta->cuposOcupados() >= $oferta->cupo_maximo) {
                throw new ReglaNegocioException('La oferta académica ya no tiene cupos disponibles.');
            }

            $anterior = Matricula::where('id_usuario', $estudiante->id_usuario)->where('id_oferta', $oferta->id_oferta)->first();

            if ($anterior && $anterior->estado !== Matricula::ANULADA) {
                throw new ReglaNegocioException('El estudiante ya tiene una matrícula en esta oferta académica.');
            }

            $arancel = $this->arancelDeLaOferta($oferta, $idArancel);

            $datos = [
                'id_preinscripcion' => $pre->id_preinscripcion,
                'fecha' => now()->toDateString(),
                'fecha_finalizacion' => null,
                'estado' => Matricula::PENDIENTE_PAGO,
                'tipo_cobro' => $arancel->tipo_cobro,
                'monto_original' => $arancel->monto,
                'monto_descuento' => 0,
                'monto_final' => $arancel->monto,
            ];

            if ($anterior) {
                // La tabla solo admite una matrícula por estudiante y oferta: se reutiliza la anulada.
                $anterior->mensualidades()->whereDoesntHave('pagos')->delete();
                $anterior->update($datos);
                $matricula = $anterior;
            } else {
                $matricula = Matricula::create([
                    ...$datos,
                    'id_usuario' => $estudiante->id_usuario,
                    'id_oferta' => $oferta->id_oferta,
                    'numero_registro' => 'TMP-'.Str::uuid(),
                ]);
                $matricula->update([
                    'numero_registro' => sprintf('ICAP-%d-%05d', $oferta->gestion, $matricula->id_matricula),
                ]);
            }

            $this->generarCuotas($matricula, $arancel);
            $this->recalcularMontos($matricula);

            $pre->update([
                'estado' => Preinscripcion::APROBADA,
                'id_arancel' => $arancel->id_arancel,
                'observacion' => $observacion,
                'id_usuario_revisor' => $revisor->id_usuario,
                'fecha_revision' => now(),
            ]);

            return $matricula;
        });
    }

    // ------------------------------------------------------------------
    // Plan de cuotas y becas
    // ------------------------------------------------------------------

    /**
     * PAGO_UNICO genera una sola cuota; MENSUALIDAD genera numero_cuotas cuotas
     * mensuales. La primera vence a los N días de la matrícula (pago inicial).
     */
    public function generarCuotas(Matricula $matricula, Arancel $arancel): void
    {
        $cuotas = $arancel->tipo_cobro === Arancel::MENSUALIDAD ? max(1, $arancel->numero_cuotas) : 1;
        $base = floor($arancel->monto * 100 / $cuotas) / 100;
        $primera = Carbon::parse($matricula->fecha)->addDays(config('icap.finanzas.dias_plazo_pago_inicial'));
        $asignado = 0.0;

        for ($n = 1; $n <= $cuotas; $n++) {
            $monto = $n === $cuotas ? round($arancel->monto - $asignado, 2) : $base;
            $asignado += $monto;

            Mensualidad::create([
                'id_matricula' => $matricula->id_matricula,
                'id_arancel' => $arancel->id_arancel,
                'numero_cuota' => $n,
                'fecha_vencimiento' => $primera->copy()->addMonthsNoOverflow($n - 1)->toDateString(),
                'monto_original' => $monto,
                'monto_descuento' => 0,
                'monto_final' => $monto,
                'estado' => Mensualidad::PENDIENTE,
            ]);
        }
    }

    /**
     * Aplica el porcentaje de las becas activas a las cuotas que aún no fueron
     * pagadas y actualiza los totales de la matrícula. Una cuota ya cancelada
     * con dinero no se modifica.
     */
    public function recalcularMontos(Matricula $matricula): void
    {
        $porcentaje = min(100, (float) $matricula->becas()->where('estado', BecaEstudiante::ACTIVA)->sum('porcentaje_aplicado'));

        foreach ($matricula->mensualidades()->where('estado', '<>', Mensualidad::ANULADA)->get() as $cuota) {
            $pagado = $cuota->montoPagado();

            if ($cuota->estado === Mensualidad::PAGADA && $pagado > 0) {
                continue;
            }

            $descuento = round($cuota->monto_original * $porcentaje / 100, 2);
            $final = max(round($cuota->monto_original - $descuento, 2), $pagado);

            $cuota->update([
                'monto_descuento' => round($cuota->monto_original - $final, 2),
                'monto_final' => $final,
                'estado' => $pagado >= $final ? Mensualidad::PAGADA : Mensualidad::PENDIENTE,
            ]);
        }

        $totales = $matricula->mensualidades()->reorder()->where('estado', '<>', Mensualidad::ANULADA)
            ->selectRaw('COALESCE(SUM(monto_descuento), 0) AS descuento, COALESCE(SUM(monto_final), 0) AS final')
            ->first();

        $matricula->update([
            'monto_descuento' => $totales->descuento,
            'monto_final' => $totales->final,
        ]);

        $this->verificarActivacion($matricula);
    }

    // ------------------------------------------------------------------
    // Activación e inscripción a módulos
    // ------------------------------------------------------------------

    /**
     * Activa la matrícula cuando la primera cuota (pago inicial) está pagada
     * e inscribe al estudiante en los módulos obligatorios de la oferta.
     */
    public function verificarActivacion(Matricula $matricula): bool
    {
        $matricula->refresh();

        if ($matricula->estado !== Matricula::PENDIENTE_PAGO) {
            return false;
        }

        $primera = $matricula->mensualidades()->where('estado', '<>', Mensualidad::ANULADA)->first();

        if (! $primera || $primera->estado !== Mensualidad::PAGADA) {
            return false;
        }

        $matricula->update(['estado' => Matricula::ACTIVA]);

        $obligatorios = OfertaModulo::where('id_oferta', $matricula->id_oferta)
            ->whereHas('mallaModulo', fn ($q) => $q->where('obligatorio', true))
            ->pluck('id_oferta_modulo');

        foreach ($obligatorios as $idOfertaModulo) {
            InscripcionModulo::firstOrCreate(
                ['id_matricula' => $matricula->id_matricula, 'id_oferta_modulo' => $idOfertaModulo],
                ['estado' => InscripcionModulo::INSCRITO],
            );
        }

        return true;
    }

    /** El Coordinador Académico inscribe al estudiante en módulos de su oferta. */
    public function inscribirModulos(Matricula $matricula, array $idsOfertaModulo): void
    {
        if ($matricula->estado !== Matricula::ACTIVA) {
            throw new ReglaNegocioException('Solo se puede inscribir a módulos con una matrícula activa (pago inicial confirmado).');
        }

        $modulos = OfertaModulo::where('id_oferta', $matricula->id_oferta)
            ->whereIn('id_oferta_modulo', $idsOfertaModulo)->get();

        if ($modulos->count() !== count(array_unique($idsOfertaModulo))) {
            throw new ReglaNegocioException('Alguno de los módulos no pertenece a la oferta de esta matrícula.');
        }

        foreach ($modulos as $modulo) {
            if ($modulo->estaCerrado()) {
                throw new ReglaNegocioException('No se puede inscribir en un módulo que ya fue cerrado.');
            }

            InscripcionModulo::updateOrCreate(
                ['id_matricula' => $matricula->id_matricula, 'id_oferta_modulo' => $modulo->id_oferta_modulo],
                ['estado' => InscripcionModulo::INSCRITO],
            );
        }
    }

    /**
     * Situación de cada materia del plan de estudios del estudiante, contando las
     * recuperaciones (una materia reprobada puede volver a cursarse en otra oferta).
     *   estado: APROBADO | REPROBADO | INSCRITO (cursándola) | NO_INSCRITO
     * Para el certificado deben estar APROBADAS todas las obligatorias y todas las
     * optativas que haya cursado.
     */
    public function situacionMaterias(Matricula $matricula): Collection
    {
        $plan = MallaModulo::with('modulo')->where('id_malla', $matricula->oferta->id_malla)->orderBy('orden')->get();
        $intentos = $matricula->inscripciones()->where('estado', '<>', InscripcionModulo::RETIRADO)
            ->with('ofertaModulo.mallaModulo')->get()
            ->groupBy(fn (InscripcionModulo $i) => $i->ofertaModulo->mallaModulo->id_modulo);

        return $plan->map(function (MallaModulo $mm) use ($intentos) {
            $lista = $intentos->get($mm->id_modulo, collect());
            $aprobada = $lista->firstWhere('estado', InscripcionModulo::APROBADO);
            $ultima = $aprobada ?? $lista->sortByDesc('id_inscripcion_modulo')->first();

            return [
                'id_modulo' => $mm->id_modulo,
                'materia' => $mm->modulo->nombre,
                'obligatorio' => (bool) $mm->obligatorio,
                'estado' => match (true) {
                    (bool) $aprobada => InscripcionModulo::APROBADO,
                    $lista->contains('estado', InscripcionModulo::INSCRITO) => InscripcionModulo::INSCRITO,
                    (bool) $ultima => $ultima->estado,
                    default => 'NO_INSCRITO',
                },
                'nota_final' => $ultima?->nota_final,
                'porcentaje_asistencia' => $ultima?->porcentaje_asistencia,
                'intentos' => $lista->count(),
            ];
        })->values();
    }

    /** Materias que le faltan para el certificado (obligatorias sin aprobar y optativas cursadas sin aprobar). */
    public function materiasPendientes(Matricula $matricula): Collection
    {
        return $this->situacionMaterias($matricula)->filter(fn ($m) => $m['estado'] !== InscripcionModulo::APROBADO
            && ($m['obligatorio'] || $m['intentos'] > 0))->values();
    }

    /** Para cada materia reprobada: ofertas vigentes que la dictan, donde puede volver a cursarla. */
    public function opcionesRecuperacion(Matricula $matricula): array
    {
        return $this->situacionMaterias($matricula)
            ->where('estado', InscripcionModulo::REPROBADO)
            ->map(fn ($m) => [
                ...$m,
                'opciones' => OfertaModulo::with(['oferta.malla.programa', 'asignaciones.docente'])
                    ->where('estado', OfertaModulo::ACTIVO)
                    ->whereHas('mallaModulo', fn ($q) => $q->where('id_modulo', $m['id_modulo']))
                    ->whereHas('oferta', fn ($q) => $q->whereIn('estado', [OfertaAcademica::PLANIFICADA, OfertaAcademica::ABIERTA, OfertaAcademica::EN_CURSO]))
                    ->orderBy('fecha_inicio')->get()
                    ->map(fn (OfertaModulo $om) => [
                        'id_oferta_modulo' => $om->id_oferta_modulo,
                        'oferta' => $om->oferta->codigo,
                        'programa' => $om->oferta->malla->programa->nombre,
                        'gestion' => $om->oferta->gestion,
                        'modalidad' => $om->oferta->modalidad,
                        'fecha_inicio' => $om->fecha_inicio?->format('Y-m-d'),
                        'fecha_fin' => $om->fecha_fin?->format('Y-m-d'),
                        'docentes' => $om->asignaciones->where('estado', 'ACTIVO')->map(fn ($a) => $a->docente->nombre_completo)->values(),
                    ])->values(),
            ])->values()->all();
    }

    /**
     * Recuperación: inscribe al estudiante en una materia que reprobó, dictada en otra oferta
     * vigente. Su docente la califica como a cualquier inscrito; si la aprueba, cuenta para
     * el certificado de su matrícula original.
     */
    public function recuperar(Matricula $matricula, OfertaModulo $destino): InscripcionModulo
    {
        if (! in_array($matricula->estado, [Matricula::ACTIVA, Matricula::FINALIZADA], true)) {
            throw new ReglaNegocioException('Solo se recuperan materias de matrículas activas o finalizadas.');
        }

        if ($matricula->certificados()->where('estado', Certificado::EMITIDO)->exists()) {
            throw new ReglaNegocioException('Esta matrícula ya tiene su certificado emitido.');
        }

        $materia = $this->situacionMaterias($matricula)->firstWhere('id_modulo', $destino->mallaModulo->id_modulo);

        if (! $materia || $materia['estado'] !== InscripcionModulo::REPROBADO) {
            throw new ReglaNegocioException('Solo se puede recuperar una materia del plan del estudiante que esté reprobada.');
        }

        if ($destino->estaCerrado() || ! in_array($destino->oferta->estado, [OfertaAcademica::PLANIFICADA, OfertaAcademica::ABIERTA, OfertaAcademica::EN_CURSO], true)) {
            throw new ReglaNegocioException('Esa materia ya no se está dictando en la oferta elegida.');
        }

        return InscripcionModulo::updateOrCreate(
            ['id_matricula' => $matricula->id_matricula, 'id_oferta_modulo' => $destino->id_oferta_modulo],
            ['estado' => InscripcionModulo::INSCRITO],
        );
    }

    /** Quita una inscripción: se elimina si no tiene registros, de lo contrario queda RETIRADO. */
    public function retirarInscripcion(InscripcionModulo $inscripcion): void
    {
        if (in_array($inscripcion->estado, [InscripcionModulo::APROBADO, InscripcionModulo::REPROBADO], true)) {
            throw new ReglaNegocioException('El módulo ya fue cerrado para este estudiante; no se puede retirar la inscripción.');
        }

        if ($inscripcion->notas()->exists() || $inscripcion->asistencias()->exists()) {
            $inscripcion->update(['estado' => InscripcionModulo::RETIRADO]);

            return;
        }

        $inscripcion->delete();
    }

    // ------------------------------------------------------------------
    // Finalización y anulación
    // ------------------------------------------------------------------

    /**
     * Cierra el ciclo académico del estudiante. Exige que todos sus módulos
     * hayan sido cerrados por los docentes y deja abiertas las tres
     * validaciones de requisitos del certificado (CU6).
     */
    public function finalizar(Matricula $matricula): Matricula
    {
        if ($matricula->estado !== Matricula::ACTIVA) {
            throw new ReglaNegocioException('Solo se puede finalizar una matrícula activa.');
        }

        $inscripciones = $matricula->inscripciones()->where('estado', '<>', InscripcionModulo::RETIRADO)->get();

        if ($inscripciones->isEmpty()) {
            throw new ReglaNegocioException('El estudiante no tiene módulos inscritos.');
        }

        $abiertos = $inscripciones->where('estado', InscripcionModulo::INSCRITO)->count();

        if ($abiertos > 0) {
            throw new ReglaNegocioException("El estudiante aún tiene {$abiertos} módulo(s) sin cerrar por el docente.");
        }

        return DB::transaction(function () use ($matricula) {
            $matricula->update(['estado' => Matricula::FINALIZADA, 'fecha_finalizacion' => now()->toDateString()]);

            foreach (ValidacionRequisito::TIPOS as $tipo) {
                ValidacionRequisito::firstOrCreate(
                    ['id_matricula' => $matricula->id_matricula, 'tipo' => $tipo],
                    ['estado' => ValidacionRequisito::PENDIENTE],
                );
            }

            return $matricula;
        });
    }

    public function anular(Matricula $matricula): Matricula
    {
        if (! in_array($matricula->estado, [Matricula::PENDIENTE_PAGO, Matricula::ACTIVA], true)) {
            throw new ReglaNegocioException('Solo se puede anular una matrícula pendiente de pago o activa.');
        }

        return DB::transaction(function () use ($matricula) {
            $matricula->update(['estado' => Matricula::ANULADA]);
            $matricula->mensualidades()->where('estado', Mensualidad::PENDIENTE)->update(['estado' => Mensualidad::ANULADA]);
            $matricula->inscripciones()->where('estado', InscripcionModulo::INSCRITO)->update(['estado' => InscripcionModulo::RETIRADO]);

            return $matricula;
        });
    }

    // ------------------------------------------------------------------
    // Seguimiento académico
    // ------------------------------------------------------------------

    /** Avance del estudiante en su programa: módulo por módulo y resumen. */
    public function progreso(Matricula $matricula, EvaluacionService $evaluaciones): array
    {
        $inscripciones = $matricula->inscripciones()
            ->with(['ofertaModulo.mallaModulo.modulo', 'ofertaModulo.asignaciones.docente', 'notas.evaluacion', 'asistencias'])
            ->get()
            ->sortBy(fn ($i) => $i->ofertaModulo->mallaModulo->orden)
            ->values();

        $modulos = $inscripciones->map(fn (InscripcionModulo $i) => [
            ...$evaluaciones->detalleInscripcion($i),
            'id_modulo' => $i->ofertaModulo->mallaModulo->id_modulo,
            // Cursada en otra oferta para recuperar una materia reprobada
            'es_recuperacion' => $i->ofertaModulo->id_oferta !== $matricula->id_oferta,
            'oferta' => $i->ofertaModulo->oferta?->codigo,
        ]);

        $situacion = $this->situacionMaterias($matricula);
        $obligatorios = $situacion->where('obligatorio', true)->count();
        $aprobados = $situacion->where('obligatorio', true)->where('estado', InscripcionModulo::APROBADO)->count();

        return [
            'modulos' => $modulos,
            'resumen' => [
                'modulos_obligatorios' => $obligatorios,
                'modulos_aprobados' => $aprobados,
                'modulos_reprobados' => $situacion->where('estado', InscripcionModulo::REPROBADO)->count(),
                // Lo que le falta para el certificado (pendientes y reprobadas sin recuperar)
                'materias_pendientes' => $this->materiasPendientes($matricula)->pluck('materia')->values(),
                'modulos_en_curso' => $inscripciones->where('estado', InscripcionModulo::INSCRITO)->count(),
                'porcentaje_avance' => $obligatorios > 0 ? round($aprobados * 100 / $obligatorios, 1) : 0,
            ],
        ];
    }

    /** Relaciones que necesita resumen(). */
    public const RELACIONES = ['usuario', 'oferta.malla.programa'];

    /** Ficha corta de la matrícula para listados. */
    public function resumen(Matricula $matricula): array
    {
        $oferta = $matricula->oferta;

        return [
            ...$matricula->only(['id_matricula', 'numero_registro', 'estado', 'tipo_cobro', 'monto_original', 'monto_descuento', 'monto_final', 'id_oferta', 'id_usuario']),
            'fecha' => $matricula->fecha?->format('Y-m-d'),
            'fecha_finalizacion' => $matricula->fecha_finalizacion?->format('Y-m-d'),
            'estudiante' => $matricula->usuario->only(['id_usuario', 'nombre_completo', 'nombres', 'apellidos', 'ci', 'correo', 'telefono']),
            'oferta' => [
                'id_oferta' => $oferta->id_oferta,
                'codigo' => $oferta->codigo,
                'gestion' => $oferta->gestion,
                'modalidad' => $oferta->modalidad,
                'estado' => $oferta->estado,
                'fecha_inicio' => $oferta->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $oferta->fecha_fin?->format('Y-m-d'),
            ],
            'programa' => $oferta->malla->programa->only(['id_programa', 'codigo', 'nombre', 'tipo']),
        ];
    }

    // ------------------------------------------------------------------

    private function exigirRevisable(Preinscripcion $pre): void
    {
        if (! in_array($pre->estado, [Preinscripcion::PENDIENTE, Preinscripcion::OBSERVADA], true)) {
            throw new ReglaNegocioException('Esta preinscripción ya fue resuelta.');
        }
    }

    private function arancelDeLaOferta(OfertaAcademica $oferta, int $idArancel): Arancel
    {
        $arancel = Arancel::where('id_oferta', $oferta->id_oferta)
            ->where('estado', Arancel::ACTIVO)->find($idArancel);

        if (! $arancel) {
            throw new ReglaNegocioException('El plan de pago seleccionado no está disponible para esta oferta académica.');
        }

        return $arancel;
    }
}
