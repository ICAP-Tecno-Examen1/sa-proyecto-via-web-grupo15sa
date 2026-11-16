<?php

namespace App\Models;

/**
 * Configuración de la pasarela digital (PagoFácil) asociada a un método de pago.
 */
class PasarelaPago extends Modelo
{
    protected $table = 'pasarela_pago';

    protected $primaryKey = 'id_pasarela';

    public const SIMULADO = 'SIMULADO';
    public const REAL = 'REAL';

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $hidden = ['tc_token_service', 'tc_token_secret'];

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago');
    }

    public function tieneCredenciales(): bool
    {
        return filled($this->tc_token_service) && filled($this->tc_token_secret);
    }

    /** Opera contra PagoFácil solo si está en modo REAL y tiene credenciales. */
    public function esReal(): bool
    {
        return $this->modo === self::REAL && $this->tieneCredenciales();
    }
}
