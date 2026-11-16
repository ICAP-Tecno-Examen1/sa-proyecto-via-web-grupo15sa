<?php

namespace App\Models;

/**
 * Persona que realiza el pago (no siempre es el propio estudiante).
 */
class Pagador extends Modelo
{
    protected $table = 'pagador';

    protected $primaryKey = 'id_pagador';

    protected $appends = ['nombre_completo'];

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }
}
