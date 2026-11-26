<?php

namespace App\Models;

class OfertaModulo extends Modelo
{
    protected $table = 'oferta_modulo';

    protected $primaryKey = 'id_oferta_modulo';

    public const ACTIVO = 'ACTIVO';

    public const CERRADO = 'CERRADO';

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    public function oferta()
    {
        return $this->belongsTo(OfertaAcademica::class, 'id_oferta');
    }

    public function mallaModulo()
    {
        return $this->belongsTo(MallaModulo::class, 'id_malla_modulo');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionDocente::class, 'id_oferta_modulo');
    }

    public function inscripciones()
    {
        return $this->hasMany(InscripcionModulo::class, 'id_oferta_modulo');
    }

    public function evaluaciones()
    {
        return $this->hasMany(Evaluacion::class, 'id_oferta_modulo')->orderBy('fecha')->orderBy('id_evaluacion');
    }

    public function estaCerrado(): bool
    {
        return $this->estado === self::CERRADO;
    }
}
