<?php

namespace App\Models;

/**
 * Plantilla única del certificado / título (fila id_plantilla = 1). Las columnas de
 * imágenes guardan rutas del disco local (firmas y escudos subidos desde el sistema).
 */
class PlantillaCertificado extends Modelo
{
    protected $table = 'plantilla_certificado';

    protected $primaryKey = 'id_plantilla';

    protected $casts = ['mostrar_foto' => 'boolean'];

    /** Campos de imagen que se pueden subir. */
    public const IMAGENES = ['firmante_1_firma', 'firmante_2_firma', 'logo_izquierdo', 'logo_derecho'];

    public static function actual(): self
    {
        return static::query()->orderBy('id_plantilla')->firstOrFail();
    }
}
