<?php

namespace App\Models;

/**
 * Acumulado de visitas por página. Lo mantiene el trigger trg_visita_contador.
 */
class ContadorVisita extends Modelo
{
    protected $table = 'contador_visita';

    protected $primaryKey = 'ruta';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'total' => 'integer',
        'ultima_visita' => 'datetime:Y-m-d H:i:s',
    ];
}
