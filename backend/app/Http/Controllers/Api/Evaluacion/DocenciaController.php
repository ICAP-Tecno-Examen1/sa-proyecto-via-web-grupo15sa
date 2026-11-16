<?php

namespace App\Http\Controllers\Api\Evaluacion;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\AsignacionDocente;
use App\Models\Evaluacion;
use App\Models\InscripcionModulo;
use App\Models\OfertaModulo;
use App\Models\Usuario;
use App\Services\Auditoria;
use App\Services\EvaluacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU5 · Trabajo del docente en sus módulos: listas, asistencia, evaluaciones,
 * calificaciones y cierre.
 *
 * Alcance: el docente solo accede a los módulos que tiene asignados. Quien
 * tiene el permiso academico.supervisar (Coordinador Académico, Director) ve
 * todos, pero solo modifica quien además tiene docencia.registrar y está asignado.
 */
class DocenciaController extends Controller
{
    public function __construct(private readonly EvaluacionService $evaluaciones) {}

    /** Módulos asignados al docente (o todos, para quien supervisa). */
    public function modulos(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $f = $request->validate(['gestion' => ['nullable', 'integer'], 'estado' => ['nullable', Rule::in([OfertaModulo::ACTIVO, OfertaModulo::CERRADO])]]);

        $modulos = OfertaModulo::with(['mallaModulo.modulo', 'oferta.malla.programa', 'asignaciones.docente'])
            ->withCount([
                'inscripciones as estudiantes' => fn ($q) => $q->where('estado', '<>', InscripcionModulo::RETIRADO),
                'evaluaciones',
            ])
            ->when(! $usuario->tienePermiso('academico.supervisar'), fn ($q) => $q->whereHas('asignaciones', fn ($a) => $a
                ->where('id_usuario_docente', $usuario->id_usuario)->where('estado', AsignacionDocente::ACTIVO)))
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($f['gestion'] ?? null, fn ($q, $g) => $q->whereHas('oferta', fn ($o) => $o->where('gestion', $g)))
            ->get()
            ->sortBy([fn ($a, $b) => $b->oferta->gestion <=> $a->oferta->gestion, fn ($a, $b) => $a->mallaModulo->orden <=> $b->mallaModulo->orden])
            ->values()
            ->map(fn (OfertaModulo $om) => [
                'id_oferta_modulo' => $om->id_oferta_modulo,
                'estado' => $om->estado,
                'codigo' => $om->mallaModulo->modulo->codigo,
                'nombre' => $om->mallaModulo->modulo->nombre,
                'horas' => $om->mallaModulo->modulo->horas,
                'fecha_inicio' => $om->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $om->fecha_fin?->format('Y-m-d'),
                'oferta' => $om->oferta->codigo,
                'gestion' => $om->oferta->gestion,
                'modalidad' => $om->oferta->modalidad,
                'programa' => $om->oferta->malla->programa->nombre,
                'estudiantes' => $om->estudiantes,
                'evaluaciones' => $om->evaluaciones_count,
                'docentes' => $om->asignaciones->where('estado', AsignacionDocente::ACTIVO)->map(fn ($a) => $a->docente->nombre_completo)->values(),
                'es_mio' => $om->asignaciones->where('estado', AsignacionDocente::ACTIVO)->contains('id_usuario_docente', $usuario->id_usuario),
            ]);

        return response()->json($modulos);
    }

    /** Planilla completa del módulo: estudiantes, evaluaciones, notas y asistencia. */
    public function planilla(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $this->autorizar($request->user(), $ofertaModulo, false);

        return response()->json([
            ...$this->evaluaciones->planilla($ofertaModulo),
            'puede_editar' => $this->puedeEditar($request->user(), $ofertaModulo),
            'tipos_evaluacion' => Evaluacion::TIPOS,
            'estados_asistencia' => Asistencia::ESTADOS,
        ]);
    }

    /** Lista de asistencia de una fecha. */
    public function asistencia(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $this->autorizar($request->user(), $ofertaModulo, false);
        $datos = $request->validate(['fecha' => ['required', 'date']]);

        $registros = Asistencia::whereIn('id_inscripcion_modulo', $ofertaModulo->inscripciones()->select('id_inscripcion_modulo'))
            ->whereDate('fecha', $datos['fecha'])->get()->keyBy('id_inscripcion_modulo')
            ->map(fn (Asistencia $a) => ['estado' => $a->estado, 'observacion' => $a->observacion]);

        return response()->json(['fecha' => $datos['fecha'], 'registros' => $registros]);
    }

