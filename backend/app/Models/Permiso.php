<?php

namespace App\Models;

class Permiso extends Modelo
{
    protected $table = 'permiso';

    protected $primaryKey = 'id_permiso';

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'id_permiso', 'id_rol');
    }
}
