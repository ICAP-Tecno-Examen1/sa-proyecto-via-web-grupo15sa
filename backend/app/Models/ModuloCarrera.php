<?php

namespace App\Models;

/**
 * Módulo de una carrera dentro de una malla: agrupa 1 o 2 materias (MODULO).
 */
class ModuloCarrera extends Modelo
{
    protected $table = 'modulo_carrera';

    protected $primaryKey = 'id_modulo_carrera';

    public const MAXIMO_MATERIAS = 2;

    public function malla()
    {
        return $this->belongsTo(MallaCurricular::class, 'id_malla');
    }

    public function materias()
    {
        return $this->hasMany(MallaModulo::class, 'id_modulo_carrera')->orderBy('orden');
    }
}
