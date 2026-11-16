<?php

namespace App\Models;

class Modulo extends Modelo
{
    protected $table = 'modulo';

    protected $primaryKey = 'id_modulo';

    protected $casts = [
        'horas' => 'integer',
    ];

    public function mallaModulos()
    {
        return $this->hasMany(MallaModulo::class, 'id_modulo');
    }
}
