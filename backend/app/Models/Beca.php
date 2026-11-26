<?php

namespace App\Models;

/**
 * Beca o descuento. Diseño original: la beca ES el convenio; institucion_financiadora
 * es la institución o empresa con la que el ICAP tiene el convenio (vacía = beca propia
 * del ICAP). Puede limitarse a cursos específicos (sin ofertas = aplica a todos).
 * El vínculo con cada estudiante está en beca_estudiante (por matrícula).
 */
class Beca extends Modelo
{
    protected $table = 'beca';

    protected $primaryKey = 'id_beca';

    public const ACTIVA = 'ACTIVA';

    public const INACTIVA = 'INACTIVA';

    protected $casts = [
        'porcentaje' => 'float',
    ];

    public function asignaciones()
    {
        return $this->hasMany(BecaEstudiante::class, 'id_beca');
    }

    public function ofertas()
    {
        return $this->belongsToMany(OfertaAcademica::class, 'beca_oferta', 'id_beca', 'id_oferta');
    }

    /** Las becas de una institución (convenio) exigen su documento: credencial, carta o constancia. */
    public function requiereRespaldo(): bool
    {
        return filled($this->institucion_financiadora);
    }

    /** Motivo por el que la beca NO aplica a la matrícula, o null si aplica. */
    public function motivoNoAplica(Matricula $matricula): ?string
    {
        if ($this->estado !== self::ACTIVA) {
            return "La beca «{$this->nombre}» está inactiva.";
        }

        $ofertas = $this->relationLoaded('ofertas') ? $this->ofertas : $this->ofertas()->get();

        if ($ofertas->isNotEmpty() && ! $ofertas->contains('id_oferta', $matricula->id_oferta)) {
            return "La beca «{$this->nombre}» no cubre el curso de esta matrícula.";
        }

        return null;
    }
}
