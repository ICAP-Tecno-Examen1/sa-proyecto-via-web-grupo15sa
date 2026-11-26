<?php

namespace App\Models;

class Arancel extends Modelo
{
    protected $table = 'arancel';

    protected $primaryKey = 'id_arancel';

    public const PAGO_UNICO = 'PAGO_UNICO';

    public const MENSUALIDAD = 'MENSUALIDAD';

    public const TIPOS_COBRO = [self::PAGO_UNICO, self::MENSUALIDAD];

    public const ACTIVO = 'ACTIVO';

    public const INACTIVO = 'INACTIVO';

    protected $casts = [
        'monto' => 'float',
        'numero_cuotas' => 'integer',
    ];

    public function oferta()
    {
        return $this->belongsTo(OfertaAcademica::class, 'id_oferta');
    }

    public function mensualidades()
    {
        return $this->hasMany(Mensualidad::class, 'id_arancel');
    }
}
