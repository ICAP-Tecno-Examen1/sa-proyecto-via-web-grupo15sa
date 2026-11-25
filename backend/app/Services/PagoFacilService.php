<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Mensualidad;
use App\Models\MetodoPago;
use App\Models\MetodoPagoElectronico;
use App\Models\Pago;
use App\Models\Usuario;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CU4 · Pago electrónico con PagoFácil (API MasterQR v2), como en el proyecto de referencia del grupo:
 *   1. login         -> token de acceso (caché 23 h)
 *   2. generate-qr   -> QR para un número de pedido único
 *   3. el cliente consulta el estado cada 5 s
 *   4. PagoFácil avisa al webhook (callback) con Estado = 2 y se confirma el pago
 * Las credenciales están en metodo_pago_electronico (un solo método activo a la vez).
 */
class PagoFacilService
{
    private const CACHE_TOKEN = 'pagofacil_access_token_v2';

    /** En MasterQR el estado aprobado es 2; se aceptan también los textos por robustez. */
    private const ESTADOS_APROBADOS = ['2', 'APROBADO', 'APPROVED', 'COMPLETED', 'PAID', 'SUCCESS', 'PAGADO'];

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
     * Inicia el pago por QR de una cuota. Siempre emite un número de pedido nuevo,
     * así el QR puede regenerarse sin que PagoFácil lo rechace por duplicado.
     */
    public function iniciar(Mensualidad $cuota, array $datosPagador, ?string $parentesco, Usuario $actor): array
    {
        $config = $this->configuracion();

        if (! $config || ! $config->tieneCredenciales()) {
            throw new ReglaNegocioException('El pago con QR de PagoFácil no está configurado. Avise al Coordinador Financiero.');
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

            // Formato: ICAP-{idPago}-{timestamp}-{aleatorio}
            $pago->update(['numero_transaccion' => 'ICAP-'.$pago->id_pago.'-'.time().'-'.substr(md5(uniqid()), 0, 6)]);

            return $pago;
        });

        $qr = $this->generarQr($config, $pago, $cuota);

        if (! empty($qr['transaccion_id'])) {
            $pago->update(['pasarela_transaccion_id' => (string) $qr['transaccion_id']]);
        }

        $pago->update(['referencia' => $pago->numero_transaccion]);

