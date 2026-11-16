<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base de todos los modelos del ICAP.
 * Las tablas del script del grupo no tienen created_at/updated_at y usan
 * claves primarias con el formato id_tabla.
 */
abstract class Modelo extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
