<?php

namespace App\Models;

class ExpedienteDocumento extends Modelo
{
    protected $table = 'expediente_documento';

    protected $primaryKey = 'id_expediente_documento';

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'id_documento');
    }
}
