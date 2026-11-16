<?php

namespace App\Models;

class Preinscripcion extends Modelo
{
    protected $table = 'preinscripcion';

    protected $primaryKey = 'id_preinscripcion';

    public const PENDIENTE = 'PENDIENTE';
    public const OBSERVADA = 'OBSERVADA';
    public const APROBADA = 'APROBADA';
    public const RECHAZADA = 'RECHAZADA';

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'fecha_revision' => 'datetime:Y-m-d H:i:s',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function oferta()
    {
        return $this->belongsTo(OfertaAcademica::class, 'id_oferta');
    }

    public function arancel()
    {
        return $this->belongsTo(Arancel::class, 'id_arancel');
    }

    public function revisor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_revisor');
    }

    public function matricula()
    {
        return $this->hasOne(Matricula::class, 'id_preinscripcion');
    }
}
