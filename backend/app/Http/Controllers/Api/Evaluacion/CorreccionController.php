<?php

namespace App\Http\Controllers\Api\Evaluacion;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDocente;
use App\Models\CorreccionNota;
use App\Models\Nota;
use App\Services\Auditoria;
use App\Services\EvaluacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU5 · Flujo de corrección de notas.
 * El docente solicita (nota nueva + motivo); el Coordinador Académico resuelve.
 */
class CorreccionController extends Controller
{
    public function __construct(private readonly EvaluacionService $evaluaciones) {}

    /** El docente ve sus solicitudes; quien resuelve o supervisa las ve todas. */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $f = $request->validate(['estado' => ['nullable', Rule::in([CorreccionNota::PENDIENTE, CorreccionNota::APROBADA, CorreccionNota::RECHAZADA])]]);
        $todas = $usuario->tieneAlgunPermiso(['correcciones.resolver', 'academico.supervisar']);

        $lista = CorreccionNota::with([
            'nota.evaluacion.ofertaModulo.mallaModulo.modulo', 'nota.evaluacion.ofertaModulo.oferta',
            'nota.inscripcion.matricula.usuario', 'solicitante:id_usuario,nombres,apellidos', 'autorizador:id_usuario,nombres,apellidos',
        ])
            ->when(! $todas, fn ($q) => $q->where('id_usuario_solicitante', $usuario->id_usuario))
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByRaw("CASE estado WHEN 'PENDIENTE' THEN 0 ELSE 1 END")->orderByDesc('fecha_solicitud')
            ->limit(300)->get()
            ->map(fn (CorreccionNota $c) => [
                ...$c->only(['id_correccion', 'nota_anterior', 'nota_nueva', 'motivo', 'estado', 'observacion_autorizador']),
                'fecha_solicitud' => $c->fecha_solicitud?->format('Y-m-d H:i:s'),
                'fecha_autorizacion' => $c->fecha_autorizacion?->format('Y-m-d H:i:s'),
                'estudiante' => $c->nota->inscripcion->matricula->usuario->nombre_completo,
                'evaluacion' => $c->nota->evaluacion->nombre,
                'modulo' => $c->nota->evaluacion->ofertaModulo->mallaModulo->modulo->nombre,
                'oferta' => $c->nota->evaluacion->ofertaModulo->oferta->codigo,
                'solicitante' => $c->solicitante?->nombre_completo,
                'autorizador' => $c->autorizador?->nombre_completo,
            ]);

        return response()->json($lista);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_nota' => ['required', 'integer', Rule::exists('nota', 'id_nota')],
            'nota_nueva' => ['required', 'numeric', 'between:0,'.config('icap.academico.nota_maxima')],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $nota = Nota::with('evaluacion.ofertaModulo')->findOrFail($datos['id_nota']);

        $asignado = $nota->evaluacion->ofertaModulo->asignaciones()
            ->where('id_usuario_docente', $request->user()->id_usuario)->where('estado', AsignacionDocente::ACTIVO)->exists();
        abort_unless($asignado, 403, 'Solo el docente asignado al módulo puede solicitar la corrección.');

        $correccion = $this->evaluaciones->solicitarCorreccion($nota, (float) $datos['nota_nueva'], $datos['motivo'], $request->user());
        Auditoria::describir("Solicitó corregir una nota de {$correccion->nota_anterior} a {$correccion->nota_nueva}");

        return response()->json($correccion, 201);
    }

    public function resolver(Request $request, CorreccionNota $correccion): JsonResponse
    {
        $datos = $request->validate([
            'aprobar' => ['required', 'boolean'],
            'observacion' => ['nullable', 'required_if:aprobar,false,0', 'string', 'min:5', 'max:500'],
        ], ['observacion.required_if' => 'Debe explicar el motivo del rechazo.']);

        $this->evaluaciones->resolverCorreccion($correccion, (bool) $datos['aprobar'], $request->user(), $datos['observacion'] ?? null);
        Auditoria::describir(($datos['aprobar'] ? 'Aprobó' : 'Rechazó')." la corrección de nota #{$correccion->id_correccion}");

        return response()->json(['message' => $datos['aprobar'] ? 'Corrección aprobada: la nota fue actualizada.' : 'Corrección rechazada.']);
    }
}
