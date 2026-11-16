<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia;
use App\Models\Certificado;
use App\Models\CorreccionNota;
use App\Models\Evaluacion;
use App\Models\InscripcionModulo;
use App\Models\Nota;
use App\Models\OfertaModulo;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU5 · Asistencia, evaluaciones, notas finales y corrección de notas.
 *
 * Reglas académicas (config/icap.php):
 *   nota final   = Σ (calificación × ponderación) / 100     (escala 0 a 100)
 *   asistencia   = sesiones cumplidas / sesiones dictadas × 100
 *   APROBADO     = nota final ≥ nota mínima  Y  asistencia ≥ asistencia mínima
 *
 * Mientras el módulo está ACTIVO el docente edita libremente. Al CERRARLO se
 * fijan nota final, asistencia y resultado; desde ese momento una nota solo
 * cambia mediante una corrección aprobada por el Coordinador Académico.
 */
class EvaluacionService
{
    // ------------------------------------------------------------------
    // Cálculos
    // ------------------------------------------------------------------

    public function notaFinal(Collection $notas, Collection $evaluaciones): float
    {
        $porEvaluacion = $notas->keyBy('id_evaluacion');

        return round($evaluaciones->sum(
            fn (Evaluacion $e) => ($porEvaluacion->get($e->id_evaluacion)?->calificacion ?? 0) * $e->ponderacion / 100
        ), 2);
    }

    /** Sin sesiones registradas no hay requisito de asistencia que incumplir. */
    public function porcentajeAsistencia(Collection $asistencias, int $sesiones): float
    {
        if ($sesiones === 0) {
            return 100.0;
        }

        $cumplidas = $asistencias->whereIn('estado', Asistencia::CUENTAN)->count();

        return round(min(100, $cumplidas * 100 / $sesiones), 2);
    }

    public function resultado(float $notaFinal, float $asistencia): string
    {
        $aprueba = $notaFinal >= config('icap.academico.nota_minima_aprobacion')
            && $asistencia >= config('icap.academico.asistencia_minima');

        return $aprueba ? InscripcionModulo::APROBADO : InscripcionModulo::REPROBADO;
    }

    /** Fechas en las que se pasó lista en el módulo. */
    public function sesiones(OfertaModulo $modulo): Collection
    {
        return Asistencia::whereIn('id_inscripcion_modulo', $modulo->inscripciones()->select('id_inscripcion_modulo'))
            ->distinct()->orderBy('fecha')->pluck('fecha')
            ->map(fn ($fecha) => Carbon::parse($fecha)->format('Y-m-d'));
    }

    // ------------------------------------------------------------------
    // Planilla del módulo (vista del docente)
    // ------------------------------------------------------------------

    public function planilla(OfertaModulo $modulo): array
    {
        $modulo->load(['mallaModulo.modulo', 'oferta.malla.programa', 'evaluaciones', 'asignaciones.docente']);
        $evaluaciones = $modulo->evaluaciones;
        $sesiones = $this->sesiones($modulo);

        $inscripciones = $modulo->inscripciones()
            ->where('estado', '<>', InscripcionModulo::RETIRADO)
            ->with(['matricula.usuario', 'notas.correcciones', 'asistencias'])
            ->get()
            ->sortBy(fn ($i) => $i->matricula->usuario->apellidos.' '.$i->matricula->usuario->nombres)
            ->values();

        $estudiantes = $inscripciones->map(function (InscripcionModulo $i) use ($evaluaciones, $sesiones, $modulo) {
            $final = $this->notaFinal($i->notas, $evaluaciones);
            $asistencia = $this->porcentajeAsistencia($i->asistencias, $sesiones->count());

            return [
                'id_inscripcion_modulo' => $i->id_inscripcion_modulo,
                'id_matricula' => $i->id_matricula,
                'numero_registro' => $i->matricula->numero_registro,
                'estudiante' => $i->matricula->usuario->apellidos.', '.$i->matricula->usuario->nombres,
                'ci' => $i->matricula->usuario->ci,
                'estado' => $i->estado,
                'notas' => $i->notas->mapWithKeys(fn (Nota $n) => [$n->id_evaluacion => [
                    'id_nota' => $n->id_nota,
                    'calificacion' => $n->calificacion,
                    'correccion_pendiente' => $n->correcciones->contains('estado', CorreccionNota::PENDIENTE),
                ]]),
                'nota_final' => $modulo->estaCerrado() ? $i->nota_final : $final,
                'porcentaje_asistencia' => $modulo->estaCerrado() ? $i->porcentaje_asistencia : $asistencia,
                'resultado_previsto' => $this->resultado($final, $asistencia),
                'asistencias' => $i->asistencias->countBy('estado'),
            ];
        });

        return [
            'modulo' => [
                'id_oferta_modulo' => $modulo->id_oferta_modulo,
                'estado' => $modulo->estado,
                'codigo' => $modulo->mallaModulo->modulo->codigo,
                'nombre' => $modulo->mallaModulo->modulo->nombre,
                'horas' => $modulo->mallaModulo->modulo->horas,
                'fecha_inicio' => $modulo->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $modulo->fecha_fin?->format('Y-m-d'),
                'oferta' => $modulo->oferta->codigo,
                'gestion' => $modulo->oferta->gestion,
                'programa' => $modulo->oferta->malla->programa->nombre,
                'docentes' => $modulo->asignaciones->where('estado', 'ACTIVO')->map(fn ($a) => $a->docente->nombre_completo)->values(),
            ],
            'evaluaciones' => $evaluaciones->values(),
            'ponderacion_total' => round($evaluaciones->sum('ponderacion'), 2),
            'sesiones' => $sesiones,
            'estudiantes' => $estudiantes,
            'reglas' => config('icap.academico'),
        ];
    }

