<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

/**
 * Usuario del sistema (tabla usuario). La contraseña se guarda cifrada en
 * password_hash; la columna password del script no se utiliza.
 *
 * El control de acceso (RBAC) se resuelve con el ROL ACTIVO: un usuario puede
 * tener varios roles, pero en cada petición trabaja con uno solo, indicado en
 * la cabecera X-Rol-Activo. Sus permisos son los de ese rol.
 */
class Usuario extends Authenticatable
{
    use HasApiTokens;

    public const ACTIVO = 'ACTIVO';

    public const INACTIVO = 'INACTIVO';

    public const TIPOS_DOCUMENTO = ['CI', 'PASAPORTE', 'CARNET_EXTRANJERO'];

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['password', 'password_hash'];

    protected $appends = ['nombre_completo'];

    protected $casts = [
        'fecha_nacimiento' => 'date:Y-m-d',
        'fecha_registro' => 'datetime:Y-m-d H:i:s',
    ];

    private ?Rol $rolActivoCache = null;

    private ?Collection $permisosCache = null;

    // ---------------------------------------------------------------
    // Autenticación
    // ---------------------------------------------------------------

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function estaActivo(): bool
    {
        return $this->estado === self::ACTIVO;
    }

    // ---------------------------------------------------------------
    // Relaciones
    // ---------------------------------------------------------------

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'id_usuario', 'id_rol')
            ->withPivot('activo', 'fecha_asignacion');
    }

    public function rolesActivos()
    {
        return $this->roles()->wherePivot('activo', true)->orderBy('rol.id_rol');
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class, 'id_usuario');
    }

    public function preinscripciones()
    {
        return $this->hasMany(Preinscripcion::class, 'id_usuario');
    }

    public function asignacionesDocente()
    {
        return $this->hasMany(AsignacionDocente::class, 'id_usuario_docente');
    }

    /** Expediente único: documentos asociados al usuario. */
    public function documentos()
    {
        return $this->belongsToMany(Documento::class, 'expediente_documento', 'id_usuario', 'id_documento')
            ->withPivot('fecha_asociacion');
    }

    // ---------------------------------------------------------------
    // Control de acceso (RBAC)
    // ---------------------------------------------------------------

    /**
     * Rol con el que el usuario trabaja en esta petición. Si la cabecera
     * X-Rol-Activo no corresponde a uno de sus roles, se usa el primero.
     */
    /**
     * Comprueba la contraseña. Las cuentas del primer parcial guardan bcrypt con prefijo
     * $2a$/$2b$ (generado por otra tecnología) que Laravel rechaza aunque es el mismo
     * algoritmo: se verifica con password_verify y, si es correcta, se convierte al
     * formato de Laravel ($2y$) para las siguientes veces.
     */
    public function verificarContrasena(string $plana): bool
    {
        $hash = (string) $this->password_hash;

        if ($hash === '') {
            return false;
        }

        if (preg_match('/^\$2[ab]\$/', $hash)) {
            if (! password_verify($plana, $hash)) {
                return false;
            }

            $this->forceFill(['password_hash' => Hash::make($plana)])->saveQuietly();

            return true;
        }

        try {
            return Hash::check($plana, $hash);
        } catch (Throwable) {
            return false; // formato desconocido: se trata como contraseña incorrecta, no como error 500
        }
    }

    public function rolActivo(): ?Rol
    {
        if ($this->rolActivoCache) {
            return $this->rolActivoCache;
        }

        $roles = $this->rolesActivos()->get();
        $solicitado = (int) request()->header('X-Rol-Activo');

        return $this->rolActivoCache = $roles->firstWhere('id_rol', $solicitado) ?? $roles->first();
    }

    /** Acciones (permiso.accion) concedidas al rol activo. */
    public function permisos(): Collection
    {
        if ($this->permisosCache) {
            return $this->permisosCache;
        }

        $rol = $this->rolActivo();

        return $this->permisosCache = $rol ? $rol->permisos()->pluck('accion')->unique()->values() : collect();
    }

    public function tienePermiso(string $accion): bool
    {
        return $this->permisos()->contains($accion);
    }

    /** Verdadero si el rol activo tiene al menos uno de los permisos. */
    public function tieneAlgunPermiso(array $acciones): bool
    {
        return $this->permisos()->intersect($acciones)->isNotEmpty();
    }

    /** Verdadero si el usuario tiene asignado (y activo) el rol indicado. */
    public function tieneRol(string $nombre): bool
    {
        return $this->rolesActivos()->where('rol.nombre', $nombre)->exists();
    }

    public function esRolActivo(string $nombre): bool
    {
        return $this->rolActivo()?->nombre === $nombre;
    }
}
