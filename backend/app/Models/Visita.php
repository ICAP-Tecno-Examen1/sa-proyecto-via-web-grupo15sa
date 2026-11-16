<?php

namespace App\Models;

class Visita extends Modelo
{
    protected $table = 'visita';

    protected $primaryKey = 'id_visita';

    protected $casts = [
        'fecha' => 'datetime:Y-m-d H:i:s',
    ];
}
