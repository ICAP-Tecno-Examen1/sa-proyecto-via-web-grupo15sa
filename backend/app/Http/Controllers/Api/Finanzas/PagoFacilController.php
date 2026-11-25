<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\PagoFacilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU4 · Webhook de PagoFácil (sin sesión): PagoFácil llama aquí cuando se confirma un pago QR.
 */
class PagoFacilController extends Controller
{
    public function __construct(private readonly PagoFacilService $pagofacil) {}

    public function callback(Request $request): JsonResponse
    {
        if (! $this->pagofacil->tokenValido($request->query('token'))) {
            Log::warning('PagoFácil: callback rechazado por token inválido', ['ip' => $request->ip()]);

            return response()->json(['error' => 1, 'status' => 0, 'message' => 'Token inválido', 'values' => false], 403);
        }

        try {
            Log::info('PagoFácil: callback recibido', ['datos' => $request->except('token')]);
            $resultado = $this->pagofacil->procesarCallback($request->all());

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
}
