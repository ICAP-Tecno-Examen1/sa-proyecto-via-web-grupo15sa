<?php

namespace App\Models;

class Pago extends Modelo
{
    protected $table = 'pago';

    protected $primaryKey = 'id_pago';

    public const PENDIENTE = 'PENDIENTE';

    public const CONFIRMADO = 'CONFIRMADO';

    public const OBSERVADO = 'OBSERVADO';

    public const RECHAZADO = 'RECHAZADO';

    public const ESTADOS = [self::PENDIENTE, self::CONFIRMADO, self::OBSERVADO, self::RECHAZADO];

    protected $casts = [
        'monto' => 'float',
        'fecha' => 'datetime:Y-m-d H:i:s',
        'fecha_conciliacion' => 'datetime:Y-m-d H:i:s',
    ];

    protected $hidden = ['comprobante_url'];

    protected $appends = ['tiene_comprobante'];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function arancel()
    {
        return $this->belongsTo(Arancel::class, 'id_arancel');
    }

    public function mensualidad()
    {
        return $this->belongsTo(Mensualidad::class, 'id_mensualidad');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago');
    }

    public function pagador()
    {
        return $this->belongsTo(Pagador::class, 'id_pagador');
    }

    public function registrador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro');
    }

    public function conciliador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_conciliador');
    }

    public function getTieneComprobanteAttribute(): bool
    {
        return filled($this->comprobante_url);
    }
}
