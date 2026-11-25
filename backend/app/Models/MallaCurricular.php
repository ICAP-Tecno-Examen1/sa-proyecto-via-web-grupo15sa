<?php

namespace App\Models;

class MallaCurricular extends Modelo
{
    protected $table = 'malla_curricular';

    protected $primaryKey = 'id_malla';

    public const ACTIVO = 'ACTIVO';
    public const INACTIVO = 'INACTIVO';

    protected $casts = [
        'fecha_vigencia' => 'date:Y-m-d',
        'horas_totales' => 'integer',
    ];

    public function programa()
    {
        return $this->belongsTo(Programa::class, 'id_programa');
    }

    public function mallaModulos()
    {
        return $this->hasMany(MallaModulo::class, 'id_malla')->orderBy('orden');
    }

    public function ofertas()
    {
        return $this->hasMany(OfertaAcademica::class, 'id_malla');
    }

    public function bloques()
    {
        return $this->hasMany(ModuloCarrera::class, 'id_malla')->orderBy('orden');
    }
}
