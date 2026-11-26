<?php

namespace App\Models;

class Matricula extends Modelo
{
    protected $table = 'matricula';

    protected $primaryKey = 'id_matricula';

    public const PENDIENTE_PAGO = 'PENDIENTE_PAGO';

    public const ACTIVA = 'ACTIVA';

    public const FINALIZADA = 'FINALIZADA';

    public const ANULADA = 'ANULADA';

    public const ESTADOS = [self::PENDIENTE_PAGO, self::ACTIVA, self::FINALIZADA, self::ANULADA];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
        'fecha_finalizacion' => 'date:Y-m-d',
        'monto_original' => 'float',
        'monto_descuento' => 'float',
        'monto_final' => 'float',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function oferta()
    {
        return $this->belongsTo(OfertaAcademica::class, 'id_oferta');
    }

    public function preinscripcion()
    {
        return $this->belongsTo(Preinscripcion::class, 'id_preinscripcion');
    }

    public function mensualidades()
    {
        return $this->hasMany(Mensualidad::class, 'id_matricula')->orderBy('numero_cuota');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_matricula');
    }

    public function inscripciones()
    {
        return $this->hasMany(InscripcionModulo::class, 'id_matricula');
    }

    public function becas()
    {
        return $this->hasMany(BecaEstudiante::class, 'id_matricula');
    }

    public function certificados()
    {
        return $this->hasMany(Certificado::class, 'id_matricula');
    }

    public function validaciones()
    {
        return $this->hasMany(ValidacionRequisito::class, 'id_matricula');
    }
}
