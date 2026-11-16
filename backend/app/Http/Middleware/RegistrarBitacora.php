<?php

namespace App\Http\Middleware;

use App\Models\Bitacora;
use App\Services\Auditoria;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bitácora de tráfico de la API (requisito 4).
 *
 * Registra, DESPUÉS de enviar la respuesta:
 *   - toda acción que modifica datos (POST, PUT, PATCH, DELETE) -> ACCION
 *   - todo intento bloqueado por la matriz de acceso (401/403)  -> ACCESO_DENEGADO
 *
 * Las consultas (GET) no se guardan aquí: el flujo de navegación se mide
 * con el contador de visitas por página.
 */
class RegistrarBitacora
{
    /** Rutas que llevan su propio registro o que no aportan a la auditoría. */
    private const EXCLUIDAS = ['api/auth/login', 'api/auth/logout', 'api/visitas'];

    private const VERBOS = [
        'POST' => 'Registró',
        'PUT' => 'Actualizó',
        'PATCH' => 'Actualizó',
        'DELETE' => 'Eliminó',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $recurso = Auditoria::recurso($request);

        if (in_array($recurso, self::EXCLUIDAS, true)) {
            return;
        }

        $codigo = $response->getStatusCode();
        $usuario = $request->user();

        if (in_array($codigo, [401, 403], true)) {
            Auditoria::registrar(Bitacora::ACCESO_DENEGADO, 'Acceso denegado al recurso', $usuario, null, $codigo, $request);

            return;
        }

        if ($request->isMethodSafe() || $codigo >= 400) {
            return;
        }

        $descripcion = Auditoria::descripcion($request)
            ?? (self::VERBOS[$request->method()] ?? $request->method()).' en '.$recurso;

        Auditoria::registrar(Bitacora::ACCION, $descripcion, $usuario, null, $codigo, $request);
    }
}