    public function guardarAsistencia(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $this->autorizar($request->user(), $ofertaModulo, true);

        $datos = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'asistencias' => ['required', 'array', 'min:1'],
            'asistencias.*.id_inscripcion_modulo' => ['required', 'integer', 'distinct'],
            'asistencias.*.estado' => ['required', Rule::in(Asistencia::ESTADOS)],
            'asistencias.*.observacion' => ['nullable', 'string', 'max:200'],
        ]);

        $this->evaluaciones->guardarAsistencia($ofertaModulo, $datos['fecha'], $datos['asistencias']);
        Auditoria::describir("Registró la asistencia del {$datos['fecha']}");

        return response()->json($this->evaluaciones->planilla($ofertaModulo));
    }

    public function eliminarSesion(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $this->autorizar($request->user(), $ofertaModulo, true);
        $datos = $request->validate(['fecha' => ['required', 'date']]);

        $this->evaluaciones->eliminarSesion($ofertaModulo, $datos['fecha']);
        Auditoria::describir("Eliminó la sesión de asistencia del {$datos['fecha']}");

        return response()->json($this->evaluaciones->planilla($ofertaModulo));
    }

    public function crearEvaluacion(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $this->autorizar($request->user(), $ofertaModulo, true);

        $evaluacion = $this->evaluaciones->guardarEvaluacion($ofertaModulo, $this->validarEvaluacion($request));
        Auditoria::describir("Creó la evaluación «{$evaluacion->nombre}»");

        return response()->json($this->evaluaciones->planilla($ofertaModulo), 201);
    }

    public function actualizarEvaluacion(Request $request, Evaluacion $evaluacion): JsonResponse
    {
        $modulo = $evaluacion->ofertaModulo;
        $this->autorizar($request->user(), $modulo, true);

        $this->evaluaciones->guardarEvaluacion($modulo, $this->validarEvaluacion($request), $evaluacion);
        Auditoria::describir("Actualizó la evaluación «{$evaluacion->nombre}»");

        return response()->json($this->evaluaciones->planilla($modulo));
    }

    public function eliminarEvaluacion(Request $request, Evaluacion $evaluacion): JsonResponse
    {
        $modulo = $evaluacion->ofertaModulo;
        $this->autorizar($request->user(), $modulo, true);

        $this->evaluaciones->eliminarEvaluacion($evaluacion);
        Auditoria::describir("Eliminó la evaluación «{$evaluacion->nombre}»");

        return response()->json($this->evaluaciones->planilla($modulo));
    }

    public function guardarNotas(Request $request, Evaluacion $evaluacion): JsonResponse
    {
        $modulo = $evaluacion->ofertaModulo;
        $this->autorizar($request->user(), $modulo, true);

        $datos = $request->validate([
            'notas' => ['required', 'array', 'min:1'],
            'notas.*.id_inscripcion_modulo' => ['required', 'integer', 'distinct'],
            'notas.*.calificacion' => ['nullable', 'numeric', 'between:0,'.config('icap.academico.nota_maxima')],
        ]);

        $this->evaluaciones->guardarNotas($evaluacion, $datos['notas']);
        Auditoria::describir("Registró calificaciones de «{$evaluacion->nombre}»");

        return response()->json($this->evaluaciones->planilla($modulo));
    }

    /** Calcula notas finales y asistencia, y fija aprobados y reprobados. */
    public function cerrar(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $this->autorizar($request->user(), $ofertaModulo, true);

        $this->evaluaciones->cerrar($ofertaModulo);
        Auditoria::describir('Cerró un módulo y fijó las notas finales');

        return response()->json($this->evaluaciones->planilla($ofertaModulo));
    }

    // ------------------------------------------------------------------

    private function validarEvaluacion(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'tipo' => ['required', Rule::in(Evaluacion::TIPOS)],
            'ponderacion' => ['required', 'numeric', 'between:1,100'],
            'fecha' => ['required', 'date'],
        ]);
    }

    private function estaAsignado(Usuario $usuario, OfertaModulo $modulo): bool
    {
        return $modulo->asignaciones()->where('id_usuario_docente', $usuario->id_usuario)
            ->where('estado', AsignacionDocente::ACTIVO)->exists();
    }

    private function puedeEditar(Usuario $usuario, OfertaModulo $modulo): bool
    {
        return $usuario->tienePermiso('docencia.registrar') && $this->estaAsignado($usuario, $modulo);
    }

    private function autorizar(Usuario $usuario, OfertaModulo $modulo, bool $escritura): void
    {
        if ($escritura) {
            abort_unless($this->puedeEditar($usuario, $modulo), 403, 'Solo el docente asignado puede registrar información en este módulo.');

            return;
        }

        abort_unless(
            $usuario->tienePermiso('academico.supervisar') || $this->estaAsignado($usuario, $modulo),
            403,
            'No tiene asignado este módulo.'
        );
    }
}
