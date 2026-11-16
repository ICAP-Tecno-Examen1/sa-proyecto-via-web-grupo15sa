<?php

namespace App\Models;

class MetodoPago extends Modelo
{
    protected $table = 'metodo_pago';

    protected $primaryKey = 'id_metodo_pago';

    // EFECTIVO: lo registra el personal · COMPROBANTE: QR/transferencia con respaldo · PASARELA: QR PagoFácil
    public const EFECTIVO = 'EFECTIVO';
    public const COMPROBANTE = 'COMPROBANTE';
    public const PASARELA = 'PASARELA';
    public const TIPOS = [self::EFECTIVO, self::COMPROBANTE, self::PASARELA];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function pasarela()
    {
        return $this->hasOne(PasarelaPago::class, 'id_metodo_pago');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_metodo_pago');
    }
}
