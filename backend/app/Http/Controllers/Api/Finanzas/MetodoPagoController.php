<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\PasarelaPago;
use App\Services\Auditoria;
use App\Services\PasarelaPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CU4 · Métodos de pago y configuración de la pasarela PagoFácil (requisito 10).
 *
 * Igual que en el proyecto de referencia, las credenciales de PagoFácil
 * (tcTokenService / tcTokenSecret) se administran desde aquí y se guardan en
 * la base de datos; nunca se devuelven completas al cliente.
 */
class MetodoPagoController extends Controller
{
    /** Métodos habilitados, para los formularios de pago. */
    public function disponibles(Request $request): JsonResponse
    {
        $puedeEfectivo = $request->user()->tienePermiso('pagos.registrar');

        return response()->json(
            MetodoPago::where('activo', true)->with('pasarela')->orderBy('id_metodo_pago')->get()
                ->filter(fn (MetodoPago $m) => $m->tipo !== MetodoPago::EFECTIVO || $puedeEfectivo)
                ->filter(fn (MetodoPago $m) => $m->tipo !== MetodoPago::PASARELA || $m->pasarela?->activo)
                ->map(fn (MetodoPago $m) => [
                    ...$m->only(['id_metodo_pago', 'nombre', 'descripcion', 'tipo']),
                    'modo_pasarela' => $m->tipo === MetodoPago::PASARELA ? ($m->pasarela->esReal() ? PasarelaPago::REAL : PasarelaPago::SIMULADO) : null,
                ])->values()
        );
    }

    public function index(): JsonResponse
    {
        return response()->json(
            MetodoPago::with('pasarela')->withCount('pagos')->orderBy('id_metodo_pago')->get()
                ->map(fn (MetodoPago $m) => $this->formato($m))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);

        $metodo = DB::transaction(function () use ($datos) {
            $metodo = MetodoPago::create($this->datosMetodo($datos));
            $this->guardarPasarela($metodo, $datos);

            return $metodo;
        });

        Auditoria::describir("Registró el método de pago «{$metodo->nombre}»");

        return response()->json($this->formato($metodo->load('pasarela')->loadCount('pagos')), 201);
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
            $this->guardarPasarela($metodoPago, $datos);
        });

        Auditoria::describir("Actualizó el método de pago «{$metodoPago->nombre}»");

        return response()->json($this->formato($metodoPago->refresh()->load('pasarela')->loadCount('pagos')));
    }

    public function cambiarEstado(MetodoPago $metodoPago): JsonResponse
    {
        $metodoPago->update(['activo' => ! $metodoPago->activo]);
        Auditoria::describir(($metodoPago->activo ? 'Activó' : 'Desactivó')." el método de pago «{$metodoPago->nombre}»");

        return response()->json($this->formato($metodoPago->load('pasarela')->loadCount('pagos')));
    }

    public function destroy(MetodoPago $metodoPago): JsonResponse
    {
        abort_if($metodoPago->pagos()->exists(), 422, 'No se puede eliminar: el método tiene pagos asociados. Puede desactivarlo.');

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
            // Solo para el tipo PASARELA
            'modo' => ['required_if:tipo,'.MetodoPago::PASARELA, 'nullable', Rule::in([PasarelaPago::SIMULADO, PasarelaPago::REAL])],
            'tc_token_service' => ['nullable', 'string', 'max:2000'],
            'tc_token_secret' => ['nullable', 'string', 'max:2000'],
            'callback_url' => ['nullable', 'url', 'max:500'],
        ]);
    }

    private function datosMetodo(array $datos): array
    {
        return array_intersect_key($datos, array_flip(['nombre', 'descripcion', 'tipo', 'activo']));
    }

    /** Crea, actualiza o elimina la configuración de pasarela según el tipo del método. */
    private function guardarPasarela(MetodoPago $metodo, array $datos): void
    {
        if ($metodo->tipo !== MetodoPago::PASARELA) {
            $metodo->pasarela()->delete();

            return;
        }

        $pasarela = $metodo->pasarela ?? new PasarelaPago(['id_metodo_pago' => $metodo->id_metodo_pago, 'proveedor' => 'PAGOFACIL']);
        $pasarela->modo = $datos['modo'] ?? PasarelaPago::SIMULADO;
        $pasarela->callback_url = $datos['callback_url'] ?? null;
        $pasarela->activo = true;

        // Las credenciales solo se reemplazan cuando el usuario escribe un valor nuevo.
        foreach (['tc_token_service', 'tc_token_secret'] as $campo) {
            if (filled($datos[$campo] ?? null)) {
                $pasarela->{$campo} = $datos[$campo];
            }
        }

        abort_if(
            $pasarela->modo === PasarelaPago::REAL && ! $pasarela->tieneCredenciales(),
            422,
            'Para usar el modo REAL debe cargar el Token Service y el Token Secret de PagoFácil.'
        );

        // Si cambian las credenciales, el token de acceso guardado deja de servir.
        PasarelaPagoService::olvidarToken($pasarela->getOriginal('tc_token_service'));
        PasarelaPagoService::olvidarToken($pasarela->tc_token_service);
        $pasarela->save();

        // Solo una pasarela activa a la vez: es la que usa el servicio de pagos por QR.
        PasarelaPago::where('id_pasarela', '<>', $pasarela->id_pasarela)->update(['activo' => false]);
    }

    private function formato(MetodoPago $m): array
    {
        return [
            ...$m->only(['id_metodo_pago', 'nombre', 'descripcion', 'tipo', 'activo', 'pagos_count']),
            'pasarela' => $m->pasarela ? [
                'proveedor' => $m->pasarela->proveedor,
                'modo' => $m->pasarela->modo,
                'modo_efectivo' => $m->pasarela->esReal() ? PasarelaPago::REAL : PasarelaPago::SIMULADO,
                'callback_url' => $m->pasarela->callback_url,
                'activo' => $m->pasarela->activo,
                'tiene_credenciales' => $m->pasarela->tieneCredenciales(),
            ] : null,
        ];
    }
}
