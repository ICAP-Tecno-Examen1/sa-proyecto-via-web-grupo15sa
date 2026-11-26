<?php

namespace App\Models;

/**
 * Bitácora de auditoría: inicios de sesión, intentos fallidos, acciones y accesos denegados.
 */
class Bitacora extends Modelo
{
    protected $table = 'bitacora';

    protected $primaryKey = 'id_bitacora';

    public const LOGIN_EXITOSO = 'LOGIN_EXITOSO';

    public const LOGIN_FALLIDO = 'LOGIN_FALLIDO';

    public const LOGOUT = 'LOGOUT';

    public const ACCION = 'ACCION';

    public const ACCESO_DENEGADO = 'ACCESO_DENEGADO';

    public const TIPOS = [self::LOGIN_EXITOSO, self::LOGIN_FALLIDO, self::LOGOUT, self::ACCION, self::ACCESO_DENEGADO];

    protected $casts = [
        'fecha' => 'datetime:Y-m-d H:i:s',
        'codigo_respuesta' => 'integer',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
