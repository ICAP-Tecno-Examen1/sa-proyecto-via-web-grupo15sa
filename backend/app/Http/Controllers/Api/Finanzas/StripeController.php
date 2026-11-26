<?php

namespace App\Http\Controllers\Api\Finanzas;

use App\Http\Controllers\Controller;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU4 · Webhook de Stripe (sin sesión). Stripe lo llama al completarse un pago de Checkout.
 * Se autentica con la firma de la cabecera Stripe-Signature (secreto whsec_…), por eso
 * se necesita el cuerpo exacto tal como llegó.
 */
class StripeController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    public function webhook(Request $request): JsonResponse
    {
        try {
            $resultado = $this->stripe->procesarWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (Throwable $e) {
            // 500: Stripe reintentará el aviso más tarde.
            Log::error('Stripe: error procesando el webhook', ['error' => $e->getMessage()]);

            return response()->json(['recibido' => false, 'message' => 'Error al procesar el aviso de Stripe.'], 500);
        }

        if ($resultado['codigo'] !== 200) {
            Log::warning('Stripe: webhook rechazado', ['motivo' => $resultado['msg'], 'ip' => $request->ip()]);
        }

        return response()->json(['recibido' => $resultado['codigo'] === 200, 'message' => $resultado['msg']], $resultado['codigo']);
    }
}
