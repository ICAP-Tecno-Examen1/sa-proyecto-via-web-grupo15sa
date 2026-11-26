<?php

namespace App\Models;

class OfertaAcademica extends Modelo
{
    protected $table = 'oferta_academica';

    protected $primaryKey = 'id_oferta';

    // PLANIFICADA: en preparación (sin costos) · ABIERTA: recibe preinscripciones
    public const PLANIFICADA = 'PLANIFICADA';

    public const ABIERTA = 'ABIERTA';

    public const EN_CURSO = 'EN_CURSO';

    public const FINALIZADA = 'FINALIZADA';

    public const CANCELADA = 'CANCELADA';

    public const ESTADOS = [self::PLANIFICADA, self::ABIERTA, self::EN_CURSO, self::FINALIZADA, self::CANCELADA];

    public const MODALIDADES = ['PRESENCIAL', 'VIRTUAL', 'SEMIPRESENCIAL'];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'cupo_maximo' => 'integer',
        'cupo_minimo' => 'integer',
        'gestion' => 'integer',
    ];

    public function malla()
    {
        return $this->belongsTo(MallaCurricular::class, 'id_malla');
    }

    public function ofertaModulos()
    {
        return $this->hasMany(OfertaModulo::class, 'id_oferta');
    }

    public function aranceles()
    {
        return $this->hasMany(Arancel::class, 'id_oferta');
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'id_oferta');
    }

    public function preinscripciones()
    {
        return $this->hasMany(Preinscripcion::class, 'id_oferta');
    }

    /** Cupos ocupados: matrículas que no fueron anuladas. */
    public function cuposOcupados(): int
    {
        return $this->matriculas()->where('estado', '<>', Matricula::ANULADA)->count();
    }
}
