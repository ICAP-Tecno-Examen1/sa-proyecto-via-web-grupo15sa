<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\PasarelaPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU4 · Extremos públicos de la pasarela: webhook de PagoFácil y banco simulado.
 * No llevan sesión: los llama la pasarela (o la página pública del QR simulado).
 */
class PasarelaController extends Controller
{
    public function __construct(private readonly PasarelaPagoService $pasarela) {}

    /**
     * Webhook de confirmación. Responde con el formato que espera PagoFácil
     * (error / status / message / values). Rechaza con 403 si el token de la
     * URL no coincide con PAGO_FACIL_WEBHOOK_SECRET.
     */
    public function callback(Request $request): JsonResponse
    {
        if (! $this->pasarela->tokenValido($request->query('token'))) {
            Log::warning('PagoFácil: callback rechazado por token inválido', ['ip' => $request->ip()]);

            return response()->json(['error' => 1, 'status' => 0, 'message' => 'Token inválido', 'values' => false], 403);
        }

        try {
            Log::info('PagoFácil: callback recibido', ['datos' => $request->except('token')]);
            $resultado = $this->pasarela->procesarCallback($request->all());

            return response()->json([
                'error' => $resultado['ok'] ? 0 : 1,
                'status' => $resultado['ok'] ? 1 : 0,
                'message' => $resultado['msg'],
                'values' => $resultado['ok'],
            ]);
        } catch (Throwable $e) {
            Log::error('PagoFácil: error procesando el callback', ['error' => $e->getMessage()]);

            return response()->json(['error' => 1, 'status' => 0, 'message' => $e->getMessage(), 'values' => false]);
        }
    }

    /** Orden de pago que muestra la página "banco simulado". */
    public function simulada(string $numero): JsonResponse
    {
        $pago = $this->pasarela->pagoSimulado($numero);

        return response()->json([
            'numero_transaccion' => $pago->numero_transaccion,
            'monto' => $pago->monto,
            'estado' => $pago->estado_conciliacion,
            'concepto' => 'Cuota '.($pago->mensualidad?->numero_cuota ?? '-').' · '.$pago->matricula->oferta->malla->programa->nombre,
            'matricula' => $pago->matricula->numero_registro,
            'pagador' => $pago->pagador->nombre_completo,
        ]);
    }

    public function resolverSimulada(Request $request, string $numero): JsonResponse
    {
        $datos = $request->validate(['aprobar' => ['required', 'boolean']]);

        return response()->json($this->pasarela->resolverSimulado($numero, (bool) $datos['aprobar']));
    }
}
