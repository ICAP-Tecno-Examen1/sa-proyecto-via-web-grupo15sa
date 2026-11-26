<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Mensualidad;
use App\Models\MetodoPago;
use App\Models\MetodoPagoElectronico;
use App\Models\Pago;
use App\Models\Usuario;
use App\Support\Frontend;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU4 · Pago en línea con tarjeta mediante Stripe Checkout (página de pago alojada por Stripe).
 *
 *   1. iniciar()            crea el pago PENDIENTE y una sesión de Checkout; el cliente
 *                           redirige al estudiante a la URL de Stripe.
 *   2. Stripe cobra y vuelve a /pago/resultado del cliente React.
 *   3. Confirmación (lo que llegue primero; ambas son idempotentes):
 *        - webhook firmado  checkout.session.completed  → procesarWebhook()
 *        - consulta directa de la sesión mientras el cliente espera → consultarEstado()
 *   4. PagoService::confirmar() dispara la cascada (cuota pagada, matrícula activa, recibo).
 *
 * Se usa la API REST de Stripe con el cliente HTTP de Laravel (sin SDK), así las
 * pruebas la simulan con Http::fake. Las credenciales están en metodo_pago_electronico.
 */
class StripeService
{
    private const EVENTOS_PAGADO = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];

    public function __construct(private readonly PagoService $pagos) {}

    /** Método electrónico activo, de tipo PASARELA. */
    public function configuracion(): ?MetodoPagoElectronico
    {
        return MetodoPagoElectronico::where('activo', true)
            ->whereHas('metodoPago', fn ($q) => $q->where('activo', true)->where('tipo', MetodoPago::PASARELA))
            ->with('metodoPago')
            ->first();
    }

    /**
     * Inicia el pago en línea de una cuota y devuelve la URL de Stripe Checkout.
     * Si ya había un intento pendiente se reutiliza ese pago con una sesión nueva.
     */
    public function iniciar(Mensualidad $cuota, array $datosPagador, ?string $parentesco, Usuario $actor): array
    {
        $config = $this->configuracion();

        if (! $config || ! $config->tieneCredenciales()) {
            throw new ReglaNegocioException('El pago con tarjeta (Stripe) no está configurado. Avise al Coordinador Financiero.');
        }

        $matricula = $this->pagos->exigirCuotaPagable($cuota);
        $monto = $cuota->saldo();

        $pago = DB::transaction(function () use ($cuota, $matricula, $config, $datosPagador, $parentesco, $actor, $monto) {
            $pagador = $this->pagos->resolverPagador($datosPagador);

            $pago = Pago::where('id_mensualidad', $cuota->id_mensualidad)
                ->where('id_metodo_pago', $config->id_metodo_pago)
                ->where('estado_conciliacion', Pago::PENDIENTE)
                ->lockForUpdate()->first();

            $datos = [
                'id_pagador' => $pagador->id_pagador,
                'monto' => $monto,
                'fecha' => now(),
                'parentesco_pagador' => $parentesco,
                'id_usuario_registro' => $actor->id_usuario,
            ];

            $pago = $pago
                ? tap($pago)->update($datos)
                : Pago::create([
                    ...$datos,
                    'id_matricula' => $matricula->id_matricula,
                    'id_arancel' => $cuota->id_arancel,
                    'id_mensualidad' => $cuota->id_mensualidad,
                    'id_metodo_pago' => $config->id_metodo_pago,
                    'estado_conciliacion' => Pago::PENDIENTE,
                ]);

            // Número propio de cada intento: ICAP-{idPago}-{timestamp}-{aleatorio}
            $numero = 'ICAP-'.$pago->id_pago.'-'.time().'-'.substr(md5(uniqid()), 0, 6);
            $pago->update(['numero_transaccion' => $numero, 'referencia' => $numero]);

            return $pago;
        });

        $sesion = $this->crearSesion($config, $pago, $cuota);
        $pago->update(['pasarela_transaccion_id' => $sesion['id']]);

        return [
            'pago' => $pago->refresh()->load(['pagador', 'metodoPago', 'mensualidad']),
            'pasarela' => [
                // integrado: el cliente muestra el formulario con client_secret + clave_publica
                // redireccion: el cliente envía al estudiante a la url de Stripe
                'presentacion' => $config->pagoIntegrado() ? 'integrado' : 'redireccion',
                'client_secret' => $sesion['client_secret'] ?? null,
                'clave_publica' => $config->pagoIntegrado() ? $config->clavePublica() : null,
                'url' => $sesion['url'] ?? null,
                'sesion' => $sesion['id'],
                'monto' => (float) $pago->monto,
                'moneda' => strtoupper(config('icap.stripe.moneda')),
                'modo' => $config->modo(),
            ],
        ];
    }

    /** Crea la sesión de Stripe Checkout para el monto del pago. */
    private function crearSesion(MetodoPagoElectronico $config, Pago $pago, Mensualidad $cuota): array
    {
        $matricula = $pago->matricula;
        $estudiante = $matricula->usuario;
        $programa = $matricula->oferta?->malla?->programa?->nombre ?? 'Programa ICAP';
        $correo = filter_var($pago->pagador->correo ?: $estudiante->correo, FILTER_VALIDATE_EMAIL) ?: null;
        $retorno = fn (string $estado) => Frontend::url("/pago/resultado?pago={$pago->id_pago}&estado={$estado}");
        $metadatos = ['id_pago' => $pago->id_pago, 'numero_transaccion' => $pago->numero_transaccion, 'matricula' => $matricula->numero_registro];

        $parametros = [
            'mode' => 'payment',
            'locale' => 'es',
            // Solo tarjeta (incluye Apple Pay / Link, que usan tarjeta). En bolivianos Stripe no ofrece
            // QR ni efectivo: esos se pagan con «Transferencia o QR bancario» o en caja («Efectivo»).
            'allowed_payment_method_types' => ['card'],
            'client_reference_id' => $pago->numero_transaccion,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => config('icap.stripe.moneda'),
                    // Stripe trabaja en centavos: Bs. 233,33 → 23333
                    'unit_amount' => (int) round((float) $pago->monto * 100),
                    'product_data' => [
                        'name' => "Cuota {$cuota->numero_cuota} · {$programa}",
                        'description' => "Matrícula {$matricula->numero_registro} · {$estudiante->nombre_completo}",
                    ],
                ],
            ]],
            'metadata' => $metadatos,
            'payment_intent_data' => ['metadata' => $metadatos],
        ];

        if ($correo) {
            $parametros['customer_email'] = $correo;
        }

        if ($config->pagoIntegrado()) {
            // Formulario de tarjeta dentro del sistema (Embedded Checkout). Al terminar no se
            // redirige: el cliente React recibe el aviso y espera la confirmación ahí mismo.
            $parametros['ui_mode'] = 'embedded_page';
            $parametros['redirect_on_completion'] = 'never';
        } else {
            // Sin clave publicable: página de Stripe y regreso a /pago/resultado.
            $parametros['success_url'] = $retorno('exito');
            $parametros['cancel_url'] = $retorno('cancelado');
        }

        $respuesta = $this->llamar(fn () => $this->cliente($config)
            ->withHeaders(['Idempotency-Key' => $pago->numero_transaccion])
            ->post('/checkout/sessions', $parametros), 'crear la sesión de pago');

        if (! $respuesta->json('id') || (! $respuesta->json('url') && ! $respuesta->json('client_secret'))) {
            Log::error('Stripe: respuesta inesperada al crear la sesión', ['pago' => $pago->id_pago, 'respuesta' => $respuesta->json()]);

            throw new ReglaNegocioException('Stripe no devolvió la página de pago. Intente nuevamente.', 502);
        }

        return $respuesta->json();
    }

    /**
     * Estado del pago mientras el estudiante espera en /pago/resultado. El webhook es
     * la vía principal; como respaldo (por ejemplo en desarrollo, sin webhook público)
     * se consulta la sesión directamente a Stripe.
     */
    public function consultarEstado(Pago $pago): Pago
    {
        if ($pago->estado_conciliacion !== Pago::PENDIENTE || ! str_starts_with((string) $pago->pasarela_transaccion_id, 'cs_')) {
            return $pago;
        }

        $config = $this->configuracion();

        if (! $config || ! $config->tieneCredenciales()) {
            return $pago;
        }

        try {
            $sesion = $this->cliente($config)->get('/checkout/sessions/'.$pago->pasarela_transaccion_id)->throw()->json();

            if (($sesion['payment_status'] ?? null) === 'paid') {
                return $this->pagos->confirmar($pago, null, $this->observacion('Confirmado por consulta a Stripe', $sesion));
            }
        } catch (Throwable $e) {
            Log::warning('Stripe: no se pudo consultar la sesión', ['pago' => $pago->id_pago, 'error' => $e->getMessage()]);
        }

        return $pago;
    }

    /**
     * Procesa un webhook de Stripe. Verifica la firma (cabecera Stripe-Signature) con el
     * secreto whsec_… y confirma el pago de forma idempotente.
     *
     * @return array{codigo: int, msg: string}
     */
    public function procesarWebhook(string $cuerpo, ?string $firma): array
    {
        $secreto = $this->configuracion()?->secretoWebhook() ?? config('icap.stripe.secreto_webhook');

        if (blank($secreto)) {
            Log::warning('Stripe: llegó un webhook pero no hay secreto de firma (whsec_…) configurado.');

            return ['codigo' => 400, 'msg' => 'Webhook sin secreto configurado'];
        }

        if (! self::firmaValida($cuerpo, $firma, $secreto)) {
            return ['codigo' => 400, 'msg' => 'Firma inválida'];
        }

        $evento = json_decode($cuerpo, true) ?? [];
        $tipo = $evento['type'] ?? '';
        $sesion = $evento['data']['object'] ?? [];

        if (! in_array($tipo, self::EVENTOS_PAGADO, true)) {
            return ['codigo' => 200, 'msg' => "Evento {$tipo}: no se procesa"];
        }

        if (($sesion['payment_status'] ?? null) !== 'paid') {
            return ['codigo' => 200, 'msg' => 'La sesión aún no está pagada'];
        }

        $pago = $this->buscarPago($sesion);

        // Se responde 200 en todos estos casos para que Stripe no reintente sin sentido.
        if (! $pago) {
            return ['codigo' => 200, 'msg' => 'Pago no encontrado en este sistema'];
        }

        if ($pago->estado_conciliacion === Pago::CONFIRMADO) {
            return ['codigo' => 200, 'msg' => "Pago {$pago->id_pago} ya estaba confirmado"];
        }

        if ($pago->estado_conciliacion !== Pago::PENDIENTE) {
            return ['codigo' => 200, 'msg' => "Pago {$pago->id_pago} no está pendiente"];
        }

        $this->pagos->confirmar($pago, null, $this->observacion('Confirmado automáticamente por Stripe', $sesion));

        return ['codigo' => 200, 'msg' => "Pago {$pago->id_pago} confirmado"];
    }

    /**
     * Verifica la firma de Stripe: cabecera "t=…,v1=…", HMAC-SHA256 de "{t}.{cuerpo}"
     * con el secreto del webhook, y que no tenga más de 5 minutos (evita reenvíos).
     */
    public static function firmaValida(string $cuerpo, ?string $cabecera, string $secreto, ?int $ahora = null): bool
    {
        $marca = null;
        $firmas = [];

        foreach (explode(',', (string) $cabecera) as $parte) {
            [$clave, $valor] = array_pad(explode('=', trim($parte), 2), 2, null);

            if ($clave === 't') {
                $marca = (int) $valor;
            } elseif ($clave === 'v1' && $valor) {
                $firmas[] = $valor;
            }
        }

        if (! $marca || ! $firmas || abs(($ahora ?? time()) - $marca) > config('icap.stripe.tolerancia_firma', 300)) {
            return false;
        }

        $esperada = hash_hmac('sha256', "{$marca}.{$cuerpo}", $secreto);

        foreach ($firmas as $firma) {
            if (hash_equals($esperada, $firma)) {
                return true;
            }
        }

        return false;
    }

    /** Ubica el pago por los metadatos, la referencia o el id de la sesión. */
    private function buscarPago(array $sesion): ?Pago
    {
        $id = $sesion['metadata']['id_pago'] ?? null;

        if ($id && ($pago = Pago::find((int) $id))) {
            return $pago;
        }

        return Pago::where('numero_transaccion', $sesion['client_reference_id'] ?? '-')
            ->orWhere('pasarela_transaccion_id', $sesion['id'] ?? '-')
            ->first();
    }

    private function observacion(string $texto, array $sesion): string
    {
        $intento = $sesion['payment_intent'] ?? null;

        return $intento ? "{$texto} (pago {$intento})." : "{$texto}.";
    }

    private function cliente(MetodoPagoElectronico $config): PendingRequest
    {
        return Http::baseUrl(config('icap.stripe.url_base'))
            ->timeout(20)
            ->acceptJson()
            ->asForm()
            ->withToken($config->claveSecreta());
    }

    /** Ejecuta una llamada a Stripe y traduce sus errores a un mensaje en español. */
    private function llamar(callable $peticion, string $accion): Response
    {
        try {
            $respuesta = $peticion();
        } catch (Throwable $e) {
            Log::error("Stripe: no se pudo {$accion}", ['error' => $e->getMessage()]);

            throw new ReglaNegocioException('No se pudo conectar con Stripe. Revise la conexión a internet e intente nuevamente.', 502);
        }

        if ($respuesta->failed()) {
            $mensaje = $respuesta->json('error.message') ?? 'error desconocido';
            Log::error("Stripe: no se pudo {$accion}", ['estado' => $respuesta->status(), 'error' => $respuesta->json('error')]);

            throw new ReglaNegocioException(
                $respuesta->status() === 401
                    ? 'Stripe rechazó la clave secreta configurada. Revise las credenciales en Métodos de pago.'
                    : "Stripe rechazó la operación: {$mensaje}",
                502,
            );
        }

        return $respuesta;
    }
}
