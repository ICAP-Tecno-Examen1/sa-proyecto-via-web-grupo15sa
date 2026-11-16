<?php

namespace App\Models;

class ValidacionRequisito extends Modelo
{
    protected $table = 'validacion_requisito';

    protected $primaryKey = 'id_validacion';

    public const ACADEMICO = 'ACADEMICO';
    public const FINANCIERO = 'FINANCIERO';
    public const DOCUMENTAL = 'DOCUMENTAL';
    public const TIPOS = [self::ACADEMICO, self::FINANCIERO, self::DOCUMENTAL];

    public const PENDIENTE = 'PENDIENTE';
    public const APROBADO = 'APROBADO';
    public const OBSERVADO = 'OBSERVADO';

    protected $casts = [
        'fecha_validacion' => 'datetime:Y-m-d H:i:s',
    ];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function validador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_validador');
    }
}