    /** Detalle de un módulo para el propio estudiante o para el seguimiento académico. */
    public function detalleInscripcion(InscripcionModulo $i): array
    {
        $modulo = $i->ofertaModulo;
        $evaluaciones = $modulo->evaluaciones;
        $sesiones = $this->sesiones($modulo)->count();
        $cerrado = $modulo->estaCerrado();
        $notas = $i->notas->keyBy('id_evaluacion');

        return [
            'id_inscripcion_modulo' => $i->id_inscripcion_modulo,
            'id_oferta_modulo' => $modulo->id_oferta_modulo,
            'codigo' => $modulo->mallaModulo->modulo->codigo,
            'modulo' => $modulo->mallaModulo->modulo->nombre,
            'horas' => $modulo->mallaModulo->modulo->horas,
            'obligatorio' => $modulo->mallaModulo->obligatorio,
            'orden' => $modulo->mallaModulo->orden,
            'docentes' => $modulo->asignaciones->where('estado', 'ACTIVO')->map(fn ($a) => $a->docente->nombre_completo)->values(),
            'estado' => $i->estado,
            'modulo_cerrado' => $cerrado,
            'nota_final' => $cerrado ? $i->nota_final : $this->notaFinal($i->notas, $evaluaciones),
            'porcentaje_asistencia' => $cerrado ? $i->porcentaje_asistencia : $this->porcentajeAsistencia($i->asistencias, $sesiones),
            'sesiones' => $sesiones,
            'asistencias' => $i->asistencias->sortBy('fecha')->map(fn (Asistencia $a) => [
                'fecha' => $a->fecha->format('Y-m-d'), 'estado' => $a->estado, 'observacion' => $a->observacion,
            ])->values(),
            'evaluaciones' => $evaluaciones->map(fn (Evaluacion $e) => [
                'nombre' => $e->nombre,
                'tipo' => $e->tipo,
                'fecha' => $e->fecha->format('Y-m-d'),
                'ponderacion' => $e->ponderacion,
                'calificacion' => $notas->get($e->id_evaluacion)?->calificacion,
            ])->values(),
        ];
    }

    // ------------------------------------------------------------------
    // Registro de asistencia, evaluaciones y notas
    // ------------------------------------------------------------------

    /** @param  array<int, array{id_inscripcion_modulo:int, estado:string, observacion?:?string}>  $filas */
    public function guardarAsistencia(OfertaModulo $modulo, string $fecha, array $filas): void
    {
        $this->exigirAbierto($modulo);
        $validas = $this->inscripcionesActivas($modulo);

        DB::transaction(function () use ($filas, $fecha, $validas) {
            foreach ($filas as $fila) {
                if (! $validas->contains($fila['id_inscripcion_modulo'])) {
                    throw new ReglaNegocioException('Uno de los estudiantes no está inscrito en este módulo.');
                }

                Asistencia::updateOrCreate(
                    ['id_inscripcion_modulo' => $fila['id_inscripcion_modulo'], 'fecha' => $fecha],
                    ['estado' => $fila['estado'], 'observacion' => $fila['observacion'] ?? null],
                );
            }
        });
    }

