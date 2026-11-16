<?php

namespace App\Models;

class Beca extends Modelo
{
    protected $table = 'beca';

    protected $primaryKey = 'id_beca';

    protected $casts = [
        'porcentaje' => 'float',
    ];

    public function asignaciones()
    {
        return $this->hasMany(BecaEstudiante::class, 'id_beca');
    }
}
