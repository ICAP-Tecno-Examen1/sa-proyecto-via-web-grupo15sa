<?php

namespace App\Models;

/**
 * Credenciales de la pasarela en línea (Stripe) de un método de pago (tabla metodo_pago_electronico).
 * Solo un método electrónico activo a la vez. Si el método no tiene credenciales propias
 * se usan las del .env (STRIPE_SECRET_KEY / STRIPE_WEBHOOK_SECRET).
 *
 * Las columnas tc_token_* y callback_url son de PagoFácil y ya no se usan.
 */
class MetodoPagoElectronico extends Modelo
{
    protected $table = 'metodo_pago_electronico';

    protected $primaryKey = 'id_metodo_pago';

    public $incrementing = false;

    protected $casts = ['activo' => 'boolean'];

    // Los secretos nunca viajan al cliente.
    protected $hidden = ['stripe_clave_secreta', 'stripe_secreto_webhook', 'tc_token_service', 'tc_token_secret'];

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago');
    }

    /** Clave secreta de la API de Stripe (sk_test_… o sk_live_…). */
    public function claveSecreta(): ?string
    {
        return $this->stripe_clave_secreta ?: config('icap.stripe.clave_secreta');
    }

    /** Clave publicable (pk_test_… o pk_live_…): no es secreta, la usa el navegador. */
    public function clavePublica(): ?string
    {
        return $this->stripe_clave_publica ?: config('icap.stripe.clave_publica');
    }

    /** Con clave publicable el pago con tarjeta se muestra dentro del sistema. */
    public function pagoIntegrado(): bool
    {
        return filled($this->clavePublica());
    }

    /** Secreto con el que Stripe firma los webhooks (whsec_…). */
    public function secretoWebhook(): ?string
    {
        return $this->stripe_secreto_webhook ?: config('icap.stripe.secreto_webhook');
    }

    public function tieneCredenciales(): bool
    {
        return filled($this->claveSecreta());
    }

    /** PRUEBA con claves sk_test_ (no se cobra dinero real) o REAL con sk_live_. */
    public function modo(): ?string
    {
        $clave = (string) $this->claveSecreta();

        return match (true) {
            str_contains($clave, '_test_') => 'PRUEBA',
            str_contains($clave, '_live_') => 'REAL',
            default => null,
        };
    }
}
