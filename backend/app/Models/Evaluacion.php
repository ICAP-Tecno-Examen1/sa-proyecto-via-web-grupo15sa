<?php

namespace App\Models;

class Evaluacion extends Modelo
{
    protected $table = 'evaluacion';

    protected $primaryKey = 'id_evaluacion';

    public const TIPOS = ['EXAMEN', 'PRACTICO', 'PROYECTO', 'PARTICIPACION', 'EXAMEN_FINAL'];

    protected $casts = [
        'ponderacion' => 'float',
        'fecha' => 'date:Y-m-d',
    ];

    public function ofertaModulo()
    {
        return $this->belongsTo(OfertaModulo::class, 'id_oferta_modulo');
    }

    public function notas()
    {
        return $this->hasMany(Nota::class, 'id_evaluacion');
    }
}
