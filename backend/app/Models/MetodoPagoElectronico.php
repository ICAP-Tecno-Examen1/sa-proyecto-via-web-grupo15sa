<?php

namespace App\Models;

/**
 * Credenciales de PagoFácil de un método de pago (tabla metodo_pago_electronico).
 * Como en el proyecto de referencia: solo un método electrónico activo a la vez.
 */
class MetodoPagoElectronico extends Modelo
{
    protected $table = 'metodo_pago_electronico';

    protected $primaryKey = 'id_metodo_pago';

    public $incrementing = false;

    protected $casts = ['activo' => 'boolean'];

    protected $hidden = ['tc_token_secret'];

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago');
    }

    public function tieneCredenciales(): bool
    {
        return filled($this->tc_token_service) && filled($this->tc_token_secret);
    }
}
