<?php

namespace App\Models;

class Rol extends Modelo
{
    protected $table = 'rol';

    protected $primaryKey = 'id_rol';

    // Roles de negocio del ICAP (los seis actores del sistema).
    public const DIRECTOR = 'Director';
    public const SECRETARIA = 'Secretaria';
    public const COORDINADOR_ACADEMICO = 'Coordinador Académico';
    public const COORDINADOR_FINANCIERO = 'Coordinador Financiero';
    public const DOCENTE = 'Docente';
    public const ESTUDIANTE = 'Estudiante';

    public const BASE = [
        self::DIRECTOR, self::SECRETARIA, self::COORDINADOR_ACADEMICO,
        self::COORDINADOR_FINANCIERO, self::DOCENTE, self::ESTUDIANTE,
    ];

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'id_rol', 'id_permiso');
    }

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_rol', 'id_rol', 'id_usuario')->withPivot('activo');
    }

    public function esBase(): bool
    {
        return in_array($this->nombre, self::BASE, true);
    }
}