    public function eliminarSesion(OfertaModulo $modulo, string $fecha): void
    {
        $this->exigirAbierto($modulo);

        Asistencia::whereIn('id_inscripcion_modulo', $modulo->inscripciones()->select('id_inscripcion_modulo'))
            ->whereDate('fecha', $fecha)->delete();
    }

    public function guardarEvaluacion(OfertaModulo $modulo, array $datos, ?Evaluacion $evaluacion = null): Evaluacion
    {
        $this->exigirAbierto($modulo);

        $otras = Evaluacion::where('id_oferta_modulo', $modulo->id_oferta_modulo)
            ->when($evaluacion, fn ($q) => $q->where('id_evaluacion', '<>', $evaluacion->id_evaluacion))
            ->sum('ponderacion');

        if (round($otras + $datos['ponderacion'], 2) > 100) {
            $disponible = max(0, round(100 - $otras, 2));

            throw new ReglaNegocioException(
                "La suma de las ponderaciones no puede superar 100%. Disponible para esta evaluación: {$disponible}%."
            );
        }

        if ($evaluacion) {
            $evaluacion->update($datos);

            return $evaluacion;
        }

        return $modulo->evaluaciones()->create($datos);
    }

    public function eliminarEvaluacion(Evaluacion $evaluacion): void
    {
        $this->exigirAbierto($evaluacion->ofertaModulo);
        $evaluacion->delete();
    }

    /** @param  array<int, array{id_inscripcion_modulo:int, calificacion:float|int|string|null}>  $filas */
    public function guardarNotas(Evaluacion $evaluacion, array $filas): void
    {
        $modulo = $evaluacion->ofertaModulo;
        $this->exigirAbierto($modulo);
        $validas = $this->inscripcionesActivas($modulo);

        DB::transaction(function () use ($filas, $evaluacion, $validas) {
            foreach ($filas as $fila) {
                if (! $validas->contains($fila['id_inscripcion_modulo'])) {
                    throw new ReglaNegocioException('Uno de los estudiantes no está inscrito en este módulo.');
                }

                $clave = ['id_inscripcion_modulo' => $fila['id_inscripcion_modulo'], 'id_evaluacion' => $evaluacion->id_evaluacion];

                if ($fila['calificacion'] === null || $fila['calificacion'] === '') {
                    Nota::where($clave)->delete();

                    continue;
                }

                Nota::updateOrCreate($clave, ['calificacion' => $fila['calificacion'], 'fecha_registro' => now()]);
            }
        });
    }

    // ------------------------------------------------------------------
    // Cierre del módulo
    // ------------------------------------------------------------------

    /** Calcula y fija nota final, asistencia y resultado de cada estudiante. */
    public function cerrar(OfertaModulo $modulo): OfertaModulo
    {
        $this->exigirAbierto($modulo);
        $evaluaciones = $modulo->evaluaciones()->get();

        if ($evaluaciones->isEmpty()) {
            throw new ReglaNegocioException('No se puede cerrar el módulo sin evaluaciones registradas.');
        }

        $total = round($evaluaciones->sum('ponderacion'), 2);

        if (abs($total - 100) > 0.009) {
            throw new ReglaNegocioException("Las ponderaciones deben sumar exactamente 100% para cerrar el módulo (suma actual: {$total}%).");
        }

        $sesiones = $this->sesiones($modulo)->count();

        return DB::transaction(function () use ($modulo, $evaluaciones, $sesiones) {
            $inscripciones = $modulo->inscripciones()->where('estado', InscripcionModulo::INSCRITO)
                ->with(['notas', 'asistencias'])->get();

            foreach ($inscripciones as $i) {
                $final = $this->notaFinal($i->notas, $evaluaciones);
                $asistencia = $this->porcentajeAsistencia($i->asistencias, $sesiones);

                $i->update([
                    'nota_final' => $final,
                    'porcentaje_asistencia' => $asistencia,
                    'estado' => $this->resultado($final, $asistencia),
                ]);
            }

            $modulo->update(['estado' => OfertaModulo::CERRADO]);

            return $modulo;
        });
    }

