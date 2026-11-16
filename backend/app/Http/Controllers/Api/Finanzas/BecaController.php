<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Beca;
use App\Models\BecaEstudiante;
use App\Models\Matricula;
use App\Services\Auditoria;
use App\Services\MatriculaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CU4 · Becas y descuentos, y su asignación a matrículas.
 * Al asignar o revocar una beca se recalculan las cuotas aún no pagadas.
 */
class BecaController extends Controller
{
    public function __construct(private readonly MatriculaService $matriculas) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Beca::withCount(['asignaciones' => fn ($q) => $q->where('estado', BecaEstudiante::ACTIVA)])->orderBy('nombre')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $beca = Beca::create($this->validar($request));
        Auditoria::describir("Registró la beca «{$beca->nombre}»");

        return response()->json($beca, 201);
    }

    public function update(Request $request, Beca $beca): JsonResponse
    {
        $beca->update($this->validar($request));
        Auditoria::describir("Actualizó la beca «{$beca->nombre}»");

        return response()->json($beca);
    }

    public function destroy(Beca $beca): JsonResponse
    {
        abort_if($beca->asignaciones()->exists(), 422, 'La beca ya fue asignada a estudiantes y no se puede eliminar.');

        $beca->delete();
        Auditoria::describir("Eliminó la beca «{$beca->nombre}»");

        return response()->json(['message' => 'Beca eliminada.']);
    }

    /** Becas asignadas (con estudiante y programa). */
    public function asignaciones(): JsonResponse
    {
        return response()->json(
            BecaEstudiante::with(['beca', 'matricula' => fn ($q) => $q->with(MatriculaService::RELACIONES)])
                ->orderByDesc('id_beca_estudiante')->get()
                ->map(fn (BecaEstudiante $b) => [
                    ...$b->only(['id_beca_estudiante', 'porcentaje_aplicado', 'estado']),
                    'fecha_asignacion' => $b->fecha_asignacion?->format('Y-m-d'),
                    'beca' => $b->beca->nombre,
                    'matricula' => $this->matriculas->resumen($b->matricula),
                ])
        );
    }

    public function asignar(Request $request, Matricula $matricula): JsonResponse
    {
        $datos = $request->validate([
            'id_beca' => ['required', 'integer', Rule::exists('beca', 'id_beca')],
            'porcentaje_aplicado' => ['nullable', 'numeric', 'between:0.01,100'],
        ]);

        abort_unless(
            in_array($matricula->estado, [Matricula::PENDIENTE_PAGO, Matricula::ACTIVA], true),
            422,
            'Solo se asignan becas a matrículas pendientes de pago o activas.'
        );

        $beca = Beca::findOrFail($datos['id_beca']);

        abort_if(
            $matricula->becas()->where('id_beca', $beca->id_beca)->where('estado', BecaEstudiante::ACTIVA)->exists(),
            422,
            'La matrícula ya tiene asignada esta beca.'
        );

        $porcentaje = (float) ($datos['porcentaje_aplicado'] ?? $beca->porcentaje);
        $actual = (float) $matricula->becas()->where('estado', BecaEstudiante::ACTIVA)->sum('porcentaje_aplicado');

        abort_if($actual + $porcentaje > 100, 422, 'La suma de las becas de la matrícula no puede superar el 100%.');

        DB::transaction(function () use ($matricula, $beca, $porcentaje) {
            BecaEstudiante::create([
                'id_beca' => $beca->id_beca,
                'id_matricula' => $matricula->id_matricula,
                'porcentaje_aplicado' => $porcentaje,
                'estado' => BecaEstudiante::ACTIVA,
                'fecha_asignacion' => now()->toDateString(),
            ]);

            $this->matriculas->recalcularMontos($matricula);
        });

        Auditoria::describir("Asignó la beca «{$beca->nombre}» ({$porcentaje}%) a la matrícula {$matricula->numero_registro}");

        return response()->json(['message' => 'Beca asignada. Las cuotas pendientes fueron recalculadas.']);
    }

    public function revocar(BecaEstudiante $becaEstudiante): JsonResponse
    {
        abort_if($becaEstudiante->estado !== BecaEstudiante::ACTIVA, 422, 'La beca ya fue revocada.');

        DB::transaction(function () use ($becaEstudiante) {
            $becaEstudiante->update(['estado' => BecaEstudiante::REVOCADA]);
            $this->matriculas->recalcularMontos($becaEstudiante->matricula);
        });

        Auditoria::describir("Revocó una beca de la matrícula {$becaEstudiante->matricula->numero_registro}");

        return response()->json(['message' => 'Beca revocada. Las cuotas pendientes fueron recalculadas.']);
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'porcentaje' => ['required', 'numeric', 'between:0.01,100'],
            'institucion_financiadora' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
