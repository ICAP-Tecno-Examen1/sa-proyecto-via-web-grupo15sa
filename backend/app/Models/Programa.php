<?php

namespace App\Models;

class Programa extends Modelo
{
    protected $table = 'programa';

    protected $primaryKey = 'id_programa';

    public const ACTIVO = 'ACTIVO';
    public const INACTIVO = 'INACTIVO';
    public const TECNICO_MEDIO = 'TECNICO_MEDIO';
    public const TECNICO_SUPERIOR = 'TECNICO_SUPERIOR';
    public const TIPOS = ['CURSO', 'TALLER', 'SEMINARIO', 'DIPLOMADO', 'TECNICO_MEDIO', 'TECNICO_SUPERIOR', 'PROGRAMA_TECNICO'];

    public function mallas()
    {
        return $this->hasMany(MallaCurricular::class, 'id_programa');
    }
}
