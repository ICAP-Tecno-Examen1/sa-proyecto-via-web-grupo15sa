<?php

namespace App\Models;

class Programa extends Modelo
{
    protected $table = 'programa';

    protected $primaryKey = 'id_programa';

    public const ACTIVO = 'ACTIVO';
    public const INACTIVO = 'INACTIVO';
    public const TIPOS = ['CURSO', 'TALLER', 'DIPLOMADO', 'PROGRAMA_TECNICO', 'SEMINARIO'];

    public function mallas()
    {
        return $this->hasMany(MallaCurricular::class, 'id_programa');
    }
}
