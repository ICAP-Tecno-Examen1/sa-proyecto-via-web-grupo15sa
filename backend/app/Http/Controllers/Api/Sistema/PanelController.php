<?php

namespace App\Http\Controllers\Api\Sistema;

use App\Http\Controllers\Controller;
use App\Services\PanelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Requisito 8 · Panel analítico del negocio y de los accesos al sitio.
 */
class PanelController extends Controller
{
    public function __construct(private readonly PanelService $panel) {}

    /** Panel del rol activo: cada bloque aparece según sus permisos. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->panel->panel($request->user()));
    }

    /** Estadísticas detalladas de acceso (visitas, inicios de sesión, IP, recursos). */
    public function accesos(Request $request): JsonResponse
    {
        $datos = $request->validate(['dias' => ['nullable', 'integer', 'in:7,14,30,90']]);

        return response()->json($this->panel->accesos((int) ($datos['dias'] ?? 30)));
    }
}
