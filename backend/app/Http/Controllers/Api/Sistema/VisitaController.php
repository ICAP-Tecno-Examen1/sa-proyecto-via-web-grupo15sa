<?php

namespace App\Http\Controllers\Api\Sistema;

use App\Http\Controllers\Controller;
use App\Models\ContadorVisita;
use App\Models\Visita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Requisito 7 · Contador de visitas por página.
 *
 * El cliente React avisa cada vez que se muestra una página; aquí se inserta
 * la visita y el trigger trg_visita_contador actualiza el acumulado que se
 * devuelve para pintarlo en el pie de página.
 */
class VisitaController extends Controller
{
    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ruta' => ['required', 'string', 'max:200', 'regex:/^\/[A-Za-z0-9\/_:.-]*$/'],
            'titulo' => ['nullable', 'string', 'max:150'],
        ]);

        Visita::create([
            'ruta' => $datos['ruta'],
            'titulo' => $datos['titulo'] ?? null,
            // La ruta es pública: si viene un token válido se asocia la visita al usuario.
            'id_usuario' => Auth::guard('sanctum')->id(),
            'ip' => $request->ip(),
            'fecha' => now(),
        ]);

        return response()->json([
            'ruta' => $datos['ruta'],
            'total' => (int) ContadorVisita::whereKey($datos['ruta'])->value('total'),
            'total_sitio' => (int) ContadorVisita::sum('total'),
        ]);
    }
}
