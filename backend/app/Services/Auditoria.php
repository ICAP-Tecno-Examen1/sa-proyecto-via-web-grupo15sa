<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Escribe en la bitácora de auditoría (requisito 4).
 * Nunca interrumpe la petición: si la escritura falla, se ignora.
 */
class Auditoria
{
    /** Atributo de la petición con el que un controlador describe la acción realizada. */
    private const DESCRIPCION = 'bitacora.descripcion';

    public static function registrar(
        string $tipo,
        ?string $descripcion = null,
        ?Usuario $usuario = null,
        ?string $identificador = null,
        ?int $codigo = null,
        ?Request $request = null,
    ): void {
        $request ??= request();

        try {
            Bitacora::create([
                'id_usuario' => $usuario?->id_usuario,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'recurso' => Str::limit(self::recurso($request), 200, ''),
                'metodo' => $request->method(),
                'codigo_respuesta' => $codigo,
                'identificador' => $identificador ? Str::limit($identificador, 150, '') : null,
                'ip' => $request->ip() ?? '0.0.0.0',
                'agente_usuario' => Str::limit((string) $request->userAgent(), 500, ''),
                'fecha' => now(),
            ]);
        } catch (Throwable) {
            // La auditoría no debe tumbar la operación principal.
        }
    }

    /** Permite a un controlador dejar un texto legible para la bitácora. */
    public static function describir(string $descripcion): void
    {
        request()->attributes->set(self::DESCRIPCION, $descripcion);
    }

    public static function descripcion(Request $request): ?string
    {
        return $request->attributes->get(self::DESCRIPCION);
    }

    /**
     * Recurso accedido: el patrón de la ruta (api/usuarios/{usuario}) en lugar
     * de la URL concreta, para poder agrupar "recursos más accedidos".
     */
    public static function recurso(Request $request): string
    {
        return $request->route()?->uri() ?? $request->path();
    }
}
