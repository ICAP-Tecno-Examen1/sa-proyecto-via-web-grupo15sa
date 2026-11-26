<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\OfertaAcademica;
use App\Models\OfertaModulo;
use App\Services\Auditoria;
use App\Services\OfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU2 · Ofertas académicas: fechas, modalidad, cupos y módulos de cada oferta.
 */
class OfertaController extends Controller
{
    public function __construct(private readonly OfertaService $ofertas) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(OfertaAcademica::ESTADOS)],
            'gestion' => ['nullable', 'integer'],
            'id_programa' => ['nullable', 'integer'],
        ]);

        $ofertas = $this->ofertas->consulta()
            ->when($f['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($f['gestion'] ?? null, fn ($q, $gestion) => $q->where('gestion', $gestion))
            ->when($f['id_programa'] ?? null, fn ($q, $id) => $q->whereHas('malla', fn ($m) => $m->where('id_programa', $id)))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(codigo) LIKE ?', [$patron])
                    ->orWhereHas('malla.programa', fn ($p) => $p->whereRaw('LOWER(nombre) LIKE ?', [$patron])));
            })
            ->orderByDesc('gestion')->orderByDesc('fecha_inicio')->get()
            ->map(fn (OfertaAcademica $o) => $this->ofertas->resumen($o));

        return response()->json($ofertas);
    }

    public function show(Request $request, OfertaAcademica $oferta): JsonResponse
    {
        // Los honorarios solo se muestran a quien administra ofertas o docentes.
        $conContratos = $request->user()->tieneAlgunPermiso(['ofertas.gestionar', 'docentes.asignar', 'academico.supervisar']);

        return response()->json($this->ofertas->detalle($oferta, $conContratos));
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_malla' => ['required', 'integer', Rule::exists('malla_curricular', 'id_malla')],
            ...$this->reglas(),
        ]);

        $oferta = $this->ofertas->crear($datos);
        Auditoria::describir("Creó la oferta académica {$oferta->codigo}");

        return response()->json($this->ofertas->detalle($oferta, true), 201);
    }

    public function update(Request $request, OfertaAcademica $oferta): JsonResponse
    {
        $oferta = $this->ofertas->actualizar($oferta, $request->validate($this->reglas($oferta)));
        Auditoria::describir("Actualizó la oferta académica {$oferta->codigo}");

        return response()->json($this->ofertas->detalle($oferta, true));
    }

    public function cambiarEstado(Request $request, OfertaAcademica $oferta): JsonResponse
    {
        $datos = $request->validate(['estado' => ['required', Rule::in(OfertaAcademica::ESTADOS)]]);

        // Finalizar = cerrar el curso: finaliza a todos los estudiantes y resume quién aprobó
        if ($datos['estado'] === OfertaAcademica::FINALIZADA) {
            $resumen = $this->ofertas->finalizarCurso($oferta);
            Auditoria::describir("Finalizó el curso {$oferta->codigo}: {$resumen['finalizados']} estudiantes, ".count($resumen['aprobados']).' aprobados');

            return response()->json([...$this->ofertas->detalle($oferta->refresh(), true), 'resumen_finalizacion' => $resumen]);
        }

        $this->ofertas->cambiarEstado($oferta, $datos['estado']);
        Auditoria::describir("Cambió la oferta {$oferta->codigo} a estado {$datos['estado']}");

        return response()->json($this->ofertas->detalle($oferta, true));
    }

    /** Fechas en las que se dicta un módulo dentro de la oferta. */
    public function actualizarModulo(Request $request, OfertaModulo $ofertaModulo): JsonResponse
    {
        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
        ]);

        $ofertaModulo->update($datos);
        Auditoria::describir('Actualizó las fechas de un módulo de la oferta');

        return response()->json($this->ofertas->detalle($ofertaModulo->oferta, true));
    }

    private function reglas(?OfertaAcademica $oferta = null): array
    {
        return [
            'codigo' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('oferta_academica', 'codigo')->ignore($oferta?->id_oferta, 'id_oferta')],
            'modalidad' => ['required', Rule::in(OfertaAcademica::MODALIDADES)],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'cupo_maximo' => ['required', 'integer', 'between:1,1000'],
            'cupo_minimo' => ['required', 'integer', 'min:0', 'lte:cupo_maximo'],
            'gestion' => ['required', 'integer', 'between:2000,2100'],
        ];
    }
}
