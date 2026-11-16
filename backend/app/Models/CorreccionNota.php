<?php

namespace App\Models;

class CorreccionNota extends Modelo
{
    protected $table = 'correccion_nota';

    protected $primaryKey = 'id_correccion';

    public const PENDIENTE = 'PENDIENTE';
    public const APROBADA = 'APROBADA';
    public const RECHAZADA = 'RECHAZADA';

    protected $casts = [
        'nota_anterior' => 'float',
        'nota_nueva' => 'float',
        'fecha_solicitud' => 'datetime:Y-m-d H:i:s',
        'fecha_autorizacion' => 'datetime:Y-m-d H:i:s',
    ];

    public function nota()
    {
        return $this->belongsTo(Nota::class, 'id_nota');
    }

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_solicitante');
    }

    public function autorizador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_autorizador');
    }
}
