<?php

namespace App\Models;

class Nota extends Modelo
{
    protected $table = 'nota';

    protected $primaryKey = 'id_nota';

    protected $casts = [
        'calificacion' => 'float',
        'fecha_registro' => 'datetime:Y-m-d H:i:s',
    ];

    public function inscripcion()
    {
        return $this->belongsTo(InscripcionModulo::class, 'id_inscripcion_modulo');
    }

    public function evaluacion()
    {
        return $this->belongsTo(Evaluacion::class, 'id_evaluacion');
    }

    public function correcciones()
    {
        return $this->hasMany(CorreccionNota::class, 'id_nota');
    }
}
