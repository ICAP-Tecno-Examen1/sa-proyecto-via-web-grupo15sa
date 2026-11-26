<?php

namespace App\Models;

class Mensualidad extends Modelo
{
    protected $table = 'mensualidad';

    protected $primaryKey = 'id_mensualidad';

    public const PENDIENTE = 'PENDIENTE';

    public const PAGADA = 'PAGADA';

    public const ANULADA = 'ANULADA';

    protected $casts = [
        'fecha_vencimiento' => 'date:Y-m-d',
        'numero_cuota' => 'integer',
        'monto_original' => 'float',
        'monto_descuento' => 'float',
        'monto_final' => 'float',
    ];

    public function matricula()
    {
        return $this->belongsTo(Matricula::class, 'id_matricula');
    }

    public function arancel()
    {
        return $this->belongsTo(Arancel::class, 'id_arancel');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_mensualidad');
    }

    /** Suma de los pagos ya conciliados de esta cuota. */
    public function montoPagado(): float
    {
        return (float) $this->pagos()->where('estado_conciliacion', Pago::CONFIRMADO)->sum('monto');
    }

    /** Lo que falta pagar de la cuota. */
    public function saldo(): float
    {
        if ($this->estado !== self::PENDIENTE) {
            return 0.0;
        }

        return max(0, round($this->monto_final - $this->montoPagado(), 2));
    }
}
