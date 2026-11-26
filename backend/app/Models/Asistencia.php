<?php

namespace App\Models;

class Asistencia extends Modelo
{
    protected $table = 'asistencia';

    protected $primaryKey = 'id_asistencia';

    public const PRESENTE = 'PRESENTE';

    public const AUSENTE = 'AUSENTE';

    public const TARDANZA = 'TARDANZA';

    public const LICENCIA = 'LICENCIA';

    public const ESTADOS = [self::PRESENTE, self::AUSENTE, self::TARDANZA, self::LICENCIA];

    // Estados que cuentan como asistencia cumplida.
    public const CUENTAN = [self::PRESENTE, self::TARDANZA, self::LICENCIA];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function inscripcion()
    {
        return $this->belongsTo(InscripcionModulo::class, 'id_inscripcion_modulo');
    }
}
