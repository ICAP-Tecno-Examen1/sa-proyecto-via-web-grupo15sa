<?php

namespace App\Models;

class MenuItem extends Modelo
{
    protected $table = 'menu_item';

    protected $primaryKey = 'id_menu_item';

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function hijos()
    {
        return $this->hasMany(self::class, 'id_padre')->orderBy('orden');
    }

    public function permiso()
    {
        return $this->belongsTo(Permiso::class, 'id_permiso');
    }
}