        return ['pago' => $pago->refresh()->load(['pagador', 'metodoPago', 'mensualidad']), 'qr' => $qr];
    }

    private function generarQr(MetodoPagoElectronico $config, Pago $pago, Mensualidad $cuota): array
    {
        $estudiante = $pago->matricula->usuario;
        $pagador = $pago->pagador;

        try {
            $cuerpo = $this->cliente($config)->post('/generate-qr', [
                'paymentMethod' => 34,
                'clientName' => $pagador->nombre_completo,
                'documentType' => 1,
                'documentId' => $pagador->numero_documento ?: $estudiante->ci,
                'phoneNumber' => $pagador->telefono ?: ($estudiante->telefono ?: '0'),
                'email' => $pagador->correo ?: $estudiante->correo,
                'paymentNumber' => $pago->numero_transaccion,
                'amount' => (float) $pago->monto,
                'currency' => 2,
                'clientCode' => $estudiante->ci,
                'callbackUrl' => $this->urlCallback($config),
                'orderDetail' => [[
                    'serial' => 1,
                    'product' => 'Cuota '.$cuota->numero_cuota.' - Matrícula '.$pago->matricula->numero_registro,
                    'quantity' => 1,
                    'price' => (float) $pago->monto,
                    'discount' => 0,
                    'total' => (float) $pago->monto,
                ]],
            ])->json();
        } catch (Throwable $e) {
            Log::error('PagoFácil: error generando QR', ['pago' => $pago->id_pago, 'error' => $e->getMessage()]);

            throw new ReglaNegocioException('No se pudo conectar con PagoFácil para generar el QR. Intente nuevamente en unos minutos.', 502);
        }

        $valores = $cuerpo['values'] ?? null;

        if (! is_array($valores)) {
            Log::error('PagoFácil: respuesta inesperada al generar QR', ['respuesta' => $cuerpo]);

            throw new ReglaNegocioException('PagoFácil rechazó la generación del QR: '.($cuerpo['message'] ?? 'respuesta no válida').'.', 502);
        }

        $imagen = $valores['qrImage'] ?? ($valores['qrBase64'] ?? null);

        if ($imagen && ! str_starts_with($imagen, 'data:')) {
            $imagen = 'data:image/png;base64,'.$imagen;
        }

        return [
            'imagen' => $imagen,
            'transaccion_id' => $valores['transactionId'] ?? null,
            'numero_transaccion' => $pago->numero_transaccion,
            'expira' => $valores['expirationDate'] ?? null,
            'monto' => (float) $pago->monto,
        ];
    }

    /**
     * Estado del pago para el sondeo del cliente. El webhook es la fuente principal;
     * como respaldo se consulta la transacción a PagoFácil.
     */
    public function consultarEstado(Pago $pago): Pago
    {
        if ($pago->estado_conciliacion !== Pago::PENDIENTE || ! $pago->pasarela_transaccion_id) {
            return $pago;
        }

        $config = $this->configuracion();

        if (! $config || ! $config->tieneCredenciales()) {
            return $pago;
        }

        try {
            $cuerpo = $this->cliente($config)
                ->post('/query-transaction', ['transactionId' => (int) $pago->pasarela_transaccion_id])
                ->json();

            $valores = $cuerpo['values'] ?? [];
            $estado = $valores['paymentStatus'] ?? ($valores['status'] ?? ($valores['Estado'] ?? ($valores['estado'] ?? null)));

            if ($estado !== null && $this->estaAprobado($estado)) {
                return $this->pagos->confirmar($pago, null, 'Confirmado por consulta a PagoFácil.');
            }
        } catch (Throwable $e) {
            Log::warning('PagoFácil: no se pudo consultar la transacción', ['pago' => $pago->id_pago, 'error' => $e->getMessage()]);
        }

        return $pago;
    }

    /**
     * Procesa la notificación de PagoFácil (PedidoID + Estado). Es idempotente.
     *
     * @return array{ok: bool, msg: string}
     */
    public function procesarCallback(array $datos): array
    {
        $numero = $datos['PedidoID'] ?? ($datos['paymentNumber'] ?? null);
        $estado = $datos['Estado'] ?? ($datos['estado'] ?? '');

        if (! $numero) {
            return ['ok' => false, 'msg' => 'Número de pedido no recibido'];
        }

        if (! $this->estaAprobado($estado)) {
            return ['ok' => true, 'msg' => "Estado {$estado}: no se procesa"];
        }

        $pago = $this->buscarPorNumero((string) $numero);

        if (! $pago) {
            return ['ok' => false, 'msg' => "Pedido {$numero} no encontrado"];
        }

        if ($pago->estado_conciliacion === Pago::CONFIRMADO) {
            return ['ok' => true, 'msg' => "Pago {$pago->id_pago} ya estaba confirmado"];
        }

        if ($pago->estado_conciliacion !== Pago::PENDIENTE) {
            return ['ok' => false, 'msg' => "Pago {$pago->id_pago} no está pendiente"];
        }

        if (! empty($datos['transactionId'])) {
            $pago->update(['pasarela_transaccion_id' => (string) $datos['transactionId']]);
        }

        $this->pagos->confirmar($pago, null, 'Confirmado automáticamente por PagoFácil.');

        return ['ok' => true, 'msg' => "Pago {$pago->id_pago} confirmado"];
    }

    /** Valida el secreto que se anexó a la URL de notificación (?token=...). */
    public function tokenValido(?string $token): bool
    {
        $secreto = config('icap.pagofacil.webhook_secret');

        if (blank($secreto)) {
            Log::warning('PagoFácil: PAGO_FACIL_WEBHOOK_SECRET no está definido; el webhook no valida el origen.');

            return true;
        }

        return is_string($token) && hash_equals($secreto, $token);
    }

    /** Descarta el token de acceso guardado (cuando cambian las credenciales). */
    public static function olvidarToken(?string $tokenService): void
    {
        if (filled($tokenService)) {
            Cache::forget(self::claveToken($tokenService));
        }
    }

    private static function claveToken(string $tokenService): string
    {
        return self::CACHE_TOKEN.'_'.md5($tokenService);
    }

    private function cliente(MetodoPagoElectronico $config): PendingRequest
    {
        return Http::baseUrl(config('icap.pagofacil.url_base'))
            ->timeout(30)
            ->acceptJson()
            ->asJson()
            ->withToken($this->login($config));
    }

    private function login(MetodoPagoElectronico $config): string
    {
        $clave = self::claveToken($config->tc_token_service);

        if ($token = Cache::get($clave)) {
            return $token;
        }

        $cuerpo = Http::baseUrl(config('icap.pagofacil.url_base'))
            ->timeout(30)
            ->withHeaders([
                'Authorization' => 'Basic '.base64_encode($config->tc_token_service.':'.$config->tc_token_secret),
                'tctokenservice' => $config->tc_token_service,
                'tctokensecret' => $config->tc_token_secret,
            ])
            ->withBody('[]', 'application/json')
            ->post('/login')
            ->json();

        $token = $cuerpo['accessToken'] ?? ($cuerpo['values']['accessToken'] ?? null);

        if (! $token) {
            Log::error('PagoFácil: login fallido', ['respuesta' => $cuerpo]);

            throw new ReglaNegocioException('PagoFácil rechazó las credenciales configuradas.', 502);
        }

        Cache::put($clave, $token, now()->addHours(23));

        return $token;
    }

    /** URL de notificación que PagoFácil invocará, con el secreto anexado. */
    private function urlCallback(MetodoPagoElectronico $config): string
    {
        $url = $config->callback_url ?: (config('icap.pagofacil.callback_url') ?: url('/api/pagos/pagofacil/callback'));
        $secreto = config('icap.pagofacil.webhook_secret');

        if (filled($secreto) && ! str_contains($url, 'token=')) {
            $url .= (str_contains($url, '?') ? '&' : '?').'token='.$secreto;
        }

        return $url;
    }

    private function estaAprobado(mixed $estado): bool
    {
        return in_array(strtoupper(trim((string) $estado)), self::ESTADOS_APROBADOS, true);
    }

    /** Busca por el número exacto y, si el QR se regeneró, por el id incluido en el número. */
    private function buscarPorNumero(string $numero): ?Pago
    {
        $pago = Pago::where('numero_transaccion', $numero)->first();

        if (! $pago && preg_match('/^ICAP-(\d+)-/', $numero, $partes)) {
            $pago = Pago::whereKey((int) $partes[1])->whereNotNull('numero_transaccion')->first();
        }

        return $pago;
    }
}