    // ------------------------------------------------------------------
    // Flujo de corrección de notas
    // ------------------------------------------------------------------

    public function solicitarCorreccion(Nota $nota, float $notaNueva, string $motivo, Usuario $docente): CorreccionNota
    {
        if (abs($notaNueva - $nota->calificacion) < 0.005) {
            throw new ReglaNegocioException('La nueva nota debe ser distinta a la nota actual.');
        }

        if ($nota->correcciones()->where('estado', CorreccionNota::PENDIENTE)->exists()) {
            throw new ReglaNegocioException('Esta calificación ya tiene una corrección pendiente de autorización.');
        }

        $this->exigirSinCertificado($nota);

        return CorreccionNota::create([
            'id_nota' => $nota->id_nota,
            'id_usuario_solicitante' => $docente->id_usuario,
            'nota_anterior' => $nota->calificacion,
            'nota_nueva' => $notaNueva,
            'motivo' => $motivo,
            'estado' => CorreccionNota::PENDIENTE,
            'fecha_solicitud' => now(),
        ]);
    }

    /**
     * El Coordinador Académico aprueba o rechaza. Si aprueba, la nota cambia y,
     * cuando el módulo ya estaba cerrado, se recalcula el resultado del estudiante.
     * El registro de la corrección conserva nota anterior, nueva y quién autorizó.
     */
    public function resolverCorreccion(CorreccionNota $correccion, bool $aprobar, Usuario $autorizador, ?string $observacion = null): CorreccionNota
    {
        if ($correccion->estado !== CorreccionNota::PENDIENTE) {
            throw new ReglaNegocioException('Esta solicitud de corrección ya fue resuelta.');
        }

        if ($correccion->id_usuario_solicitante === $autorizador->id_usuario) {
            throw new ReglaNegocioException('Quien solicita la corrección no puede autorizarla.');
        }

        return DB::transaction(function () use ($correccion, $aprobar, $autorizador, $observacion) {
            if ($aprobar) {
                $nota = $correccion->nota;
                $this->exigirSinCertificado($nota);
                $nota->update(['calificacion' => $correccion->nota_nueva]);
                $this->recalcularSiCerrado($nota->inscripcion);
            }

            $correccion->update([
                'estado' => $aprobar ? CorreccionNota::APROBADA : CorreccionNota::RECHAZADA,
                'id_usuario_autorizador' => $autorizador->id_usuario,
                'fecha_autorizacion' => now(),
                'observacion_autorizador' => $observacion,
            ]);

            return $correccion;
        });
    }

    private function recalcularSiCerrado(InscripcionModulo $i): void
    {
        $modulo = $i->ofertaModulo;

        if (! $modulo->estaCerrado() || $i->estado === InscripcionModulo::RETIRADO) {
            return;
        }

        $final = $this->notaFinal($i->notas()->get(), $modulo->evaluaciones()->get());
        $asistencia = $i->porcentaje_asistencia
            ?? $this->porcentajeAsistencia($i->asistencias()->get(), $this->sesiones($modulo)->count());

        $i->update(['nota_final' => $final, 'estado' => $this->resultado($final, $asistencia)]);
    }

    // ------------------------------------------------------------------

    private function exigirAbierto(OfertaModulo $modulo): void
    {
        if ($modulo->estaCerrado()) {
            throw new ReglaNegocioException(
                'El módulo ya fue cerrado. Para cambiar una calificación solicite una corrección de nota.'
            );
        }
    }

    private function exigirSinCertificado(Nota $nota): void
    {
        $emitido = Certificado::where('id_matricula', $nota->inscripcion->id_matricula)
            ->where('estado', Certificado::EMITIDO)->exists();

        if ($emitido) {
            throw new ReglaNegocioException('El estudiante ya tiene un certificado emitido; sus notas no pueden modificarse.');
        }
    }

    private function inscripcionesActivas(OfertaModulo $modulo): Collection
    {
        return $modulo->inscripciones()->where('estado', InscripcionModulo::INSCRITO)->pluck('id_inscripcion_modulo');
    }
}
