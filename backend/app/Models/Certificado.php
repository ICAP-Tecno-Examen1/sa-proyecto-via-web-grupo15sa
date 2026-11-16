<?php

namespace App\Models;

class Certificado extends Modelo
{
    protected $table = 'certificado';

    protected $primaryKey = 'id_certificado';

    public const EMITIDO = 'EMITIDO';
    public const ANULADO = 'ANULADO';
    public const TIPO_APROBACION = 'APROBACION';

    protected $casts = [
        'fecha_emision' => 'date:Y-m-d',
    ];

    protected $hidden = ['url_archivo'];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function autorizador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_autorizador');
    }
}
