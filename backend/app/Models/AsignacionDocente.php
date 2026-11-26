<?php

namespace App\Models;

class AsignacionDocente extends Modelo
{
    protected $table = 'asignacion_docente';

    protected $primaryKey = 'id_asignacion_docente';

    public const ACTIVO = 'ACTIVO';

    public const INACTIVO = 'INACTIVO';

    public const TIPOS_CONTRATO = ['POR_MODULO', 'POR_HORAS', 'CONSULTORIA', 'PLANTA'];

    protected $casts = [
        'fecha_contrato' => 'date:Y-m-d',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'monto_honorario' => 'float',
    ];

    public function ofertaModulo()
    {
        return $this->belongsTo(OfertaModulo::class, 'id_oferta_modulo');
    }

    public function docente()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_docente');
    }
}
