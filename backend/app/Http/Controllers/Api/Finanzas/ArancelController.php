<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Arancel;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU4 · Aranceles: costo y modalidad de pago de cada oferta académica.
 *
 * Cada arancel es un PLAN de pago de la oferta:
 *   PAGO_UNICO   -> una sola cuota por el monto total
 *   MENSUALIDAD  -> el monto total dividido en numero_cuotas mensualidades
 */
class ArancelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['id_oferta' => ['nullable', 'integer']]);

        return response()->json(
            Arancel::with('oferta.malla.programa:id_programa,nombre')->withCount('mensualidades')
                ->when($f['id_oferta'] ?? null, fn ($q, $id) => $q->where('id_oferta', $id))
                ->orderByDesc('id_oferta')->orderBy('monto')->get()
                ->map(fn (Arancel $a) => [
                    ...$a->only(['id_arancel', 'id_oferta', 'concepto', 'monto', 'tipo_cobro', 'numero_cuotas', 'estado', 'mensualidades_count']),
                    'oferta' => $a->oferta->codigo,
                    'gestion' => $a->oferta->gestion,
                    'estado_oferta' => $a->oferta->estado,
                    'programa' => $a->oferta->malla->programa->nombre,
                ])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_oferta' => ['required', 'integer', Rule::exists('oferta_academica', 'id_oferta')],
            ...$this->reglas(),
        ]);

        $arancel = Arancel::create($this->normalizar($datos));
        Auditoria::describir("Configuró el arancel «{$arancel->concepto}»");

        return response()->json($arancel, 201);
    }

    public function update(Request $request, Arancel $arancel): JsonResponse
    {
        $datos = $this->normalizar($request->validate($this->reglas()));

        // Con cuotas ya generadas, el plan solo puede renombrarse o desactivarse.
        $cambiaPlan = (float) $datos['monto'] !== $arancel->monto
            || $datos['tipo_cobro'] !== $arancel->tipo_cobro
            || (int) $datos['numero_cuotas'] !== $arancel->numero_cuotas;

        abort_if(
            $cambiaPlan && $arancel->mensualidades()->exists(),
            422,
            'Este plan ya tiene matrículas con cuotas generadas: no se puede cambiar su monto ni sus cuotas. Desactívelo y cree un plan nuevo.'
        );

        $arancel->update($datos);
        Auditoria::describir("Actualizó el arancel «{$arancel->concepto}»");

        return response()->json($arancel);
    }

    public function destroy(Arancel $arancel): JsonResponse
    {
        abort_if($arancel->mensualidades()->exists(), 422, 'El plan ya fue utilizado en matrículas. Puede desactivarlo, pero no eliminarlo.');

        $arancel->delete();
        Auditoria::describir("Eliminó el arancel «{$arancel->concepto}»");

        return response()->json(['message' => 'Arancel eliminado.']);
    }

    private function reglas(): array
    {
        return [
            'concepto' => ['required', 'string', 'min:3', 'max:200'],
            'monto' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'tipo_cobro' => ['required', Rule::in(Arancel::TIPOS_COBRO)],
            'numero_cuotas' => ['required_if:tipo_cobro,'.Arancel::MENSUALIDAD, 'nullable', 'integer', 'between:1,36'],
            'estado' => ['required', Rule::in([Arancel::ACTIVO, Arancel::INACTIVO])],
        ];
    }

    private function normalizar(array $datos): array
    {
        if ($datos['tipo_cobro'] === Arancel::PAGO_UNICO) {
            $datos['numero_cuotas'] = 1;
        } elseif ((int) ($datos['numero_cuotas'] ?? 0) < 2) {
            abort(422, 'Un plan de mensualidades debe tener al menos 2 cuotas.');
        }

        return $datos;
    }
}
