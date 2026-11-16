<?php

namespace App\Http\Controllers\Api\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Requisito 4 · Consulta de la bitácora de auditoría.
 */
class BitacoraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(Bitacora::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'por_pagina' => ['nullable', 'integer', 'between:5,100'],
        ]);

        $registros = Bitacora::with('usuario:id_usuario,nombres,apellidos,username')
            ->when($f['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($f['hasta'] ?? null, fn ($q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when($f['q'] ?? null, function ($q, $texto) {
                $patron = '%'.mb_strtolower($texto).'%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(descripcion) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(recurso) LIKE ?', [$patron])
                    ->orWhereRaw('LOWER(identificador) LIKE ?', [$patron])
                    ->orWhere('ip', 'like', $patron)
                    ->orWhereHas('usuario', fn ($u) => $u->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])));
            })
            ->orderByDesc('fecha')->orderByDesc('id_bitacora')
            ->paginate($f['por_pagina'] ?? 20);

        return response()->json($registros);
    }
}
