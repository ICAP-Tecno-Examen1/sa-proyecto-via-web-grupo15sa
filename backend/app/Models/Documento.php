<?php

namespace App\Models;

class Documento extends Modelo
{
    protected $table = 'documento';

    protected $primaryKey = 'id_documento';

    public const CATEGORIAS = ['PERSONAL', 'ACADEMICO', 'FINANCIERO', 'INSTITUCIONAL'];
    public const TIPOS = [
        'CI' => 'Cédula de identidad',
        'TITULO' => 'Título o certificado de estudios',
        'FOTOGRAFIA' => 'Fotografía',
        'CERTIFICADO_NACIMIENTO' => 'Certificado de nacimiento',
        'COMPROBANTE_PAGO' => 'Comprobante de pago',
        'ACTA_NOTAS' => 'Acta de calificaciones',
        'CERTIFICADO' => 'Certificado emitido',
        'CONTRATO' => 'Contrato',
        'INFORME' => 'Informe',
        'RESOLUCION' => 'Resolución',
        'OTRO' => 'Otro',
    ];
    public const VIGENTE = 'VIGENTE';
    public const ARCHIVADO = 'ARCHIVADO';

    protected $casts = [
        'fecha_documento' => 'date:Y-m-d',
        'fecha_registro' => 'datetime:Y-m-d H:i:s',
        'gestion' => 'integer',
        'tamano_bytes' => 'integer',
    ];

    protected $hidden = ['url_archivo'];

    protected $appends = ['tiene_archivo'];

    public function registrador()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario_registro');
    }

    public function getTieneArchivoAttribute(): bool
    {
        return filled($this->url_archivo);
    }

    /** Titulares del documento: usuarios en cuyo expediente único figura. */
    public function titulares()
    {
        return $this->belongsToMany(Usuario::class, 'expediente_documento', 'id_documento', 'id_usuario')
            ->withPivot('fecha_asociacion');
    }
}
