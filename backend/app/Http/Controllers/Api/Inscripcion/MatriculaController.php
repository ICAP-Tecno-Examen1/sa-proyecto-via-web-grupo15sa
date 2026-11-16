<?php

namespace App\Http\Controllers\Api\Inscripcion;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\InscripcionModulo;
use App\Models\Matricula;
use App\Services\Auditoria;
use App\Services\EvaluacionService;
use App\Services\MatriculaService;
use App\Services\OfertaService;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU3 · Matrículas, inscripción a módulos y seguimiento académico.
 */
class MatriculaController extends Controller
{
    public function __construct(
        private readonly MatriculaService $matriculas,
        private readonly PagoService $pagos,
        private readonly EvaluacionService $evaluaciones,
        private readonly OfertaService $ofertas,
    ) {}

    // ------------------------------------------------------------------
    // Personal del ICAP
    // ------------------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(Matricula::ESTADOS)],
            'id_oferta' => ['nullable', 'integer'],
            'gestion' => ['nullable', 'integer'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $pagina = Matricula::with(MatriculaService::RELACIONES)
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($f['id_oferta'] ?? null, fn ($q, $id) => $q->where('id_oferta', $id))
            ->when($f['gestion'] ?? null, fn ($q, $g) => $q->whereHas('oferta', fn ($o) => $o->where('gestion', $g)))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(numero_registro) LIKE ?', [$patron])
                    ->orWhereHas('usuario', fn ($u) => $u->where(fn ($x) => $x
                        ->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])->orWhereRaw('LOWER(ci) LIKE ?', [$patron]))));
            })
            ->orderByDesc('id_matricula')
            ->paginate($f['por_pagina'] ?? 15);

        $pagina->getCollection()->transform(fn (Matricula $m) => $this->matriculas->resumen($m));

        return response()->json($pagina);
    }

    public function show(Matricula $matricula): JsonResponse
    {
        return response()->json($this->detalle($matricula));
    }

    /** Coordinador Académico: inscribe al estudiante en módulos de la oferta. */
    public function inscribir(Request $request, Matricula $matricula): JsonResponse
    {
        $datos = $request->validate([
            'modulos_oferta' => ['required', 'array', 'min:1'],
            'modulos_oferta.*' => ['integer', 'distinct', Rule::exists('oferta_modulo', 'id_oferta_modulo')],
        ]);

        $this->matriculas->inscribirModulos($matricula, $datos['modulos_oferta']);
        Auditoria::describir("Inscribió a módulos la matrícula {$matricula->numero_registro}");

        return response()->json($this->detalle($matricula));
    }

    public function retirarInscripcion(InscripcionModulo $inscripcion): JsonResponse
    {
        $matricula = $inscripcion->matricula;
        $this->matriculas->retirarInscripcion($inscripcion);
        Auditoria::describir("Retiró una inscripción de la matrícula {$matricula->numero_registro}");

        return response()->json($this->detalle($matricula));
    }

    /** Coordinador Académico: el estudiante concluyó todos sus módulos. */
    public function finalizar(Matricula $matricula): JsonResponse
    {
        $this->matriculas->finalizar($matricula);
        Auditoria::describir("Finalizó el programa de la matrícula {$matricula->numero_registro}");

        return response()->json($this->detalle($matricula));
    }

    public function anular(Matricula $matricula): JsonResponse
    {
        $this->matriculas->anular($matricula);
        Auditoria::describir("Anuló la matrícula {$matricula->numero_registro}");

        return response()->json($this->detalle($matricula));
    }

    // ------------------------------------------------------------------
    // Estudiante
    // ------------------------------------------------------------------

    /** Mis programas: estado, avance académico y situación de pagos. */
    public function mias(Request $request): JsonResponse
    {
        $matriculas = Matricula::where('id_usuario', $request->user()->id_usuario)
            ->with(MatriculaService::RELACIONES)->orderByDesc('id_matricula')->get()
            ->map(fn (Matricula $m) => [
                ...$this->matriculas->resumen($m),
                'progreso' => $this->matriculas->progreso($m, $this->evaluaciones)['resumen'],
                'cuenta' => $this->pagos->estadoCuenta($m)['resumen'],
            ]);

        return response()->json($matriculas);
    }

    public function mia(Request $request, Matricula $matricula): JsonResponse
    {
        abort_unless($matricula->id_usuario === $request->user()->id_usuario, 403, 'Esta matrícula no le pertenece.');

        return response()->json($this->detalle($matricula));
    }

    // ------------------------------------------------------------------

    /** Ficha completa: datos, estado de cuenta, avance, becas y certificado. */
    private function detalle(Matricula $matricula): array
    {
        $matricula->refresh()->load([...MatriculaService::RELACIONES, 'becas.beca', 'validaciones.validador:id_usuario,nombres,apellidos']);
        $inscritos = $matricula->inscripciones()->where('estado', '<>', InscripcionModulo::RETIRADO)->pluck('id_oferta_modulo');

        return [
            ...$this->matriculas->resumen($matricula),
            'cuenta' => $this->pagos->estadoCuenta($matricula),
            'progreso' => $this->matriculas->progreso($matricula, $this->evaluaciones),
            'becas' => $matricula->becas,
            'validaciones' => $matricula->validaciones,
            'certificado' => $matricula->certificados()->where('estado', Certificado::EMITIDO)->first(),
            // Módulos de la oferta en los que aún no está inscrito.
            'modulos_disponibles' => collect($this->ofertas->detalle($matricula->oferta)['modulos'])
                ->reject(fn ($m) => $inscritos->contains($m['id_oferta_modulo']))->values(),
        ];
    }
}
