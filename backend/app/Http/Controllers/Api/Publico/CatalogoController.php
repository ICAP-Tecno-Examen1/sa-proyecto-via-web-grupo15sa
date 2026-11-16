<?php

namespace App\Http\Controllers\Api\Publico;

use App\Http\Controllers\Controller;
use App\Models\OfertaAcademica;
use App\Models\Programa;
use App\Services\OfertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU2 · Catálogo público de programas (página web, sin iniciar sesión).
 * Solo muestra ofertas ABIERTAS: programa, costos, duración, modalidad y cupos.
 */
class CatalogoController extends Controller
{
    public function __construct(private readonly OfertaService $ofertas) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(Programa::TIPOS)],
            'modalidad' => ['nullable', Rule::in(OfertaAcademica::MODALIDADES)],
        ]);

        $ofertas = $this->ofertas->consulta()
            ->where('estado', OfertaAcademica::ABIERTA)
            ->when($f['modalidad'] ?? null, fn ($q, $m) => $q->where('modalidad', $m))
            ->when($f['tipo'] ?? null, fn ($q, $tipo) => $q->whereHas('malla.programa', fn ($p) => $p->where('tipo', $tipo)))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->whereHas('malla.programa', fn ($p) => $p->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(codigo) LIKE ?', [$patron])));
            })
            ->orderBy('fecha_inicio')->get()
            ->map(fn (OfertaAcademica $o) => $this->ofertas->resumen($o));

        return response()->json([
            'ofertas' => $ofertas,
            'tipos' => Programa::TIPOS,
            'modalidades' => OfertaAcademica::MODALIDADES,
        ]);
    }

    public function show(OfertaAcademica $oferta): JsonResponse
    {
        abort_unless(
            in_array($oferta->estado, [OfertaAcademica::ABIERTA, OfertaAcademica::EN_CURSO], true),
            404,
            'Esta oferta académica no está disponible.'
        );

        return response()->json([
            ...$this->ofertas->detalle($oferta),
            'documentos_requeridos' => array_values(config('icap.documentos_requeridos')),
        ]);
    }
}
