<?php

namespace App\Models;

class MallaModulo extends Modelo
{
    protected $table = 'malla_modulo';

    protected $primaryKey = 'id_malla_modulo';

    protected $casts = [
        'obligatorio' => 'boolean',
        'orden' => 'integer',
    ];

    public function malla()
    {
        return $this->belongsTo(MallaCurricular::class, 'id_malla');
    }

    public function modulo()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo');
    }
}
