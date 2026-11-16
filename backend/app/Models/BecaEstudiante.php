<?php

namespace App\Models;

class BecaEstudiante extends Modelo
{
    protected $table = 'beca_estudiante';

    protected $primaryKey = 'id_beca_estudiante';

    public const ACTIVA = 'ACTIVA';
    public const REVOCADA = 'REVOCADA';

    protected $casts = [
        'porcentaje_aplicado' => 'float',
        'fecha_asignacion' => 'date:Y-m-d',
    ];

    public function beca()
    {
        return $this->belongsTo(Beca::class, 'id_beca');
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }
}
