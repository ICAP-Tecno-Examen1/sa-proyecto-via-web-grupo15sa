<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Beca;
use App\Models\BecaEstudiante;
use App\Models\Matricula;
use App\Services\Auditoria;
use App\Services\BecaService;
use App\Services\DocumentoService;
use App\Services\MatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CU4 · Becas y descuentos: catálogo, solicitudes de los estudiantes (con respaldo)
 * y su revisión por el Coordinador Financiero. Al aprobar, asignar o revocar una beca
 * se recalculan las cuotas aún no pagadas.
 */
class BecaController extends Controller
{
    public function __construct(
        private readonly MatriculaService $matriculas,
        private readonly BecaService $becas,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Beca::with('ofertas:id_oferta,codigo')
                ->withCount(['asignaciones' => fn ($q) => $q->where('estado', BecaEstudiante::ACTIVA)])
                ->orderBy('nombre')->get()
                ->map(fn (Beca $b) => $this->formatoBeca($b))
        );
    }

    /**
     * Becas que se pueden solicitar o asignar (solo ACTIVAS). Con id_matricula, solo las
     * que cubren el curso de esa matrícula.
     */
    public function disponibles(Request $request): JsonResponse
    {
        $idMatricula = $request->validate(['id_matricula' => ['nullable', 'integer']])['id_matricula'] ?? null;
        $matricula = $idMatricula ? Matricula::findOrFail($idMatricula) : null;

        if ($matricula && $matricula->id_usuario !== $request->user()->id_usuario) {
            abort_unless($request->user()->tieneAlgunPermiso(['becas.ver', 'becas.asignar']), 403, 'No puede consultar las becas de esta matrícula.');
        }

        return response()->json(
            Beca::with('ofertas:id_oferta,codigo')->where('estado', Beca::ACTIVA)->orderBy('nombre')->get()
                ->filter(fn (Beca $b) => ! $matricula || $b->motivoNoAplica($matricula) === null)
                ->map(fn (Beca $b) => $this->formatoBeca($b))
                ->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $beca = Beca::create(collect($datos)->except('ofertas')->all());
        $beca->ofertas()->sync($datos['ofertas'] ?? []);
        Auditoria::describir("Registró la beca «{$beca->nombre}»");

        return response()->json($beca, 201);
    }

    public function update(Request $request, Beca $beca): JsonResponse
    {
        $datos = $this->validar($request);
        $beca->update(collect($datos)->except('ofertas')->all());
        $beca->ofertas()->sync($datos['ofertas'] ?? []);
        Auditoria::describir("Actualizó la beca «{$beca->nombre}»");

        return response()->json($beca);
    }

    /** Activa o desactiva la beca. Inactiva no se ofrece ni se asigna; las ya aplicadas siguen. */
    public function cambiarEstado(Beca $beca): JsonResponse
    {
        $beca->update(['estado' => $beca->estado === Beca::ACTIVA ? Beca::INACTIVA : Beca::ACTIVA]);
        Auditoria::describir(($beca->estado === Beca::ACTIVA ? 'Activó' : 'Desactivó')." la beca «{$beca->nombre}»");

        return response()->json($beca);
    }

    public function destroy(Beca $beca): JsonResponse
    {
        abort_if($beca->asignaciones()->exists(), 422, 'La beca ya fue asignada o solicitada por estudiantes y no se puede eliminar.');

        $beca->delete();
        Auditoria::describir("Eliminó la beca «{$beca->nombre}»");

        return response()->json(['message' => 'Beca eliminada.']);
    }

    /** Becas asignadas (activas o revocadas), con estudiante y programa. */
    public function asignaciones(): JsonResponse
    {
        return response()->json(
            BecaEstudiante::with(['beca', 'matricula' => fn ($q) => $q->with(MatriculaService::RELACIONES)])
                ->whereIn('estado', [BecaEstudiante::ACTIVA, BecaEstudiante::REVOCADA])
                ->orderByDesc('id_beca_estudiante')->get()
                ->map(fn (BecaEstudiante $b) => $this->formato($b))
        );
    }

    /** Solicitudes de los estudiantes (por defecto, las que esperan revisión). */
    public function solicitudes(Request $request): JsonResponse
    {
        $estado = $request->validate(['estado' => ['nullable', Rule::in([BecaEstudiante::SOLICITADA, BecaEstudiante::RECHAZADA])]])['estado'] ?? BecaEstudiante::SOLICITADA;

        return response()->json(
            BecaEstudiante::with(['beca', 'revisor', 'matricula' => fn ($q) => $q->with(MatriculaService::RELACIONES)])
                ->where('estado', $estado)
                ->orderBy('fecha_solicitud')->get()
                ->map(fn (BecaEstudiante $b) => $this->formato($b))
        );
    }

    /** El estudiante solicita una beca para su matrícula, con documento de respaldo. */
    public function solicitar(Request $request, Matricula $matricula): JsonResponse
    {
        abort_unless($matricula->id_usuario === $request->user()->id_usuario, 403, 'Solo puede solicitar becas para sus propias matrículas.');

        $archivos = config('icap.archivos');
        $datos = $request->validate([
            'id_beca' => ['required', 'integer', Rule::exists('beca', 'id_beca')],
            'motivo' => ['nullable', 'string', 'max:1000'],
            // Obligatorio en becas por convenio (lo exige BecaService); opcional en las demás
            'respaldo' => ['nullable', 'file', 'mimes:'.implode(',', $archivos['extensiones']), 'max:'.$archivos['tamano_maximo_kb']],
        ]);

        $beca = Beca::findOrFail($datos['id_beca']);
        $solicitud = $this->becas->solicitar($matricula, $beca, $datos['motivo'] ?? null, $request->file('respaldo'));
        Auditoria::describir("Solicitó la beca «{$beca->nombre}» para la matrícula {$matricula->numero_registro}");

        return response()->json($this->formato($solicitud->load(['beca', 'matricula' => fn ($q) => $q->with(MatriculaService::RELACIONES)])), 201);
    }

    public function aprobar(Request $request, BecaEstudiante $becaEstudiante): JsonResponse
    {
        $datos = $request->validate([
            'porcentaje_aplicado' => ['nullable', 'numeric', 'between:0.01,100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $solicitud = $this->becas->aprobar($becaEstudiante, isset($datos['porcentaje_aplicado']) ? (float) $datos['porcentaje_aplicado'] : null, $datos['observacion'] ?? null, $request->user());
        Auditoria::describir("Aprobó la beca «{$solicitud->beca->nombre}» ({$solicitud->porcentaje_aplicado}%) de la matrícula {$solicitud->matricula->numero_registro}");

        return response()->json(['message' => 'Beca aprobada. Las cuotas pendientes del estudiante fueron recalculadas.']);
    }

    public function rechazar(Request $request, BecaEstudiante $becaEstudiante): JsonResponse
    {
        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], ['motivo.required' => 'Explique al estudiante por qué se rechaza la beca.']);

        $solicitud = $this->becas->rechazar($becaEstudiante, $datos['motivo'], $request->user());
        Auditoria::describir("Rechazó la beca «{$solicitud->beca->nombre}» de la matrícula {$solicitud->matricula->numero_registro}");

        return response()->json(['message' => 'Solicitud rechazada. El estudiante verá el motivo en «Mis pagos».']);
    }

    /** Documento de respaldo: lo ve el estudiante dueño o quien revisa becas. */
    public function respaldo(Request $request, BecaEstudiante $becaEstudiante, DocumentoService $documentos): StreamedResponse
    {
        $usuario = $request->user();
        abort_unless($becaEstudiante->matricula->id_usuario === $usuario->id_usuario || $usuario->tieneAlgunPermiso(['becas.ver', 'becas.asignar']), 403, 'No puede ver este documento.');

        return $documentos->entregar($becaEstudiante->respaldo_url, 'respaldo-beca-'.$becaEstudiante->id_beca_estudiante, true);
    }

    /** Asignación directa (por ejemplo, un convenio ya verificado). */
    public function asignar(Request $request, Matricula $matricula): JsonResponse
    {
        $datos = $request->validate([
            'id_beca' => ['required', 'integer', Rule::exists('beca', 'id_beca')],
            'porcentaje_aplicado' => ['nullable', 'numeric', 'between:0.01,100'],
        ]);

        $beca = Beca::findOrFail($datos['id_beca']);
        $asignada = $this->becas->asignar($matricula, $beca, isset($datos['porcentaje_aplicado']) ? (float) $datos['porcentaje_aplicado'] : null, $request->user());
        Auditoria::describir("Asignó la beca «{$beca->nombre}» ({$asignada->porcentaje_aplicado}%) a la matrícula {$matricula->numero_registro}");

        return response()->json(['message' => 'Beca asignada. Las cuotas pendientes fueron recalculadas.']);
    }

    public function revocar(BecaEstudiante $becaEstudiante): JsonResponse
    {
        abort_if($becaEstudiante->estado !== BecaEstudiante::ACTIVA, 422, 'Solo se revoca una beca activa.');

        $becaEstudiante->update(['estado' => BecaEstudiante::REVOCADA]);
        $this->matriculas->recalcularMontos($becaEstudiante->matricula);

        Auditoria::describir("Revocó una beca de la matrícula {$becaEstudiante->matricula->numero_registro}");

        return response()->json(['message' => 'Beca revocada. Las cuotas pendientes fueron recalculadas.']);
    }

    private function formato(BecaEstudiante $b): array
    {
        return [
            ...$b->only(['id_beca_estudiante', 'porcentaje_aplicado', 'estado', 'motivo', 'observacion_revision']),
            'fecha_asignacion' => $b->fecha_asignacion?->format('Y-m-d'),
            'fecha_solicitud' => $b->fecha_solicitud?->format('Y-m-d H:i:s'),
            'fecha_revision' => $b->fecha_revision?->format('Y-m-d H:i:s'),
            'revisado_por' => $b->revisor?->nombre_completo,
            'tiene_respaldo' => filled($b->respaldo_url),
            'beca' => $b->beca->nombre,
            'institucion' => $b->beca->institucion_financiadora,
            'porcentaje_beca' => $b->beca->porcentaje,
            'requisitos' => $b->beca->requisitos,
            'matricula' => $this->matriculas->resumen($b->matricula),
        ];
    }

    private function formatoBeca(Beca $b): array
    {
        return [
            ...$b->only(['id_beca', 'nombre', 'descripcion', 'porcentaje', 'requisitos', 'estado', 'institucion_financiadora']),
            'asignaciones_count' => $b->asignaciones_count ?? null,
            'requiere_respaldo' => $b->requiereRespaldo(),
            // Ofertas a las que se limita (vacío = todas)
            'ofertas' => $b->ofertas->map(fn ($o) => ['id_oferta' => $o->id_oferta, 'codigo' => $o->codigo])->values(),
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'porcentaje' => ['required', 'numeric', 'between:0.01,100'],
            'estado' => ['required', Rule::in([Beca::ACTIVA, Beca::INACTIVA])],
            'institucion_financiadora' => ['nullable', 'string', 'max:200'],
            'requisitos' => ['nullable', 'string', 'max:1000'],
            'ofertas' => ['nullable', 'array'],
            'ofertas.*' => ['integer', 'distinct', Rule::exists('oferta_academica', 'id_oferta')],
        ]);
    }
}
