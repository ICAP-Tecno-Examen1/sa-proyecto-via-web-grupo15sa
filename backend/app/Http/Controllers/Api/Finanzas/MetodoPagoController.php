<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\MetodoPagoElectronico;
use App\Services\Auditoria;
use App\Services\PagoFacilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CU4 · Métodos de pago y credenciales de PagoFácil (requisito 10), como en el proyecto de referencia.
 * Las credenciales (tcTokenService / tcTokenSecret) se guardan en metodo_pago_electronico y nunca
 * se devuelven al cliente. Solo un método electrónico queda activo a la vez.
 */
class MetodoPagoController extends Controller
{
    /** Métodos habilitados para pagar (PagoFácil solo si tiene credenciales activas). */
    public function disponibles(Request $request): JsonResponse
    {
        $puedeEfectivo = $request->user()->tienePermiso('pagos.registrar');

        return response()->json(
            MetodoPago::where('activo', true)->with('electronico')->orderBy('id_metodo_pago')->get()
                ->filter(fn (MetodoPago $m) => $m->tipo !== MetodoPago::EFECTIVO || $puedeEfectivo)
                ->filter(fn (MetodoPago $m) => $m->tipo !== MetodoPago::PASARELA || ($m->electronico?->activo && $m->electronico->tieneCredenciales()))
                ->map(fn (MetodoPago $m) => $m->only(['id_metodo_pago', 'nombre', 'descripcion', 'tipo']))
                ->values()
        );
    }

    public function index(): JsonResponse
    {
        return response()->json(
            MetodoPago::with('electronico')->withCount('pagos')->orderBy('id_metodo_pago')->get()
                ->map(fn (MetodoPago $m) => $this->formato($m))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $metodo = DB::transaction(function () use ($datos) {
            $metodo = MetodoPago::create($this->datosMetodo($datos));
            $this->guardarElectronico($metodo, $datos);

            return $metodo;
        });

        Auditoria::describir("Registró el método de pago «{$metodo->nombre}»");

        return response()->json($this->formato($metodo->load('electronico')->loadCount('pagos')), 201);
    }

    public function update(Request $request, MetodoPago $metodoPago): JsonResponse
    {
        $datos = $this->validar($request, $metodoPago);

        abort_if(
            $datos['tipo'] !== $metodoPago->tipo && $metodoPago->pagos()->exists(),
            422,
            'No se puede cambiar el tipo de un método que ya tiene pagos registrados.'
        );

        DB::transaction(function () use ($metodoPago, $datos) {
            $metodoPago->update($this->datosMetodo($datos));
            $this->guardarElectronico($metodoPago, $datos);
        });

        Auditoria::describir("Actualizó el método de pago «{$metodoPago->nombre}»");

        return response()->json($this->formato($metodoPago->refresh()->load('electronico')->loadCount('pagos')));
    }

    public function cambiarEstado(MetodoPago $metodoPago): JsonResponse
    {
        $metodoPago->update(['activo' => ! $metodoPago->activo]);
        Auditoria::describir(($metodoPago->activo ? 'Activó' : 'Desactivó')." el método de pago «{$metodoPago->nombre}»");

        return response()->json($this->formato($metodoPago->load('electronico')->loadCount('pagos')));
    }

    public function destroy(MetodoPago $metodoPago): JsonResponse
    {
        abort_if($metodoPago->pagos()->exists(), 422, 'No se puede eliminar: el método tiene pagos asociados. Puede desactivarlo.');

        $metodoPago->electronico()->delete();
        $metodoPago->delete();
        Auditoria::describir("Eliminó el método de pago «{$metodoPago->nombre}»");

        return response()->json(['message' => 'Método de pago eliminado.']);
    }

    // ------------------------------------------------------------------

    private function validar(Request $request, ?MetodoPago $metodo = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:100',
                Rule::unique('metodo_pago', 'nombre')->ignore($metodo?->id_metodo_pago, 'id_metodo_pago')],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['required', Rule::in(MetodoPago::TIPOS)],
            'activo' => ['required', 'boolean'],
            // Solo para el tipo PASARELA (PagoFácil)
            'tc_token_service' => ['nullable', 'string', 'max:2000'],
            'tc_token_secret' => ['nullable', 'string', 'max:2000'],
            'callback_url' => ['nullable', 'url', 'max:500'],
        ]);
    }

    private function datosMetodo(array $datos): array
    {
        return array_intersect_key($datos, array_flip(['nombre', 'descripcion', 'tipo', 'activo']));
    }

    /** Crea, actualiza o elimina las credenciales de PagoFácil según el tipo del método. */
    private function guardarElectronico(MetodoPago $metodo, array $datos): void
    {
        if ($metodo->tipo !== MetodoPago::PASARELA) {
            $metodo->electronico()->delete();

            return;
        }

        $electronico = MetodoPagoElectronico::firstOrNew(['id_metodo_pago' => $metodo->id_metodo_pago]);
        $electronico->callback_url = $datos['callback_url'] ?? null;

        // Las credenciales solo se reemplazan cuando se escribe un valor nuevo.
        foreach (['tc_token_service', 'tc_token_secret'] as $campo) {
            if (filled($datos[$campo] ?? null)) {
                PagoFacilService::olvidarToken($electronico->getOriginal($campo));
                $electronico->{$campo} = $datos[$campo];
            }
        }

        // Sin credenciales el método queda guardado pero no activo: no se ofrece al pagar.
        $electronico->activo = $electronico->tieneCredenciales();
        $electronico->save();

        // Solo un método electrónico activo a la vez: es el que usa el servicio de PagoFácil.
        if ($electronico->activo) {
            MetodoPagoElectronico::where('id_metodo_pago', '<>', $electronico->id_metodo_pago)->update(['activo' => false]);
        }
    }

    private function formato(MetodoPago $m): array
    {
        return [
            ...$m->only(['id_metodo_pago', 'nombre', 'descripcion', 'tipo', 'activo', 'pagos_count']),
            'electronico' => $m->electronico ? [
                'callback_url' => $m->electronico->callback_url,
                'activo' => $m->electronico->activo,
                'tiene_credenciales' => $m->electronico->tieneCredenciales(),
            ] : null,
        ];
    }
}
