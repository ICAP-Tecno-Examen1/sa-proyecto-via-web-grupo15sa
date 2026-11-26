<?php

namespace App\Models;

/**
 * Beca de una matrícula. Ciclo de vida:
 *   SOLICITADA (el estudiante la pidió con su respaldo) -> ACTIVA (aprobada: se aplica el descuento)
 *                                                       -> RECHAZADA
 *   ACTIVA -> REVOCADA
 * Una beca asignada directamente por el Coordinador Financiero nace ACTIVA.
 */
class BecaEstudiante extends Modelo
{
    protected $table = 'beca_estudiante';

    protected $primaryKey = 'id_beca_estudiante';

    public const SOLICITADA = 'SOLICITADA';

    public const ACTIVA = 'ACTIVA';

    public const RECHAZADA = 'RECHAZADA';

    public const REVOCADA = 'REVOCADA';

    protected $casts = [
        'porcentaje_aplicado' => 'float',
        'fecha_asignacion' => 'date:Y-m-d',
        'fecha_solicitud' => 'datetime',
        'fecha_revision' => 'datetime',
    ];

    // El archivo de respaldo se descarga por su propia ruta, con control de acceso.
    protected $hidden = ['respaldo_url'];

    public function beca()
    {
        return $this->belongsTo(Beca::class, 'id_beca');
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function revisor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_revisor');
    }
}
