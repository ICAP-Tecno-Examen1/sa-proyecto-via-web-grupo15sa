<?php

namespace App\Models;

class InscripcionModulo extends Modelo
{
    protected $table = 'inscripcion_modulo';

    protected $primaryKey = 'id_inscripcion_modulo';

    public const INSCRITO = 'INSCRITO';

    public const APROBADO = 'APROBADO';

    public const REPROBADO = 'REPROBADO';

    public const RETIRADO = 'RETIRADO';

    protected $casts = [
        'nota_final' => 'float',
        'porcentaje_asistencia' => 'float',
    ];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function ofertaModulo()
    {
        return $this->belongsTo(OfertaModulo::class, 'id_oferta_modulo');
    }

    public function notas()
    {
        return $this->hasMany(Nota::class, 'id_inscripcion_modulo');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'id_inscripcion_modulo');
    }
}
