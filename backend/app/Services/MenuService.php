<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Usuario;
use Illuminate\Support\Collection;

/**
 * CU1 · Menú dinámico (requisito 2).
 *
 * El menú NO está escrito en el cliente: se lee de la tabla menu_item y se
 * filtra con los permisos del rol activo. Cada opción indica el permiso que
 * exige (id_permiso); una sección solo aparece si conserva alguna opción.
 */
class MenuService
{
    public function paraUsuario(Usuario $usuario): array
    {
        $permisos = $usuario->permisos();

        return MenuItem::whereNull('id_padre')->where('activo', true)->orderBy('orden')
            ->with(['permiso', 'hijos' => fn ($q) => $q->where('activo', true)->with('permiso')])
            ->get()
            ->map(function (MenuItem $seccion) use ($permisos) {
                $hijos = $seccion->hijos->filter(fn (MenuItem $h) => $this->permitido($h, $permisos))
                    ->map(fn (MenuItem $h) => $this->formato($h))->unique('ruta')->values();

                if ($hijos->isEmpty() && (! $seccion->ruta || ! $this->permitido($seccion, $permisos))) {
                    return null;
                }

                return [...$this->formato($seccion), 'hijos' => $hijos];
            })
            ->filter()->values()->all();
    }

    private function permitido(MenuItem $item, Collection $permisos): bool
    {
        return ! $item->id_permiso || $permisos->contains($item->permiso?->accion);
    }

    private function formato(MenuItem $item): array
    {
        return [
            'id' => $item->id_menu_item,
            'etiqueta' => $item->etiqueta,
            'ruta' => $item->ruta,
            'icono' => $item->icono,
        ];
    }
}
